<?php

declare(strict_types=1);

namespace LatentKit\Resources;

use LatentKit\Support\RouteControlGuard;

final class EmbeddingsResource extends Resource
{
    /**
     * @param string|list<string> $input
     * @param array<string, mixed> $extraBody
     * @return array<string, mixed>
     */
    public function create(
        string|array $input,
        ?string $encodingFormat = null,
        ?int $dimensions = null,
        ?string $user = null,
        ?string $responseProfile = null,
        array $extraBody = [],
    ): array {
        $body = ['input' => $input];
        if ($encodingFormat !== null) {
            $body['encoding_format'] = $encodingFormat;
        }
        if ($dimensions !== null) {
            $body['dimensions'] = $dimensions;
        }
        if ($user !== null) {
            $body['user'] = $user;
        }
        if ($responseProfile !== null && $responseProfile !== '') {
            $body['response_profile'] = $responseProfile;
        }

        return $this->request(
            'POST',
            '/embeddings',
            RouteControlGuard::mergeExtraBody($body, $extraBody, 'client.embeddings.create'),
        );
    }
}
