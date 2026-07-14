<?php

declare(strict_types=1);

namespace LatentKit\Transport;

interface TransportInterface
{
    /**
     * @param array<string, string> $headers
     */
    public function request(
        string $method,
        string $url,
        array $headers,
        ?string $body,
        float $timeoutSeconds,
    ): HttpResponse;
}
