<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/MockTransport.php';

use LatentKit\Exceptions\AuthenticationException;
use LatentKit\Exceptions\InvalidResponseException;
use LatentKit\Exceptions\LatentKitException;
use LatentKit\Exceptions\RateLimitException;
use LatentKit\Exceptions\ValidationException;
use LatentKit\LatentKit;
use LatentKit\Tests\MockTransport;
use LatentKit\Transport\HttpResponse;
use LatentKit\Transport\Psr18Transport;

$tests = [];

$test = static function (string $name, callable $callback) use (&$tests): void {
    $tests[$name] = $callback;
};

$assertSame = static function (mixed $expected, mixed $actual, string $message = ''): void {
    if ($expected !== $actual) {
        throw new RuntimeException(
            ($message !== '' ? $message . ': ' : '')
            . 'expected ' . var_export($expected, true) . ', got ' . var_export($actual, true),
        );
    }
};

$assertThrows = static function (string $class, callable $callback): Throwable {
    try {
        $callback();
    } catch (Throwable $exception) {
        if (!$exception instanceof $class) {
            throw new RuntimeException("Expected {$class}, got " . $exception::class, 0, $exception);
        }
        return $exception;
    }
    throw new RuntimeException("Expected {$class} to be thrown.");
};

$jsonResponse = static fn (array $body, int $status = 200, array $headers = []): HttpResponse => new HttpResponse(
    $status,
    json_encode($body, JSON_THROW_ON_ERROR),
    $headers,
);

$test('Composer dependencies load the optional PSR-18 transport when installed', static function (): void {
    if (interface_exists(\Psr\Http\Client\ClientInterface::class) && !class_exists(Psr18Transport::class)) {
        throw new RuntimeException('Psr18Transport could not be autoloaded with Composer dependencies installed.');
    }
});

$test('chat uses canonical path, defaults, and attribution headers', static function () use ($assertSame, $jsonResponse): void {
    $transport = new MockTransport();
    $transport->push($jsonResponse(['content' => 'ok']));
    $client = new LatentKit(
        apiKey: 'test-key',
        baseUrl: 'https://example.test/',
        timeoutSeconds: 30,
        transport: $transport,
        toolSlug: 'latentkit-wordpress',
        toolVersion: '0.1.0',
        appId: 'app-1',
    );

    $response = $client->chat->create([['role' => 'user', 'content' => 'hello']]);
    $request = $transport->requests[0];
    $body = json_decode((string) $request['body'], true, 512, JSON_THROW_ON_ERROR);

    $assertSame('ok', $response['content']);
    $assertSame('POST', $request['method']);
    $assertSame('https://example.test/v1/chat', $request['url']);
    $assertSame('Bearer test-key', $request['headers']['Authorization']);
    $assertSame('latentkit-wordpress', $request['headers']['X-LK-Tool-Slug']);
    $assertSame('0.1.0', $request['headers']['X-LK-Tool-Version']);
    $assertSame('app-1', $request['headers']['X-LK-App-ID']);
    $assertSame(1000, $body['max_tokens']);
    $assertSame(0.7, $body['temperature']);
    $assertSame(30.0, $request['timeout']);
});

$test('environment values are used when explicit values are absent', static function () use ($assertSame, $jsonResponse): void {
    $previousKey = getenv('LATENTKIT_API_KEY');
    $previousUrl = getenv('LATENTKIT_BASE_URL');
    putenv('LATENTKIT_API_KEY=env-key');
    putenv('LATENTKIT_BASE_URL=https://dev-api.example.test/v1');
    try {
        $transport = new MockTransport();
        $transport->push($jsonResponse(['app' => null, 'policy' => null, 'credits' => null]));
        $client = new LatentKit(transport: $transport);
        $response = $client->me->retrieve();
        $assertSame(null, $response['policy']);
        $assertSame('https://dev-api.example.test/v1/me', $transport->requests[0]['url']);
        $assertSame('Bearer env-key', $transport->requests[0]['headers']['Authorization']);
    } finally {
        $previousKey === false ? putenv('LATENTKIT_API_KEY') : putenv("LATENTKIT_API_KEY={$previousKey}");
        $previousUrl === false ? putenv('LATENTKIT_BASE_URL') : putenv("LATENTKIT_BASE_URL={$previousUrl}");
    }
});

$test('me typed context parses the shared five-model route fixture', static function () use (
    $assertSame,
    $jsonResponse
): void {
    $fixture = json_decode(
        (string) file_get_contents(__DIR__ . '/fixtures/me-context.json'),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );
    $transport = new MockTransport();
    $transport->push($jsonResponse($fixture));
    $client = new LatentKit(apiKey: 'key', transport: $transport);

    $context = $client->me->retrieveContext();

    $assertSame('WordPress Connector', $context->app?->name);
    $assertSame('Default Priority Route', $context->route?->name);
    $assertSame(true, $context->route?->detailsAvailable);
    $assertSame(5, $context->route?->modelCount);
    $assertSame(5, count($context->route?->models ?? []));
    $assertSame('Claude Haiku 4.5', $context->route?->models[4]->model);
    $assertSame('gpt-4.1-mini', $context->latestRequest?->model);
    // Purpose labels the caller may send. A disabled binding is listed, not hidden:
    // "configured but off" and "never configured" have different fixes.
    $assertSame(3, count($context->purposes));
    $assertSame('extraction', $context->purposes[0]->purpose);
    $assertSame('Pull structured fields out of uploaded documents', $context->purposes[0]->description);
    $assertSame(true, $context->purposes[0]->isEnabled);
    $assertSame(false, $context->purposes[2]->isEnabled);
});

$test('me typed context preserves nullable and legacy route states', static function () use (
    $assertSame,
    $jsonResponse
): void {
    $transport = new MockTransport();
    $transport->push($jsonResponse([
        'app' => null,
        'policy' => ['id' => 'policy-1', 'name' => 'Legacy Route'],
        'tenant' => null,
        'auth' => ['credential_type' => 'api_key'],
        'credits' => null,
        'latest_request' => null,
    ]));
    $client = new LatentKit(apiKey: 'key', transport: $transport);

    $context = $client->me->retrieveContext();

    $assertSame(null, $context->app);
    $assertSame('Legacy Route', $context->route?->name);
    $assertSame(false, $context->route?->detailsAvailable);
    $assertSame(null, $context->route?->modelCount);
    $assertSame(null, $context->credits);
    $assertSame(null, $context->latestRequest);
});

$test('me typed context derives model count when models are present', static function () use (
    $assertSame,
    $jsonResponse
): void {
    $transport = new MockTransport();
    $transport->push($jsonResponse([
        'policy' => [
            'id' => 'policy-1',
            'name' => 'Compatible Route',
            'models' => [
                [
                    'rank' => 1,
                    'active' => true,
                    'provider' => 'openai',
                    'model' => 'gpt-4.1-mini',
                    'model_id' => 'model-1',
                    'capabilities' => ['chat'],
                ],
            ],
        ],
    ]));
    $client = new LatentKit(apiKey: 'key', transport: $transport);

    $context = $client->me->retrieveContext();

    $assertSame(true, $context->route?->detailsAvailable);
    $assertSame(1, $context->route?->modelCount);
    $assertSame('gpt-4.1-mini', $context->route?->models[0]->model);
});

$test('missing key and invalid options fail before transport', static function () use ($assertThrows): void {
    $previousKey = getenv('LATENTKIT_API_KEY');
    putenv('LATENTKIT_API_KEY');
    try {
        $assertThrows(LatentKitException::class, static fn () => new LatentKit(transport: new MockTransport()));
        $assertThrows(InvalidArgumentException::class, static fn () => new LatentKit(apiKey: 'key', baseUrl: '/relative', transport: new MockTransport()));
        $assertThrows(InvalidArgumentException::class, static fn () => new LatentKit(apiKey: 'key', timeoutSeconds: 0, transport: new MockTransport()));
    } finally {
        $previousKey === false ? putenv('LATENTKIT_API_KEY') : putenv("LATENTKIT_API_KEY={$previousKey}");
    }
});

$test('route control keys are rejected before requests', static function () use ($assertThrows): void {
    $transport = new MockTransport();
    $client = new LatentKit(apiKey: 'key', transport: $transport);

    $exception = $assertThrows(
        InvalidArgumentException::class,
        static fn () => $client->chat->create(
            [['role' => 'user', 'content' => 'hello']],
            extraBody: ['model' => 'gpt-4o-mini'],
        ),
    );
    if (!str_contains($exception->getMessage(), 'route-based')) {
        throw new RuntimeException('Route-control error did not explain route-based behavior.');
    }
    if ($transport->requests !== []) {
        throw new RuntimeException('A rejected route-control request reached the transport.');
    }

    // The gateway silently drops a model placed under provider_extra, so it is
    // rejected at the call site, in a direct call and in a queued payload.
    $nested = $assertThrows(
        InvalidArgumentException::class,
        static fn () => $client->chat->create(
            [['role' => 'user', 'content' => 'hello']],
            extraBody: ['provider_extra' => ['model' => 'gpt-4o']],
        ),
    );
    if (!str_contains($nested->getMessage(), 'provider_extra')) {
        throw new RuntimeException('The provider_extra rejection did not name the container.');
    }
    $assertThrows(
        InvalidArgumentException::class,
        static fn () => $client->queue->create('chat', ['messages' => [], 'provider_extra' => ['provider' => 'openai']]),
    );
    if ($transport->requests !== []) {
        throw new RuntimeException('A rejected provider_extra request reached the transport.');
    }

    // A tool whose JSON schema has a property named "model" is ordinary data.
    $transport->push(new HttpResponse(200, '{"content":"ok"}'));
    $client->chat->create(
        [['role' => 'user', 'content' => 'hello']],
        extraBody: [
            'tools' => [[
                'type' => 'function',
                'function' => [
                    'name' => 'lookup_car',
                    'parameters' => ['type' => 'object', 'properties' => ['model' => ['type' => 'string']]],
                ],
            ]],
            'provider_extra' => ['reasoning_effort' => 'low'],
        ],
    );
    if (count($transport->requests) !== 1) {
        throw new RuntimeException('A tool schema with a "model" property must be sent.');
    }
});

$test('vision, embeddings, and image map to canonical request bodies', static function () use ($assertSame, $jsonResponse): void {
    $transport = new MockTransport();
    $transport->push($jsonResponse(['content' => 'seen']));
    $transport->push($jsonResponse(['data' => []]));
    $transport->push($jsonResponse(['data' => []]));
    $client = new LatentKit(apiKey: 'key', transport: $transport);

    $client->vision->create('Describe', imageUrl: 'https://example.test/image.png', responseProfile: 'deep');
    $client->embeddings->create(['one', 'two'], dimensions: 256);
    $client->image->generate('A logo', size: '1024x1024', n: 1);

    $assertSame(
        ['https://ai.latentkit.com/v1/vision', 'https://ai.latentkit.com/v1/embeddings', 'https://ai.latentkit.com/v1/image'],
        array_column($transport->requests, 'url'),
    );
    $vision = json_decode((string) $transport->requests[0]['body'], true, 512, JSON_THROW_ON_ERROR);
    $embeddings = json_decode((string) $transport->requests[1]['body'], true, 512, JSON_THROW_ON_ERROR);
    $image = json_decode((string) $transport->requests[2]['body'], true, 512, JSON_THROW_ON_ERROR);
    $assertSame('deep', $vision['response_profile']);
    $assertSame('https://example.test/image.png', $vision['image_url']);
    $assertSame(256, $embeddings['dimensions']);
    $assertSame(false, array_key_exists('encoding_format', $embeddings));
    $assertSame(1, $image['n']);
});

$test('queue supports canonical endpoints and idempotency but exposes no result helper', static function () use ($assertSame, $assertThrows, $jsonResponse): void {
    $transport = new MockTransport();
    $transport->push($jsonResponse(['queued' => true, 'job' => ['id' => 'job-1']]));
    $client = new LatentKit(apiKey: 'key', transport: $transport);

    $response = $client->queue->create(
        'embeddings',
        ['input' => ['hello']],
        idempotencyKey: 'job-key-1',
    );
    $body = json_decode((string) $transport->requests[0]['body'], true, 512, JSON_THROW_ON_ERROR);
    $assertSame(true, $response['queued']);
    $assertSame('embeddings', $body['endpoint']);
    $assertSame('job-key-1', $body['idempotency_key']);
    $assertSame(false, method_exists($client->queue, 'retrieve'));

    $assertThrows(InvalidArgumentException::class, static fn () => $client->queue->create('unknown', []));
    $assertThrows(InvalidArgumentException::class, static fn () => $client->queue->create('chat', ['model' => 'gpt']));
    $assertThrows(InvalidArgumentException::class, static fn () => $client->queue->create('chat', [], idempotencyKey: ''));
});

$test('API errors expose normalized class, code, body, and request id', static function () use ($assertSame, $assertThrows, $jsonResponse): void {
    $cases = [
        [401, AuthenticationException::class],
        [429, RateLimitException::class],
        [422, ValidationException::class],
    ];
    foreach ($cases as [$status, $class]) {
        $transport = new MockTransport();
        $transport->push($jsonResponse([
            'error' => [
                'code' => 'TEST_ERROR',
                'message' => 'Nope',
                'request_id' => 'req_body',
            ],
        ], $status));
        $client = new LatentKit(apiKey: 'key', transport: $transport);
        $exception = $assertThrows($class, static fn () => $client->me->retrieve());
        $assertSame($status, $exception->statusCode);
        $assertSame('TEST_ERROR', $exception->apiCode);
        $assertSame('req_body', $exception->requestId);
        $assertSame('Nope', $exception->body['error']['message']);
    }

    $transport = new MockTransport();
    $transport->push(new HttpResponse(503, 'unavailable', ['x-lk-request-id' => 'req_header']));
    $client = new LatentKit(apiKey: 'key', transport: $transport);
    $exception = $assertThrows(\LatentKit\Exceptions\ApiException::class, static fn () => $client->me->retrieve());
    $assertSame('req_header', $exception->requestId);
});

$test('invalid success JSON is rejected', static function () use ($assertThrows): void {
    $transport = new MockTransport();
    $transport->push(new HttpResponse(200, 'not-json'));
    $client = new LatentKit(apiKey: 'key', transport: $transport);
    $assertThrows(InvalidResponseException::class, static fn () => $client->me->retrieve());
});

$test('API errors expose category, retryability, retry delay, and suggested action', static function () use (
    $assertSame,
    $assertThrows,
    $jsonResponse
): void {
    $transport = new MockTransport();
    $transport->push($jsonResponse([
        'error' => [
            'code' => 'NO_HEALTHY_PROVIDER',
            'category' => 'routing',
            'message' => 'No route can serve this capability',
            'request_id' => 'req_config',
            'retryable' => false,
            'suggested_action' => 'attach_provider_for_capability',
        ],
    ], 503));
    $client = new LatentKit(apiKey: 'key', transport: $transport);
    $exception = $assertThrows(\LatentKit\Exceptions\ApiException::class, static fn () => $client->me->retrieve());
    $assertSame('routing', $exception->category);
    $assertSame(false, $exception->retryable);
    $assertSame('attach_provider_for_capability', $exception->suggestedAction);
    $assertSame(null, $exception->retryAfterSeconds);

    // Budget reserved by in-flight requests: retryable, with the delay in the header.
    $transport = new MockTransport();
    $transport->push($jsonResponse([
        'error' => ['code' => 'KEY_BUDGET_IN_FLIGHT', 'category' => 'billing', 'retryable' => true],
    ], 429, ['retry-after' => '7']));
    $client = new LatentKit(apiKey: 'key', transport: $transport);
    $exception = $assertThrows(RateLimitException::class, static fn () => $client->me->retrieve());
    $assertSame('billing', $exception->category);
    $assertSame(true, $exception->retryable);
    $assertSame(7, $exception->retryAfterSeconds);

    // The edge rate limiter answers with a bare string and no retryable flag.
    $transport = new MockTransport();
    $transport->push($jsonResponse(['error' => 'rate_limited'], 429, ['retry-after' => '60']));
    $client = new LatentKit(apiKey: 'key', transport: $transport);
    $exception = $assertThrows(RateLimitException::class, static fn () => $client->me->retrieve());
    $assertSame('rate_limited', $exception->apiCode);
    $assertSame(null, $exception->retryable);
    $assertSame(null, $exception->category);
    $assertSame(60, $exception->retryAfterSeconds);

    // Without a header, the delay in the error detail is used.
    $transport = new MockTransport();
    $transport->push($jsonResponse([
        'error' => ['code' => 'RATE_LIMITED', 'retryable' => true],
        'detail' => ['retry_after_seconds' => 12],
    ], 429));
    $client = new LatentKit(apiKey: 'key', transport: $transport);
    $exception = $assertThrows(RateLimitException::class, static fn () => $client->me->retrieve());
    $assertSame(12, $exception->retryAfterSeconds);

    // An HTTP-date Retry-After is converted to a non-negative number of seconds.
    $transport = new MockTransport();
    $transport->push($jsonResponse(
        ['error' => ['code' => 'RATE_LIMITED']],
        429,
        ['retry-after' => gmdate('D, d M Y H:i:s', time() + 120) . ' GMT'],
    ));
    $client = new LatentKit(apiKey: 'key', transport: $transport);
    $exception = $assertThrows(RateLimitException::class, static fn () => $client->me->retrieve());
    if ($exception->retryAfterSeconds === null || $exception->retryAfterSeconds < 100 || $exception->retryAfterSeconds > 120) {
        throw new RuntimeException('HTTP-date Retry-After was not converted: ' . var_export($exception->retryAfterSeconds, true));
    }

    // A non-JSON error body still yields an exception with no invented metadata.
    $transport = new MockTransport();
    $transport->push(new HttpResponse(502, '<html>bad gateway</html>'));
    $client = new LatentKit(apiKey: 'key', transport: $transport);
    $exception = $assertThrows(\LatentKit\Exceptions\ApiException::class, static fn () => $client->me->retrieve());
    $assertSame(null, $exception->retryable);
    $assertSame(null, $exception->retryAfterSeconds);
});

$test('requests declare JSON, send the client deadline, and trim pasted credentials', static function () use (
    $assertSame,
    $jsonResponse
): void {
    $transport = new MockTransport();
    $transport->push($jsonResponse(['content' => 'ok']));
    $client = new LatentKit(
        apiKey: "  lk-pasted-key\n",
        baseUrl: " https://example.test/ \n",
        timeoutSeconds: 12.5,
        headers: ['X-Trace' => 'abc'],
        transport: $transport,
    );
    $client->chat->create([['role' => 'user', 'content' => 'hello']]);

    $headers = $transport->requests[0]['headers'];
    $assertSame('Bearer lk-pasted-key', $headers['Authorization']);
    $assertSame('application/json', $headers['Accept']);
    $assertSame('12500', $headers['X-LK-Client-Timeout-Ms']);
    $assertSame('abc', $headers['X-Trace']);
    $assertSame('https://example.test/v1/chat', $transport->requests[0]['url']);
});

$test('header names and values that could split a header line are rejected', static function () use (
    $assertThrows
): void {
    $transport = new MockTransport();
    $cases = [
        ['headers' => ['X-Trace' => "abc\r\nX-LK-App-ID: other"]],
        ['headers' => ["X-Trace\r\nX-Injected" => 'abc']],
        ['headers' => ['X-Trace' => 42]],
        ['toolSlug' => "tool\nX-Injected: 1"],
        ['apiKey' => "lk-first\r\nX-Injected: 1"],
    ];
    foreach ($cases as $arguments) {
        $exception = $assertThrows(
            \InvalidArgumentException::class,
            static fn () => new LatentKit(...array_merge(['apiKey' => 'key', 'transport' => $transport], $arguments)),
        );
        if (str_contains($exception->getMessage(), 'lk-first') || str_contains($exception->getMessage(), 'X-Injected')) {
            throw new RuntimeException('The rejection message must not echo the header value.');
        }
    }
    if ($transport->requests !== []) {
        throw new RuntimeException('No request may be sent with unsafe headers.');
    }
});

$test('me typed context exposes gateway limits and tolerates their absence', static function () use (
    $assertSame,
    $jsonResponse
): void {
    $transport = new MockTransport();
    $transport->push($jsonResponse([
        'app' => ['id' => 'app-1', 'slug' => 'demo', 'name' => 'Demo'],
        'limits' => [
            'max_request_body_bytes' => 52428800,
            'max_audio_bytes' => 26214400,
            'max_audio_url_bytes' => 26214400,
            'max_audio_seconds' => null,
            'queue_max_payload_bytes' => 2097152,
            'sync_idempotency_ttl_seconds' => 86400,
            'stt_job_result_ttl_seconds' => 259200,
        ],
    ]));
    $transport->push($jsonResponse(['app' => ['id' => 'app-1', 'slug' => 'demo', 'name' => 'Demo']]));
    $client = new LatentKit(apiKey: 'key', transport: $transport);

    $limits = $client->me->retrieveContext()->limits;
    $assertSame(52428800, $limits?->maxRequestBodyBytes);
    $assertSame(26214400, $limits?->maxAudioBytes);
    $assertSame(null, $limits?->maxAudioSeconds);
    $assertSame(2097152, $limits?->queueMaxPayloadBytes);
    $assertSame(86400, $limits?->syncIdempotencyTtlSeconds);
    $assertSame(259200, $limits?->sttJobResultTtlSeconds);

    // A gateway that predates the limits block reports none rather than zeros.
    $assertSame(null, $client->me->retrieveContext()->limits);
});

$failures = 0;
foreach ($tests as $name => $callback) {
    try {
        $callback();
        fwrite(STDOUT, "PASS {$name}\n");
    } catch (Throwable $exception) {
        $failures++;
        fwrite(STDERR, "FAIL {$name}\n{$exception}\n");
    }
}

fwrite(STDOUT, sprintf("\n%d tests, %d failures\n", count($tests), $failures));
exit($failures === 0 ? 0 : 1);
