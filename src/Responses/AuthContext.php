<?php

declare(strict_types=1);

namespace LatentKit\Responses;

final class AuthContext
{
    public function __construct(
        public readonly string $credentialType,
        public readonly ?string $authProvider,
        public readonly ?string $grantScope,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            credentialType: (string) ($data['credential_type'] ?? ''),
            authProvider: self::optionalString($data['auth_provider'] ?? null),
            grantScope: self::optionalString($data['grant_scope'] ?? null),
        );
    }

    private static function optionalString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? $value : null;
    }
}
