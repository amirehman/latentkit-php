<?php

declare(strict_types=1);

namespace LatentKit;

use JsonException;
use LatentKit\Exceptions\ApiException;
use LatentKit\Exceptions\AuthenticationException;
use LatentKit\Exceptions\InvalidResponseException;
use LatentKit\Exceptions\LatentKitException;
use LatentKit\Exceptions\RateLimitException;
use LatentKit\Exceptions\ValidationException;
use LatentKit\Resources\ChatResource;
use LatentKit\Resources\DecisionsResource;
use LatentKit\Resources\EmbeddingsResource;
use LatentKit\Resources\ImageResource;
use LatentKit\Resources\MeResource;
use LatentKit\Resources\QueueResource;
use LatentKit\Resources\SpeechResource;
use LatentKit\Resources\TranscriptionResource;
use LatentKit\Resources\VisionResource;
use LatentKit\Streaming\SseParser;
use LatentKit\Transport\CurlTransport;
use LatentKit\Transport\HttpResponse;
use LatentKit\Transport\StreamedHttpResponse;
use LatentKit\Transport\StreamingTransportInterface;
use LatentKit\Transport\TransportInterface;

final class LatentKit
{
    private const DEFAULT_BASE_URL = 'https://ai.latentkit.com';

    private readonly string $baseV1;

    /** @var array<string, string> */
    private readonly array $headers;

    private readonly TransportInterface $transport;

    public readonly ChatResource $chat;
    public readonly VisionResource $vision;
    public readonly EmbeddingsResource $embeddings;
    public readonly ImageResource $image;
    public readonly MeResource $me;
    public readonly QueueResource $queue;
    public readonly DecisionsResource $decisions;
    public readonly SpeechResource $speech;
    public readonly TranscriptionResource $transcription;

    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        ?string $apiKey = null,
        ?string $baseUrl = null,
        private readonly float $timeoutSeconds = 120.0,
        array $headers = [],
        ?TransportInterface $transport = null,
        ?string $toolSlug = null,
        ?string $toolVersion = null,
        ?string $appId = null,
    ) {
        $resolvedApiKey = self::resolveValue($apiKey, 'LATENTKIT_API_KEY');
        if ($resolvedApiKey === null) {
            throw new LatentKitException(
                'Missing LatentKit API key. Pass apiKey or set the LATENTKIT_API_KEY environment variable.',
            );
        }
        if ($this->timeoutSeconds <= 0) {
            throw new \InvalidArgumentException('timeoutSeconds must be greater than zero.');
        }

        $resolvedBaseUrl = self::resolveValue($baseUrl, 'LATENTKIT_BASE_URL') ?? self::DEFAULT_BASE_URL;
        $this->baseV1 = self::normalizeBaseUrl($resolvedBaseUrl);
        $this->headers = self::assertSafeHeaders(array_merge(
            [
                'Authorization' => "Bearer {$resolvedApiKey}",
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                // Tells LatentKit when this client stops waiting, so the gateway does
                // not keep trying providers for a response nobody will read.
                'X-LK-Client-Timeout-Ms' => (string) max(1, (int) round($this->timeoutSeconds * 1000)),
            ],
            $toolSlug !== null && $toolSlug !== '' ? ['X-LK-Tool-Slug' => $toolSlug] : [],
            $toolVersion !== null && $toolVersion !== '' ? ['X-LK-Tool-Version' => $toolVersion] : [],
            $appId !== null && $appId !== '' ? ['X-LK-App-ID' => $appId] : [],
            $headers,
        ));
        $this->transport = $transport ?? new CurlTransport();

        $requestJson = \Closure::fromCallable($this->requestJson(...));
        $this->chat = new ChatResource($requestJson, \Closure::fromCallable($this->requestStream(...)));
        $this->vision = new VisionResource($requestJson);
        $this->embeddings = new EmbeddingsResource($requestJson);
        $this->image = new ImageResource($requestJson);
        $this->me = new MeResource($requestJson);
        $this->queue = new QueueResource($requestJson);
        $this->decisions = new DecisionsResource($requestJson);
        $this->speech = new SpeechResource($requestJson);
        $this->transcription = new TranscriptionResource($requestJson);
    }

    /**
     * @param array<string, mixed>|null $body
     * @return array<string, mixed>
     */
    private function requestJson(string $method, string $path, ?array $body = null): array
    {
        try {
            $encodedBody = $body === null ? null : json_encode($body, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new \InvalidArgumentException('LatentKit request body is not valid JSON.', 0, $exception);
        }

        $response = $this->transport->request(
            $method,
            $this->baseV1 . $path,
            $this->headers,
            $encodedBody,
            $this->timeoutSeconds,
        );

        if ($response->statusCode < 200 || $response->statusCode >= 300) {
            throw $this->apiException($response);
        }

        try {
            $decoded = json_decode($response->body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidResponseException('LatentKit returned an invalid JSON response.', 0, $exception);
        }
        if (!is_array($decoded) || array_is_list($decoded)) {
            throw new InvalidResponseException('LatentKit returned a JSON response that was not an object.');
        }

        return $decoded;
    }

    /**
     * Sends a streaming request and returns its events.
     *
     * The request is sent before this returns, so a refused request raises here rather
     * than at the first iteration. Events are then read as they arrive.
     *
     * @param array<string, mixed> $body
     *
     * @return \Generator<int, array{event: string, data: array<string, mixed>}>
     */
    private function requestStream(string $method, string $path, array $body): \Generator
    {
        if (!$this->transport instanceof StreamingTransportInterface) {
            throw new LatentKitException(
                'The configured transport cannot stream responses. Use CurlTransport or a transport '
                . 'that implements StreamingTransportInterface.',
            );
        }
        try {
            $encodedBody = json_encode($body, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new \InvalidArgumentException('LatentKit request body is not valid JSON.', 0, $exception);
        }

        $response = $this->transport->stream(
            $method,
            $this->baseV1 . $path,
            array_merge($this->headers, ['Accept' => 'text/event-stream']),
            $encodedBody,
            $this->timeoutSeconds,
        );

        if ($response->statusCode < 200 || $response->statusCode >= 300) {
            $raw = '';
            foreach ($response->chunks as $chunk) {
                $raw .= $chunk;
            }
            throw $this->apiException(new HttpResponse($response->statusCode, $raw, $response->headers));
        }

        return $this->streamEvents($response);
    }

    /**
     * @return \Generator<int, array{event: string, data: array<string, mixed>}>
     */
    private function streamEvents(StreamedHttpResponse $response): \Generator
    {
        foreach (SseParser::parse($response->chunks) as $event) {
            if ($event['data'] === '[DONE]') {
                return;
            }
            try {
                $decoded = json_decode($event['data'], true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw new InvalidResponseException('LatentKit sent a stream event that was not valid JSON.', 0, $exception);
            }
            if (!is_array($decoded) || array_is_list($decoded)) {
                throw new InvalidResponseException('LatentKit sent a stream event that was not a JSON object.');
            }

            $name = $event['event'] ?? 'message';
            if ($name === 'error' || $name === 'response.failed') {
                // The stream began with HTTP 200, so the failure has no status of its own.
                // Zero marks it as an in-stream error; the code, category and request ID
                // carry the meaning.
                throw $this->apiException(new HttpResponse(0, $event['data'], $response->headers));
            }

            yield ['event' => $name, 'data' => $decoded];
        }
    }

    private function apiException(HttpResponse $response): ApiException
    {
        $body = null;
        try {
            $decoded = json_decode($response->body, true, 512, JSON_THROW_ON_ERROR);
            $body = is_array($decoded) && !array_is_list($decoded) ? $decoded : $response->body;
        } catch (JsonException) {
            $body = $response->body !== '' ? $response->body : null;
        }

        $message = self::extractString($body, ['error', 'message'])
            ?? self::extractString($body, ['message'])
            ?? self::extractString($body, ['detail'])
            ?? (is_string($body) && $body !== '' ? $body : null);
        $apiCode = self::extractString($body, ['error', 'code'])
            ?? self::extractString($body, ['code'])
            ?? self::extractString($body, ['error']);
        $requestId = self::extractString($body, ['error', 'request_id'])
            ?? self::extractString($body, ['request_id'])
            ?? $response->header('X-LK-Request-ID');

        $class = match ($response->statusCode) {
            401, 403 => AuthenticationException::class,
            429 => RateLimitException::class,
            400, 422 => ValidationException::class,
            default => ApiException::class,
        };

        return new $class(
            $response->statusCode,
            $body,
            $apiCode,
            $requestId,
            $message,
            self::extractString($body, ['error', 'category']) ?? self::extractString($body, ['category']),
            self::extractBool($body, ['error', 'retryable']) ?? self::extractBool($body, ['retryable']),
            self::retryAfterSeconds($response, $body),
            self::extractString($body, ['error', 'suggested_action']),
        );
    }

    /**
     * @param array<string, mixed>|string|null $body
     */
    private static function retryAfterSeconds(HttpResponse $response, array|string|null $body): ?int
    {
        $header = $response->header('Retry-After');
        if ($header !== null) {
            $header = trim($header);
            if (preg_match('/^\d+$/', $header) === 1) {
                return (int) $header;
            }
            // RFC 9110 also allows an HTTP date.
            $timestamp = $header !== '' ? strtotime($header) : false;
            if ($timestamp !== false) {
                return max(0, $timestamp - time());
            }
        }

        foreach ([['detail', 'retry_after_seconds'], ['detail', 'retry_after'], ['retry_after_seconds']] as $path) {
            $value = self::extractValue($body, $path);
            if (is_int($value) || is_float($value) || (is_string($value) && is_numeric($value))) {
                return max(0, (int) ceil((float) $value));
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed>|string|null $body
     * @param list<string> $path
     */
    private static function extractBool(array|string|null $body, array $path): ?bool
    {
        $value = self::extractValue($body, $path);
        return is_bool($value) ? $value : null;
    }

    /**
     * @param array<string, mixed>|string|null $body
     * @param list<string> $path
     */
    private static function extractString(array|string|null $body, array $path): ?string
    {
        $value = self::extractValue($body, $path);
        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @param array<string, mixed>|string|null $body
     * @param list<string> $path
     */
    private static function extractValue(array|string|null $body, array $path): mixed
    {
        if (!is_array($body)) {
            return null;
        }
        $value = $body;
        foreach ($path as $key) {
            if (!is_array($value) || !array_key_exists($key, $value)) {
                return null;
            }
            $value = $value[$key];
        }
        return $value;
    }

    /**
     * Surrounding whitespace is removed: a key or URL pasted with a trailing newline
     * would otherwise produce a malformed header or an unreachable host.
     */
    private static function resolveValue(?string $explicit, string $environmentName): ?string
    {
        if ($explicit !== null && trim($explicit) !== '') {
            return trim($explicit);
        }
        $environment = getenv($environmentName);
        return is_string($environment) && trim($environment) !== '' ? trim($environment) : null;
    }

    /**
     * Reject header names and values that could split or smuggle a header line.
     *
     * @param array<array-key, mixed> $headers
     * @return array<string, string>
     */
    private static function assertSafeHeaders(array $headers): array
    {
        $safe = [];
        foreach ($headers as $name => $value) {
            if (!is_string($name) || preg_match('/^[A-Za-z0-9!#$%&\'*+.^_`|~-]+$/', $name) !== 1) {
                throw new \InvalidArgumentException('LatentKit header names must be valid HTTP header tokens.');
            }
            if (!is_string($value) || preg_match('/[\x00-\x08\x0A-\x1F\x7F]/', $value) === 1) {
                // The value is never echoed: it may be the API key.
                throw new \InvalidArgumentException(
                    "The value of the '{$name}' header must be a string without control characters.",
                );
            }
            $safe[$name] = $value;
        }
        return $safe;
    }

    private static function normalizeBaseUrl(string $baseUrl): string
    {
        $normalized = rtrim($baseUrl, '/');
        $parts = parse_url($normalized);
        if (
            $normalized === ''
            || $parts === false
            || !isset($parts['scheme'], $parts['host'])
            || !in_array(strtolower((string) $parts['scheme']), ['http', 'https'], true)
        ) {
            throw new \InvalidArgumentException('baseUrl must be an absolute HTTP or HTTPS URL.');
        }
        return str_ends_with($normalized, '/v1') ? $normalized : $normalized . '/v1';
    }
}
