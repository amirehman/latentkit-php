<?php

declare(strict_types=1);

namespace LatentKit\Responses;

final class AppContext
{
    public function __construct(
        public readonly string $id,
        public readonly string $slug,
        public readonly string $name,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) ($data['id'] ?? ''),
            slug: (string) ($data['slug'] ?? ''),
            name: (string) ($data['name'] ?? ''),
        );
    }
}
