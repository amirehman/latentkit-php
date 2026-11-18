<?php

declare(strict_types=1);

namespace LatentKit\Tests;

use LatentKit\Transport\HttpResponse;
use LatentKit\Transport\StreamedHttpResponse;
use LatentKit\Transport\StreamingTransportInterface;
use LatentKit\Transport\TransportInterface;

/**
 * A transport that replays queued body chunks, split exactly as a test supplies them.
 */
final class StreamingMockTransport implements TransportInterface, StreamingTransportInterface
{
    /** @var list<array{method: string, url: string, headers: array<string, string>, body: ?string, timeout: float}> */
    public array $requests = [];

    /** @var list<array{status: int, chunks: list<string>}> */
    private array $streams = [];

    /** @param list<string> $chunks */
    public function pushStream(array $chunks, int $status = 200): void
    {
        $this->streams[] = ['status' => $status, 'chunks' => $chunks];
    }

    public function request(
        string $method,
        string $url,
        array $headers,
        ?string $body,
        float $timeoutSeconds,
    ): HttpResponse {
        throw new \RuntimeException('StreamingMockTransport only replays streams.');
    }

    public function stream(
        string $method,
        string $url,
        array $headers,
        ?string $body,
        float $timeoutSeconds,
    ): StreamedHttpResponse {
        $this->requests[] = [
            'method' => $method,
            'url' => $url,
            'headers' => $headers,
            'body' => $body,
            'timeout' => $timeoutSeconds,
        ];
        if ($this->streams === []) {
            throw new \RuntimeException('StreamingMockTransport has no queued stream.');
        }
        $stream = array_shift($this->streams);

        return new StreamedHttpResponse($stream['status'], $stream['chunks']);
    }
}
