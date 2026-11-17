<?php

declare(strict_types=1);

namespace LatentKit\Support;

final class RouteControlGuard
{
    /**
     * Keys that would let a caller pick the model itself, rejected client-side so the
     * mistake surfaces at the call site.
     *
     * `purpose` is deliberately NOT in this set: it is the supported way to send
     * different kinds of work to different routes. It names a label the tenant
     * configured in the console, never a model, provider, or policy.
     *
     * @var array<string, true>
     */
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

        // `provider_extra` carries provider-native parameters, and the gateway drops a
        // model or provider placed there without saying so. Reject it here so a caller
        // does not believe a model is pinned when it is not. Only this one container is
        // checked: recursing into everything would reject a tool schema that merely has
        // a property named "model".
        $providerExtra = $payload['provider_extra'] ?? null;
        if (is_array($providerExtra)) {
            foreach (array_keys($providerExtra) as $key) {
                if (isset(self::ROUTE_CONTROL_KEYS[$key])) {
                    throw new \InvalidArgumentException(
                        "{$context}.provider_extra does not accept '{$key}'. LatentKit SDK requests are "
                        . "route-based: the API key's assigned route selects the provider/model.",
                    );
                }
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
