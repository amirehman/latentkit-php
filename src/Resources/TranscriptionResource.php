<?php

declare(strict_types=1);

namespace LatentKit\Resources;

use LatentKit\Support\RouteControlGuard;

final class TranscriptionResource extends Resource
{
    /**
     * Transcribes audio with the route's speech-to-text model.
     *
     * Pass exactly one of $audioBase64 or $audioUrl. The transcript is `content` on the
     * response; `segments`, `words`, `language` and `duration_seconds` are present when
     * the model reports them. Size limits for the connected gateway are in
     * `me->retrieveContext()->limits`.
     *
     * @param string|null $audioBase64 The audio file's bytes, base64-encoded.
     * @param string|null $audioUrl A URL the gateway is allowed to fetch.
     * @param string|null $mediaType MIME type of the audio, for example `audio/mpeg`.
     * @param string|null $filename Original file name; some providers use its extension.
     * @param string|null $language Spoken language as an ISO 639-1 code, when known.
     * @param string|null $prompt Words or context that help recognition.
     * @param array<string, mixed> $extraBody
     * @return array<string, mixed>
     */
    public function create(
        ?string $audioBase64 = null,
        ?string $audioUrl = null,
        ?string $mediaType = null,
        ?string $filename = null,
        ?string $language = null,
        ?string $prompt = null,
        ?string $responseProfile = null,
        array $extraBody = [],
        ?string $purpose = null,
    ): array {
        $hasBase64 = $audioBase64 !== null && $audioBase64 !== '';
        $hasUrl = $audioUrl !== null && $audioUrl !== '';
        if ($hasBase64 === $hasUrl) {
            throw new \InvalidArgumentException('Pass exactly one of audioBase64 or audioUrl.');
        }

        $audio = $hasBase64 ? ['base64' => $audioBase64] : ['url' => $audioUrl];
        if ($mediaType !== null && $mediaType !== '') {
            $audio['media_type'] = $mediaType;
        }
        if ($filename !== null && $filename !== '') {
            $audio['filename'] = $filename;
        }

        $body = ['audio' => $audio];
        if ($language !== null && $language !== '') {
            $body['language'] = $language;
        }
        if ($prompt !== null && $prompt !== '') {
            $body['prompt'] = $prompt;
        }
        if ($responseProfile !== null && $responseProfile !== '') {
            $body['response_profile'] = $responseProfile;
        }
        if ($purpose !== null && $purpose !== '') {
            $body['purpose'] = $purpose;
        }

        return $this->request(
            'POST',
            '/transcription',
            RouteControlGuard::mergeExtraBody($body, $extraBody, 'client.transcription.create'),
        );
    }
}
