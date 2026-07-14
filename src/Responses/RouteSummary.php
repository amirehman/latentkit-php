<?php

declare(strict_types=1);

namespace LatentKit\Responses;

final class RouteSummary
{
    /** @param list<RouteModelSummary> $models */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly ?string $strategy,
        public readonly ?bool $published,
        public readonly ?int $version,
        public readonly ?int $modelCount,
        public readonly array $models,
        public readonly bool $detailsAvailable,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $models = [];
        if (is_array($data['models'] ?? null)) {
            foreach ($data['models'] as $model) {
                if (is_array($model)) {
                    $models[] = RouteModelSummary::fromArray($model);
                }
            }
        }
        $strategy = $data['strategy'] ?? null;

        return new self(
            id: (string) ($data['id'] ?? ''),
            name: (string) ($data['name'] ?? ''),
            strategy: is_string($strategy) && $strategy !== '' ? $strategy : null,
            published: array_key_exists('published', $data) ? (bool) $data['published'] : null,
            version: isset($data['version']) ? (int) $data['version'] : null,
            modelCount: isset($data['model_count']) ? (int) $data['model_count'] : null,
            models: $models,
            detailsAvailable: array_key_exists('model_count', $data) && is_array($data['models'] ?? null),
        );
    }
}
