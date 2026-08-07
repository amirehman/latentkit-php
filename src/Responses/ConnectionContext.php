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
