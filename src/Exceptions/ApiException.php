<?php

declare(strict_types=1);

namespace LatentKit\Exceptions;

class ApiException extends LatentKitException
{
    /**
     * @param array<string, mixed>|string|null $body
     */
    public function __construct(
        public readonly int $statusCode,
        public readonly array|string|null $body,
        public readonly ?string $apiCode = null,
        public readonly ?string $requestId = null,
        ?string $message = null,
    ) {
        parent::__construct(
            $message !== null && $message !== ''
                ? "LatentKit API error {$statusCode}: {$message}"
                : "LatentKit API error {$statusCode}",
        );
    }
}
