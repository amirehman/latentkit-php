<?php

declare(strict_types=1);

namespace LatentKit\Resources;

use LatentKit\Support\RouteControlGuard;

final class ImageResource extends Resource
{
    /**
     * @param string|array<string, mixed>|null $responseFormat
     * @param array<string, mixed> $extraBody
     * @return array<string, mixed>
     */
    public function generate(
        string $prompt,
        ?string $size = null,
        ?string $quality = null,
        ?string $style = null,
        ?int $n = null,
        string|array|null $responseFormat = null,
        ?string $responseProfile = null,
        array $extraBody = [],
    ): array {
        $body = ['prompt' => $prompt];
        if ($size !== null) {
            $body['size'] = $size;
        }
        if ($quality !== null) {
            $body['quality'] = $quality;
        }
        if ($style !== null) {
            $body['style'] = $style;
        }
        if ($n !== null) {
            $body['n'] = $n;
        }
        if ($responseFormat !== null) {
            $body['response_format'] = $responseFormat;
        }
        if ($responseProfile !== null && $responseProfile !== '') {
            $body['response_profile'] = $responseProfile;
        }

        return $this->request(
            'POST',
            '/image',
            RouteControlGuard::mergeExtraBody($body, $extraBody, 'client.image.generate'),
        );
    }
}
