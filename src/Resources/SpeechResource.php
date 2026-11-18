<?php

declare(strict_types=1);

namespace LatentKit\Resources;

use LatentKit\Support\RouteControlGuard;

final class SpeechResource extends Resource
{
    /**
     * Turns text into speech with the route's speech model.
     *
     * The audio comes back base64-encoded in `content_blocks`, in a block of type
     * `output_audio` with its `media_type`.
     *
     * @param string|null $voice A voice the route's provider offers.
     * @param string|null $responseFormat Audio format hint, for example `mp3`.
     * @param array<string, mixed> $extraBody
     * @return array<string, mixed>
     */
    public function create(
        string $input,
        ?string $voice = null,
        ?string $responseFormat = null,
        ?float $speed = null,
        ?string $responseProfile = null,
        array $extraBody = [],
        ?string $purpose = null,
    ): array {
        $body = ['input' => $input];
        if ($voice !== null && $voice !== '') {
            $body['voice'] = $voice;
        }
        if ($responseFormat !== null && $responseFormat !== '') {
            $body['response_format'] = $responseFormat;
        }
        if ($speed !== null) {
            $body['speed'] = $speed;
        }
        if ($responseProfile !== null && $responseProfile !== '') {
            $body['response_profile'] = $responseProfile;
        }
        if ($purpose !== null && $purpose !== '') {
            $body['purpose'] = $purpose;
        }

        return $this->request(
            'POST',
            '/speech',
            RouteControlGuard::mergeExtraBody($body, $extraBody, 'client.speech.create'),
        );
    }
}
