<?php

declare(strict_types=1);

namespace LatentKit\Support;

final class RouteControlGuard
{
    /** @var array<string, true> */
    private const ROUTE_CONTROL_KEYS = [
        'model' => true,
        'models' => true,
        'provider' => true,
        'providers' => true,
        'route' => true,
        'route_id' => true,
        'policy' => true,
        'policy_id' => true,
        'policy_version_id' => true,
    ];

    /**
     * @param array<string, mixed> $payload
     */
    public static function assertNoRouteControlKeys(array $payload, string $context): void
    {
        foreach (array_keys($payload) as $key) {
            if (isset(self::ROUTE_CONTROL_KEYS[$key])) {
                throw new \InvalidArgumentException(
                    "{$context} does not accept '{$key}'. LatentKit SDK requests are route-based: "
                    . "the API key's assigned route selects the provider/model.",
                );
            }
        }
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, mixed> $extraBody
     * @return array<string, mixed>
     */
    public static function mergeExtraBody(array $body, array $extraBody, string $context): array
    {
        self::assertNoRouteControlKeys($extraBody, "{$context}.extraBody");
        return array_merge($body, $extraBody);
    }
}
