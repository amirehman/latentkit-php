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
use LatentKit\Resources\EmbeddingsResource;
use LatentKit\Resources\ImageResource;
use LatentKit\Resources\MeResource;
use LatentKit\Resources\QueueResource;
use LatentKit\Resources\VisionResource;
use LatentKit\Transport\CurlTransport;
use LatentKit\Transport\HttpResponse;
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
        $this->headers = array_merge(
            [
                'Authorization' => "Bearer {$resolvedApiKey}",
                'Content-Type' => 'application/json',
            ],
            $toolSlug !== null && $toolSlug !== '' ? ['X-LK-Tool-Slug' => $toolSlug] : [],
            $toolVersion !== null && $toolVersion !== '' ? ['X-LK-Tool-Version' => $toolVersion] : [],
            $appId !== null && $appId !== '' ? ['X-LK-App-ID' => $appId] : [],
            $headers,
        );
        $this->transport = $transport ?? new CurlTransport();

        $requestJson = \Closure::fromCallable($this->requestJson(...));
        $this->chat = new ChatResource($requestJson);
        $this->vision = new VisionResource($requestJson);
        $this->embeddings = new EmbeddingsResource($requestJson);
        $this->image = new ImageResource($requestJson);
        $this->me = new MeResource($requestJson);
        $this->queue = new QueueResource($requestJson);
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

        return new $class($response->statusCode, $body, $apiCode, $requestId, $message);
    }

    /**
     * @param array<string, mixed>|string|null $body
     * @param list<string> $path
     */
    private static function extractString(array|string|null $body, array $path): ?string
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
        return is_string($value) && $value !== '' ? $value : null;
    }

    private static function resolveValue(?string $explicit, string $environmentName): ?string
    {
        if ($explicit !== null && trim($explicit) !== '') {
            return $explicit;
        }
        $environment = getenv($environmentName);
        return is_string($environment) && trim($environment) !== '' ? $environment : null;
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
