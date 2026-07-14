# LatentKit PHP SDK

Official PHP 8.1+ client for LatentKit's canonical, route-based `/v1` API.

The application sends the task. The API key's assigned published route selects the provider and model. Do not send `model`, `provider`, `route`, or `policy`; the SDK rejects those route-control fields before making a request.

## Install

After the first tagged Packagist release:

```bash
composer require latentkit/latentkit-php
```

For repository development, run `composer install` inside `sdk/php` and load `vendor/autoload.php`.

## Quickstart

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use LatentKit\LatentKit;

$client = new LatentKit(); // Reads LATENTKIT_API_KEY and LATENTKIT_BASE_URL.

$response = $client->chat->create(
    messages: [
        ['role' => 'user', 'content' => 'Say hello from LatentKit.'],
    ],
    maxTokens: 100,
    responseProfile: 'balanced',
);

echo $response['content'];
```

Set the key server-side:

```bash
export LATENTKIT_API_KEY="lk_..."
```

## Client options

```php
$client = new LatentKit(
    apiKey: $_ENV['LATENTKIT_API_KEY'],
    baseUrl: 'https://ai.latentkit.com',
    timeoutSeconds: 120,
    toolSlug: 'latentkit-wordpress',
    toolVersion: '0.1.0',
    appId: null,
);
```

- `apiKey` falls back to `LATENTKIT_API_KEY`.
- `baseUrl` falls back to `LATENTKIT_BASE_URL`, then `https://ai.latentkit.com`, and is normalized to `/v1`.
- `headers` adds or overrides request headers.
- `transport` accepts any `TransportInterface` implementation.
- `toolSlug` and `toolVersion` set LatentKit tool-attribution headers.
- `appId` is only for a credential that supports explicit app context.

Keep the client and API key in server-side PHP. Never render the raw key into HTML or JavaScript.

## Supported resources in 0.1

```php
$client->me->retrieve();

$client->chat->create(
    messages: [['role' => 'user', 'content' => 'Hello']],
);

$client->vision->create(
    prompt: 'Describe this image.',
    imageUrl: 'https://example.com/image.png',
);

$client->embeddings->create(
    input: ['first document', 'second document'],
    dimensions: 256,
);

$client->image->generate(
    prompt: 'A clean product icon',
    size: '1024x1024',
);
```

Chat streaming, completions, speech, transcription, translation, video, control-plane helpers, IDE auth, and agent sessions are not part of PHP 0.1. JavaScript and Python remain the full-surface SDKs while PHP parity expands.

## Queue is enqueue-only

```php
$queued = $client->queue->create(
    endpoint: 'chat',
    payload: [
        'messages' => [['role' => 'user', 'content' => 'Summarize this.']],
    ],
    idempotencyKey: 'cms-job-42',
);
```

LatentKit currently exposes `POST /v1/queue` but no public per-job result endpoint. The PHP SDK deliberately has no `queue->retrieve()` method. WordPress, Drupal, Laravel, and commerce plugins that need a result must run their own background job and call the synchronous SDK method from that job.

## Errors and request IDs

```php
use LatentKit\Exceptions\ApiException;
use LatentKit\Exceptions\AuthenticationException;
use LatentKit\Exceptions\RateLimitException;
use LatentKit\Exceptions\ValidationException;

try {
    $response = $client->me->retrieve();
} catch (ApiException $error) {
    error_log(json_encode([
        'status' => $error->statusCode,
        'code' => $error->apiCode,
        'request_id' => $error->requestId,
    ]));
}
```

`AuthenticationException`, `RateLimitException`, and `ValidationException` extend `ApiException`. Network/client failures throw `TransportException`; malformed successful API responses throw `InvalidResponseException`.

## HTTP transports

The default `CurlTransport` has no concrete HTTP-library dependency and requires `ext-curl`.

Framework applications may inject an existing PSR-18 client plus PSR-17 request/stream factories:

```php
use LatentKit\LatentKit;
use LatentKit\Transport\Psr18Transport;

$transport = new Psr18Transport(
    client: $psr18Client,
    requestFactory: $requestFactory,
    streamFactory: $streamFactory,
);

$client = new LatentKit(
    apiKey: $_ENV['LATENTKIT_API_KEY'],
    transport: $transport,
);
```

Configure timeouts on the injected PSR-18 client. PSR-18 does not standardize timeout configuration or streaming.

## Development

```bash
composer validate --strict
composer check
```

The tests use an in-memory transport and never contact LatentKit.
