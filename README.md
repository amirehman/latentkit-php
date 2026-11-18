# LatentKit PHP SDK

Official PHP 8.1+ client for LatentKit's canonical, route-based `/v1` API.

The application sends the task. The API key's assigned published route selects the provider and model. Do not send `model`, `provider`, `route`, or `policy`; the SDK rejects those route-control fields before making a request.

## Install

Install the current beta from [Packagist](https://packagist.org/packages/latentkit/latentkit-php):

```bash
composer require latentkit/latentkit-php:0.1.0-beta.4
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
export LATENTKIT_API_KEY="lk-..."
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
- `headers` adds or overrides request headers. A header name or value containing
  control characters is rejected before any request is sent.
- `timeoutSeconds` is also sent to LatentKit as the client deadline, so the gateway
  stops trying providers once this client has stopped waiting.
- A key or base URL pasted with surrounding whitespace is trimmed.
- `transport` accepts any `TransportInterface` implementation.
- `toolSlug` and `toolVersion` set LatentKit tool-attribution headers.
- `appId` is only for a credential that supports explicit app context.

Keep the client and API key in server-side PHP. Never render the raw key into HTML or JavaScript.

## Inspect the connected route

Use the typed context when an integration needs connection, route, model, or
recent request status:

```php
$context = $client->me->retrieveContext();

echo $context->app?->name;
echo $context->route?->name;
echo $context->route?->modelCount;

foreach ($context->route?->models ?? [] as $model) {
    echo $model->rank . ': ' . $model->provider . ' / ' . $model->model;
}

echo $context->latestRequest?->model;
```

The latest request reports the model that won that request. It is not a fixed
model: the assigned route can choose another eligible model or use a fallback
on the next call. Runtime SDK credentials cannot change the route.

When a compatible gateway returns the ordered `models` list without a separate
`model_count`, the typed context derives the count from the returned models.
This avoids presenting a populated route as unavailable or empty.

`$context->limits` reports the request limits the connected gateway enforces
(`maxRequestBodyBytes`, `maxAudioBytes`, `queueMaxPayloadBytes`, and the
idempotency and job-result lifetimes). Read them instead of hardcoding sizes;
it is `null` when the gateway does not report limits.

`$client->me->retrieve()` remains available when raw associative arrays are
preferred.

## Supported resources in 0.1

```php
$client->me->retrieveContext();

$client->chat->create(
    messages: [['role' => 'user', 'content' => 'Hello']],
);

// A purpose selects which published route handles the request. Read the labels your
// workspace configured instead of hardcoding one; unknown labels fall back to the app's
// default route rather than failing, so check `lk_applied_purpose` on the response.
foreach ($client->me->retrieveContext()->purposes as $purpose) {
    echo $purpose->purpose, ' - ', $purpose->description, PHP_EOL;
}

$client->chat->create(
    messages: [['role' => 'user', 'content' => 'Extract the invoice total from this text.']],
    responseProfile: 'deep',
    purpose: 'extraction',
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
        'category' => $error->category,
        'request_id' => $error->requestId,
    ]));

    if ($error->retryable === true) {
        // LatentKit says the same request can succeed later. Every 429 carries a
        // delay; other retryable errors may not, so choose your own backoff then.
        $waitSeconds = $error->retryAfterSeconds ?? 2;
    }
}
```

`AuthenticationException`, `RateLimitException`, and `ValidationException` extend `ApiException`. Network/client failures throw `TransportException`; malformed successful API responses throw `InvalidResponseException`.

`ApiException` also exposes what LatentKit says about the failure:

| Property | Meaning |
|---|---|
| `apiCode` | Stable error code, such as `KEY_DISABLED`, `INSUFFICIENT_CREDITS`, or `RATE_LIMITED` |
| `category` | Handling category: `auth`, `billing`, `quota`, `routing`, `policy`, `upstream`, or `gateway` |
| `retryable` | `true` when the same request can succeed later, `false` when something must change first, `null` when the response did not say |
| `retryAfterSeconds` | Seconds to wait, from the `Retry-After` header or the error detail |
| `suggestedAction` | Machine-readable next step when LatentKit names one |

The SDK does not retry on its own. `apiCode` is safe to show an operator; the
message text can include upstream provider output, so log it rather than
rendering it.

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
