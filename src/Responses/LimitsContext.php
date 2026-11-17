<?php

declare(strict_types=1);

namespace LatentKit\Responses;

/**
 * Request limits the connected gateway enforces, as reported by `/v1/me`.
 *
 * Read these instead of hardcoding sizes: they come from the gateway's own settings,
 * so a self-hosted or reconfigured gateway reports different numbers. A null value
 * means the gateway did not report that limit, or that it is unlimited.
 */
final class LimitsContext
{
    public function __construct(
        public readonly ?int $maxRequestBodyBytes = null,
        public readonly ?int $maxAudioBytes = null,
        public readonly ?int $maxAudioUrlBytes = null,
        public readonly ?int $maxAudioSeconds = null,
        public readonly ?int $queueMaxPayloadBytes = null,
        public readonly ?int $syncIdempotencyTtlSeconds = null,
        public readonly ?int $sttJobResultTtlSeconds = null,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $integer = static fn (string $key): ?int => is_numeric($data[$key] ?? null) ? (int) $data[$key] : null;

        return new self(
            maxRequestBodyBytes: $integer('max_request_body_bytes'),
            maxAudioBytes: $integer('max_audio_bytes'),
            maxAudioUrlBytes: $integer('max_audio_url_bytes'),
            maxAudioSeconds: $integer('max_audio_seconds'),
            queueMaxPayloadBytes: $integer('queue_max_payload_bytes'),
            syncIdempotencyTtlSeconds: $integer('sync_idempotency_ttl_seconds'),
            sttJobResultTtlSeconds: $integer('stt_job_result_ttl_seconds'),
        );
    }
}
