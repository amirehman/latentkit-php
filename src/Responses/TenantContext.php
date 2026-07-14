<?php

declare(strict_types=1);

namespace LatentKit\Responses;

final class TenantContext
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) ($data['id'] ?? ''),
            name: (string) ($data['name'] ?? ''),
        );
    }
}
