<?php

declare(strict_types=1);

namespace LatentKit\Resources;

use LatentKit\Support\RouteControlGuard;

final class VisionResource extends Resource
{
    /**
     * @param array<string, mixed> $extraBody
     * @return array<string, mixed>
     */
    public function create(
        string $prompt,
        ?string $imageUrl = null,
        ?string $imageBase64 = null,
        ?string $system = null,
        int $maxTokens = 1000,
        float $temperature = 0.7,
        ?string $responseProfile = null,
        array $extraBody = [],
    ): array {
        $body = [
            'prompt' => $prompt,
            'image_url' => $imageUrl,
            'image_base64' => $imageBase64,
            'system' => $system,
            'max_tokens' => $maxTokens,
            'temperature' => $temperature,
        ];
        if ($responseProfile !== null && $responseProfile !== '') {
            $body['response_profile'] = $responseProfile;
        }

        return $this->request(
            'POST',
            '/vision',
            RouteControlGuard::mergeExtraBody($body, $extraBody, 'client.vision.create'),
        );
    }
}
