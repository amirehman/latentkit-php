<?php

declare(strict_types=1);

namespace LatentKit\Responses;

final class AccountContext
{
    public function __construct(public readonly string $userId)
    {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(userId: (string) ($data['user_id'] ?? ''));
    }
}
