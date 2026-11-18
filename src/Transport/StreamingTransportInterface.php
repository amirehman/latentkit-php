<?php

declare(strict_types=1);

namespace LatentKit\Transport;

/**
 * A transport that can hand a response body over while it is still arriving.
 *
 * Streaming is optional. A transport that only implements TransportInterface still
 * serves every non-streaming call; asking it to stream raises a LatentKitException
 * instead of silently buffering the whole response.
 */
interface StreamingTransportInterface
{
    /**
     * @param array<string, string> $headers
     *
     * @throws \LatentKit\Exceptions\TransportException When the request cannot be made.
     */
    public function stream(
        string $method,
        string $url,
        array $headers,
        ?string $body,
        float $timeoutSeconds,
    ): StreamedHttpResponse;
}
