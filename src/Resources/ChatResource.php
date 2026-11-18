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
     * @param list<array<string, mixed>>|null $tools Tool definitions in the OpenAI
     *     function format. The route needs a model with function calling; read tool
     *     calls from `tool_calls` on the response.
     * @param array<string, mixed>|string|null $toolChoice
     * @param array<string, mixed>|null $responseFormat `['type' => 'json_schema', ...]`
     *     needs a route model that enforces schemas; `['type' => 'json_object']` needs
     *     JSON mode. `GET /v1/me` reports which the key's route can serve.
     * @return array<string, mixed>
     */
    public function create(
        array $messages,
        int $maxTokens = 1000,
        float $temperature = 0.7,
        ?string $responseProfile = null,
        array $extraBody = [],
        ?string $purpose = null,
        ?array $tools = null,
        array|string|null $toolChoice = null,
        ?array $responseFormat = null,
    ): array {
        return $this->request(
            'POST',
            '/chat',
            RouteControlGuard::mergeExtraBody(
                self::body($messages, $maxTokens, $temperature, $responseProfile, $purpose, $tools, $toolChoice, $responseFormat),
                $extraBody,
                'client.chat.create',
            ),
        );
    }

    /**
     * Streams a chat response as server-sent events.
     *
     * Each item is `['event' => string, 'data' => array]`. Text arrives in
     * `message.delta` (`data['delta']`), tool calls in `tool_call.delta`, the final
     * message in `message.completed`, and token counts in `response.usage`. The
     * request is sent when this method is called, so a refused request raises here;
     * a failure after the stream starts raises an ApiException during iteration.
     *
     * Streaming needs a transport that implements StreamingTransportInterface, which
     * the default CurlTransport does.
     *
     * @param list<array<string, mixed>> $messages
     * @param array<string, mixed> $extraBody
     * @param list<array<string, mixed>>|null $tools
     * @param array<string, mixed>|string|null $toolChoice
     * @param array<string, mixed>|null $responseFormat
     *
     * @return \Generator<int, array{event: string, data: array<string, mixed>}>
     */
    public function stream(
        array $messages,
        int $maxTokens = 1000,
        float $temperature = 0.7,
        ?string $responseProfile = null,
        array $extraBody = [],
        ?string $purpose = null,
        ?array $tools = null,
        array|string|null $toolChoice = null,
        ?array $responseFormat = null,
    ): \Generator {
        $body = RouteControlGuard::mergeExtraBody(
            self::body($messages, $maxTokens, $temperature, $responseProfile, $purpose, $tools, $toolChoice, $responseFormat),
            $extraBody,
            'client.chat.stream',
        );
        $body['stream'] = true;

        return $this->streamRequest('POST', '/chat', $body);
    }

    /**
     * @param list<array<string, mixed>> $messages
     * @param list<array<string, mixed>>|null $tools
     * @param array<string, mixed>|string|null $toolChoice
     * @param array<string, mixed>|null $responseFormat
     *
     * @return array<string, mixed>
     */
    private static function body(
        array $messages,
        int $maxTokens,
        float $temperature,
        ?string $responseProfile,
        ?string $purpose,
        ?array $tools,
        array|string|null $toolChoice,
        ?array $responseFormat,
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
        if ($tools !== null && $tools !== []) {
            $body['tools'] = $tools;
        }
        if ($toolChoice !== null && $toolChoice !== '' && $toolChoice !== []) {
            $body['tool_choice'] = $toolChoice;
        }
        if ($responseFormat !== null && $responseFormat !== []) {
            $body['response_format'] = $responseFormat;
        }

        return $body;
    }
}
