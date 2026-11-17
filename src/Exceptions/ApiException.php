<?php

declare(strict_types=1);

namespace LatentKit\Exceptions;

class ApiException extends LatentKitException
{
    /**
     * @param array<string, mixed>|string|null $body
     * @param string|null $category Handling category from the error envelope, such as
     *     `auth`, `billing`, `quota`, `routing`, `policy`, `upstream`, or `gateway`.
     * @param bool|null $retryable Whether LatentKit says the same request can succeed
     *     later. Null when the response did not say, for example an edge rate limit.
     * @param int|null $retryAfterSeconds Seconds to wait before retrying, from the
     *     `Retry-After` header or the error detail. Every 429 carries one.
     * @param string|null $suggestedAction Machine-readable next step, when LatentKit
     *     names one (for example `attach_provider_for_capability`).
     */
    public function __construct(
        public readonly int $statusCode,
        public readonly array|string|null $body,
        public readonly ?string $apiCode = null,
        public readonly ?string $requestId = null,
        ?string $message = null,
        public readonly ?string $category = null,
        public readonly ?bool $retryable = null,
        public readonly ?int $retryAfterSeconds = null,
        public readonly ?string $suggestedAction = null,
    ) {
        parent::__construct(
            $message !== null && $message !== ''
                ? "LatentKit API error {$statusCode}: {$message}"
                : "LatentKit API error {$statusCode}",
        );
    }
}
