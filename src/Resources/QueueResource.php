<?php

declare(strict_types=1);

namespace LatentKit\Resources;

use LatentKit\Support\RouteControlGuard;

final class QueueResource extends Resource
{
    /** @var array<string, true> */
    private const ENDPOINTS = [
        'chat' => true,
        'complete' => true,
        'vision' => true,
        'embed' => true,
        'embeddings' => true,
        'image' => true,
        'speech' => true,
        'transcription' => true,
        'translation' => true,
        'audio' => true,
        'video' => true,
    ];

    /**
     * Enqueue-only API. LatentKit does not currently expose a public result endpoint.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function create(
        string $endpoint,
        array $payload,
        string $mode = 'priority',
        ?string $idempotencyKey = null,
    ): array {
        if (!isset(self::ENDPOINTS[$endpoint])) {
            throw new \InvalidArgumentException("Unsupported queue endpoint '{$endpoint}'.");
        }
        RouteControlGuard::assertNoRouteControlKeys($payload, 'client.queue.create.payload');
        if ($idempotencyKey !== null && (strlen($idempotencyKey) < 1 || strlen($idempotencyKey) > 256)) {
            throw new \InvalidArgumentException('idempotencyKey must be between 1 and 256 characters.');
        }

        $body = [
            'endpoint' => $endpoint,
            'payload' => $payload,
            'mode' => $mode,
        ];
        if ($idempotencyKey !== null) {
            $body['idempotency_key'] = $idempotencyKey;
        }

        return $this->request('POST', '/queue', $body);
    }
}
