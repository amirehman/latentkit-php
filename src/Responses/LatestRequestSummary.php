<?php

declare(strict_types=1);

namespace LatentKit\Responses;

final class LatestRequestSummary
{
    public function __construct(
        public readonly string $requestId,
        public readonly string $provider,
        public readonly string $model,
        public readonly string $status,
        public readonly string $endpoint,
        public readonly ?int $latencyMs,
        public readonly int $fallbackCount,
        public readonly ?string $policyId,
        public readonly ?string $policyVersionId,
        public readonly ?string $createdAt,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            requestId: (string) ($data['request_id'] ?? ''),
            provider: (string) ($data['provider'] ?? ''),
            model: (string) ($data['model'] ?? ''),
            status: (string) ($data['status'] ?? ''),
            endpoint: (string) ($data['endpoint'] ?? ''),
            latencyMs: isset($data['latency_ms']) ? (int) $data['latency_ms'] : null,
            fallbackCount: (int) ($data['fallback_count'] ?? 0),
            policyId: self::optionalString($data['policy_id'] ?? null),
            policyVersionId: self::optionalString($data['policy_version_id'] ?? null),
            createdAt: self::optionalString($data['created_at'] ?? null),
        );
    }

    private static function optionalString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? $value : null;
    }
}
