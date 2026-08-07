<?php

declare(strict_types=1);

namespace LatentKit\Resources;

use LatentKit\Support\RouteControlGuard;

final class VisionResource extends Resource
{
    /**
     * @param array<string, mixed> $extraBody
     * @param string|null $purpose Tenant-declared routing label: which published policy
     *     handles this request. Null runs the app's assigned policy, and so does a
     *     purpose that is not configured — read `lk_applied_purpose` on the response to
     *     tell the two apart. Declared after $extraBody so existing positional calls
     *     keep working; prefer named arguments.
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
        ?string $purpose = null,
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
        if ($purpose !== null && $purpose !== '') {
            $body['purpose'] = $purpose;
        }

        return $this->request(
            'POST',
            '/vision',
            RouteControlGuard::mergeExtraBody($body, $extraBody, 'client.vision.create'),
        );
    }
}
