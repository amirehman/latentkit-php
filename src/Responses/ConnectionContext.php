<?php

declare(strict_types=1);

namespace LatentKit\Responses;

final class ConnectionContext
{
    public function __construct(
        public readonly ?AppContext $app,
        public readonly ?RouteSummary $route,
        public readonly ?TenantContext $tenant,
        public readonly ?AccountContext $account,
        public readonly ?AuthContext $auth,
        public readonly ?CreditsContext $credits,
        /** @var list<PurposeContext> Purpose labels configured for this app; empty when none are. */
        public readonly array $purposes,
        public readonly ?LatestRequestSummary $latestRequest,
        /** Request limits enforced by the connected gateway; null when it predates the `limits` block. */
        public readonly ?LimitsContext $limits = null,
        /**
         * What this key can do right now, per operation. Null when the gateway could not
         * determine it for this request or predates the snapshot; treat null as unknown,
         * never as "nothing is available".
         */
        public readonly ?CapabilitySnapshot $capabilitySnapshot = null,
        /**
         * The routes this key can reach, default route first: one entry per capability
         * override, each with its purposes. Empty when the gateway reports none.
         *
         * @var list<array<string, mixed>>
         */
        public readonly array $routingContexts = [],
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            app: self::object($data['app'] ?? null, AppContext::fromArray(...)),
            route: self::object($data['policy'] ?? null, RouteSummary::fromArray(...)),
            tenant: self::object($data['tenant'] ?? null, TenantContext::fromArray(...)),
            account: self::object($data['account'] ?? null, AccountContext::fromArray(...)),
            auth: self::object($data['auth'] ?? null, AuthContext::fromArray(...)),
            credits: self::object($data['credits'] ?? null, CreditsContext::fromArray(...)),
            purposes: self::objectList($data['purposes'] ?? null, PurposeContext::fromArray(...)),
            latestRequest: self::object(
                $data['latest_request'] ?? null,
                LatestRequestSummary::fromArray(...),
            ),
            limits: self::object($data['limits'] ?? null, LimitsContext::fromArray(...)),
            capabilitySnapshot: self::object($data['capability_snapshot'] ?? null, CapabilitySnapshot::fromArray(...)),
            routingContexts: array_values(array_filter(
                is_array($data['routing_contexts'] ?? null) ? $data['routing_contexts'] : [],
                'is_array',
            )),
        );
    }

    /**
     * @template T
     * @param mixed $value
     * @param callable(array<string, mixed>): T $factory
     * @return T|null
     */
    private static function object(mixed $value, callable $factory): mixed
    {
        return is_array($value) ? $factory($value) : null;
    }

    /**
     * @template T
     * @param mixed $value
     * @param callable(array<string, mixed>): T $factory
     * @return list<T>
     */
    private static function objectList(mixed $value, callable $factory): array
    {
        if (!is_array($value)) {
            return [];
        }

        $items = [];
        foreach ($value as $item) {
            if (is_array($item)) {
                $items[] = $factory($item);
            }
        }

        return $items;
    }
}
