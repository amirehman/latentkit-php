<?php

declare(strict_types=1);

namespace LatentKit\Responses;

final class CreditsContext
{
    public function __construct(public readonly ?float $remainingUsd)
    {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $remaining = $data['remaining_usd'] ?? null;
        return new self(remainingUsd: is_numeric($remaining) ? (float) $remaining : null);
    }
}
