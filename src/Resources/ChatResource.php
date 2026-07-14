<?php

declare(strict_types=1);

namespace LatentKit\Resources;

use LatentKit\Support\RouteControlGuard;

final class ChatResource extends Resource
{
    /**
     * @param list<array<string, mixed>> $messages
     * @param array<string, mixed> $extraBody
     * @return array<string, mixed>
     */
    public function create(
        array $messages,
        int $maxTokens = 1000,
        float $temperature = 0.7,
        ?string $responseProfile = null,
        array $extraBody = [],
    ): array {
        $body = [
            'messages' => $messages,
            'max_tokens' => $maxTokens,
            'temperature' => $temperature,
        ];
        if ($responseProfile !== null && $responseProfile !== '') {
            $body['response_profile'] = $responseProfile;
        }

        return $this->request(
            'POST',
            '/chat',
            RouteControlGuard::mergeExtraBody($body, $extraBody, 'client.chat.create'),
        );
    }
}
