<?php

declare(strict_types=1);

namespace LatentKit\Resources;

abstract class Resource
{
    /**
     * @param \Closure(string, string, ?array): array<string, mixed> $requestJson
     * @param (\Closure(string, string, array): \Generator)|null $requestStream
     */
    public function __construct(
        protected readonly \Closure $requestJson,
        protected readonly ?\Closure $requestStream = null,
    ) {
    }

    /**
     * @param array<string, mixed>|null $body
     * @return array<string, mixed>
     */
    protected function request(string $method, string $path, ?array $body = null): array
    {
        return ($this->requestJson)($method, $path, $body);
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return \Generator<int, array{event: string, data: array<string, mixed>}>
     */
    protected function streamRequest(string $method, string $path, array $body): \Generator
    {
        if ($this->requestStream === null) {
            throw new \LogicException(static::class . ' does not support streaming.');
        }

        return ($this->requestStream)($method, $path, $body);
    }
}
