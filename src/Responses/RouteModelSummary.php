<?php

declare(strict_types=1);

namespace LatentKit\Responses;

final class RouteModelSummary
{
    /** @param list<string> $capabilities */
    public function __construct(
        public readonly int $rank,
        public readonly bool $active,
        public readonly string $provider,
        public readonly string $model,
        public readonly string $modelId,
        public readonly array $capabilities,
        public readonly ?string $responseProfile,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $capabilities = is_array($data['capabilities'] ?? null)
            ? array_values(array_map('strval', $data['capabilities']))
            : [];
        $responseProfile = $data['response_profile'] ?? null;

        return new self(
            rank: (int) ($data['rank'] ?? 0),
            active: (bool) ($data['active'] ?? false),
            provider: (string) ($data['provider'] ?? ''),
            model: (string) ($data['model'] ?? ''),
            modelId: (string) ($data['model_id'] ?? ''),
            capabilities: $capabilities,
            responseProfile: is_string($responseProfile) && $responseProfile !== '' ? $responseProfile : null,
        );
    }
}
