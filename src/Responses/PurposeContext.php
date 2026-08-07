<?php

declare(strict_types=1);

namespace LatentKit\Responses;

/**
 * A purpose label this API key may send, as configured for its app.
 *
 * Read these from `/v1/me` rather than hardcoding a label: purposes are declared per
 * workspace, so a label that is right for one LatentKit account is silently wrong for
 * another. If none of them fit the work you are doing, send no `purpose` at all — that
 * runs the app's assigned route, which is always a supported outcome.
 */
final class PurposeContext
{
    public function __construct(
        public readonly string $purpose,
        /** What the workspace configured this purpose for. May be an empty string. */
        public readonly string $description,
        /** False means the binding exists but is off; requests fall back to the default. */
        public readonly bool $isEnabled,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            purpose: is_string($data['purpose'] ?? null) ? $data['purpose'] : '',
            description: is_string($data['description'] ?? null) ? $data['description'] : '',
            isEnabled: (bool) ($data['is_enabled'] ?? true),
        );
    }
}
