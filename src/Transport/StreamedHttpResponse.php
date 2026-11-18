<?php

declare(strict_types=1);

namespace LatentKit\Transport;

/**
 * A response whose body arrives in pieces.
 *
 * The status code and headers are known before the first chunk, so a caller can tell
 * an error response from a stream without waiting for the body to finish.
 */
final class StreamedHttpResponse
{
    /**
     * @param iterable<string> $chunks Raw body bytes, in arrival order.
     * @param array<string, string> $headers Lower-cased header names.
     */
    public function __construct(
        public readonly int $statusCode,
        public readonly iterable $chunks,
        public readonly array $headers = [],
    ) {
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }
}
