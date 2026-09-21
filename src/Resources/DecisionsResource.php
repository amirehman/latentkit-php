<?php

declare(strict_types=1);

namespace LatentKit\Resources;

use LatentKit\Support\RouteControlGuard;

/**
 * Typed decisions.
 *
 * Returns structured answers rather than text: read `$response['answers'][$questionId]`,
 * keyed by the ids you supplied. No streaming, no temperature, no token budget -- a
 * decision model evaluates, it does not write.
 *
 * Question shapes:
 *   ['type' => 'noul',   'instructions' => string, 'criteria' => ['true' => ..., 'false' => ...]]  // criteria optional
 *   ['type' => 'choice', 'instructions' => string, 'criteria' => [optionKey => description]]        // max 255 options
 *   ['type' => 'score',  'instructions' => string, 'criteria' => [levelDescription, ...]]           // 2-10 levels
 */
final class DecisionsResource extends Resource
{
    /**
     * @param string|array<mixed> $state
     * @param array<string, array<string, mixed>> $questions
     * @param array<string, mixed> $extraBody
     * @return array<string, mixed>
     */
    public function create(
        string|array $state,
        array $questions,
        ?string $responseProfile = null,
        ?string $purpose = null,
        array $extraBody = [],
    ): array {
        $body = [
            'state' => $state,
            'questions' => $questions,
        ];
        if ($responseProfile !== null && $responseProfile !== '') {
            $body['response_profile'] = $responseProfile;
        }
        if ($purpose !== null && $purpose !== '') {
            $body['purpose'] = $purpose;
        }

        return $this->request(
            'POST',
            '/decisions',
            RouteControlGuard::mergeExtraBody($body, $extraBody, 'client.decisions.create'),
        );
    }
}
