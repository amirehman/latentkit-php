<?php

declare(strict_types=1);

namespace LatentKit\Resources;

abstract class Resource
{
    /** @param \Closure(string, string, ?array): array<string, mixed> $requestJson */
    public function __construct(protected readonly \Closure $requestJson)
    {
    }

    /**
     * @param array<string, mixed>|null $body
     * @return array<string, mixed>
     */
    protected function request(string $method, string $path, ?array $body = null): array
    {
        return ($this->requestJson)($method, $path, $body);
    }
}
