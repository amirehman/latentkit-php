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
