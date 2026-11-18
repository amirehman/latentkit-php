<?php

declare(strict_types=1);

namespace LatentKit\Responses;

/**
 * What the API key can do right now, as decided by LatentKit's routing rules.
 *
 * Use this, not a route model's raw `capabilities`, to decide which operations to
 * offer: it accounts for capability override routes, adapter support, and disabled
 * connections. A null snapshot on the context means "not determined this time" or a
 * gateway that predates it, never "nothing is available".
 */
final class CapabilitySnapshot
{
    /**
     * @param array<string, array<string, mixed>> $operations Keyed by operation name:
     *     chat, embeddings, image, speech, transcription, video, decisions.
     */
    public function __construct(
        public readonly int $version,
        public readonly ?string $generatedAt,
        public readonly array $operations,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $operations = [];
        foreach (is_array($data['operations'] ?? null) ? $data['operations'] : [] as $name => $operation) {
            if (is_string($name) && is_array($operation)) {
                $operations[$name] = $operation;
            }
        }

        return new self(
            version: is_int($data['version'] ?? null) ? $data['version'] : 1,
            generatedAt: is_string($data['generated_at'] ?? null) ? $data['generated_at'] : null,
            operations: $operations,
        );
    }

    /** Whether the key's route can serve an operation such as `chat` or `embeddings`. */
    public function isAvailable(string $operation): bool
    {
        return ($this->operations[$operation]['available'] ?? false) === true;
    }

    /**
     * Whether one model on the chat route covers a chat feature: system_role, vision,
     * json_mode, json_schema, tools, tools_with_json_schema or streaming.
     */
    public function hasChatFeature(string $feature): bool
    {
        return $this->isAvailable('chat')
            && ($this->operations['chat']['features'][$feature]['available'] ?? false) === true;
    }

    /**
     * Why an operation, or a chat feature, is unavailable: a reason code such as
     * `no_eligible_model` or `connection_disabled`, or null when it is available.
     */
    public function reason(string $operation, ?string $feature = null): ?string
    {
        $block = $this->operations[$operation] ?? [];
        if ($feature !== null && ($block['available'] ?? false) === true) {
            $block = $block['features'][$feature] ?? [];
        }
        $reason = is_array($block) ? ($block['reason'] ?? null) : null;

        return is_string($reason) && $reason !== '' ? $reason : null;
    }

    /**
     * The single embedding space the route serves, or null when it has none.
     *
     * Bind a vector index to `id`: it changes whenever the provider, model, revision,
     * dimensions or settings change. Equal dimensions do not make two spaces compatible.
     *
     * @return array<string, mixed>|null
     */
    public function embeddingSpace(): ?array
    {
        $space = $this->isAvailable('embeddings') ? ($this->operations['embeddings']['space'] ?? null) : null;

        return is_array($space) ? $space : null;
    }
}
