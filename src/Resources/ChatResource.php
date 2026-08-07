<?php

declare(strict_types=1);

namespace LatentKit\Resources;

use LatentKit\Support\RouteControlGuard;

final class ChatResource extends Resource
{
    /**
     * @param list<array<string, mixed>> $messages
     * @param array<string, mixed> $extraBody
     * @param string|null $purpose Tenant-declared routing label configured per app in
     *     the LatentKit console. Selects which published policy handles the request;
     *     composes with $responseProfile, which selects among that policy's routes.
     *     Null (the default) runs the app's assigned policy. A purpose that is not
     *     configured falls back to that same default rather than failing — read
     *     `lk_applied_purpose` on the response to tell the two apart.
     *
     *     Declared after $extraBody so existing positional calls keep working; prefer
     *     named arguments.
     * @return array<string, mixed>
     */
    public function create(
        array $messages,
        int $maxTokens = 1000,
        float $temperature = 0.7,
        ?string $responseProfile = null,
        array $extraBody = [],
        ?string $purpose = null,
    ): array {
        $body = [
            'messages' => $messages,
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
            '/chat',
            RouteControlGuard::mergeExtraBody($body, $extraBody, 'client.chat.create'),
        );
    }
}
