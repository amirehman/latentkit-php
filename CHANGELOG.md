# Changelog

All notable changes to the LatentKit PHP SDK will be documented in this file.

The project follows [Semantic Versioning](https://semver.org/).

## 0.1.0-beta.5 - Unreleased

Needs a gateway that reports `capability_snapshot` in `GET /v1/me` for the
snapshot fields; every other addition works with any gateway.

### Added

- `chat->stream()`: streamed chat as server-sent events. Each item is
  `['event' => string, 'data' => array]`; text arrives in `message.delta`, the
  final message and complete tool calls in `message.completed`, token counts in
  `response.usage`. The request is sent when the method is called, so a refused
  request raises there; a failure after the stream starts raises an
  `ApiException` with `statusCode` 0 during iteration.
- `tools`, `toolChoice` and `responseFormat` arguments on `chat->create()` and
  `chat->stream()`. They were reachable only through `extraBody` before.
- `speech->create()` for text to speech and `transcription->create()` for
  speech to text.
- `StreamingTransportInterface` and `StreamedHttpResponse`. `CurlTransport`
  implements streaming; a transport that does not raises a `LatentKitException`
  when asked to stream instead of buffering the whole response.
- `me->retrieveContext()` exposes `capabilitySnapshot`: what the key's route can
  serve per operation and chat feature, the effective route, the reason when
  something is unavailable, and the single embedding space when there is one.
  It is `null` when the gateway did not report it, which means unknown, not
  unavailable.
- `me->retrieveContext()` exposes `routingContexts`, the routes the key can
  reach with their purposes.

### Changed

- `CurlTransport` no longer calls `curl_close()`, which PHP 8.5 deprecates.

## 0.1.0-beta.4 - 2026-11-18

### Added

- `purpose` on `chat->create()` and `vision->create()`: a tenant-declared routing
  label, configured per app in the console, that selects which published policy
  handles the request. Composes with `responseProfile` rather than replacing it.
  Declared after `$extraBody` so existing positional calls keep working — prefer
  named arguments.
- Responses carry `lk_requested_purpose`, `lk_applied_purpose`, and
  `lk_purpose_source`. `lk_applied_purpose` is null whenever routing fell back to
  the app default, including when the requested purpose was never configured.
- `me->retrieveContext()` exposes `purposes` (label, description, enabled) so callers
  discover configured labels instead of hardcoding them.
- Transcription and translation are not part of PHP 0.1, so their `purpose` support
  lands with those resources; JavaScript and Python already have it.
- `ApiException` exposes `category`, `retryable`, `retryAfterSeconds`, and
  `suggestedAction` from the error envelope and the `Retry-After` header, so a
  caller can tell a transient failure from one that needs a configuration change.
  The SDK still does not retry on its own.
- `me->retrieveContext()` exposes `limits`: the request-size, queue-payload, and
  retention limits the connected gateway enforces.

### Changed

- Requests send `Accept: application/json` and the client deadline
  (`X-LK-Client-Timeout-Ms`, derived from `timeoutSeconds`), so LatentKit stops
  trying providers once the client has stopped waiting.
- An API key or base URL with surrounding whitespace is trimmed instead of being
  sent as a malformed header or URL.
- A route-control key under `provider_extra` (for example
  `provider_extra.model`) is rejected at the call site, in `extraBody` and in a
  queued payload. LatentKit drops such a key without an error, so the call used
  to succeed while the model was not pinned.

### Security

- Header names that are not valid HTTP tokens, and header values containing
  control characters, are rejected before a request is sent. This closes header
  injection through a custom header, tool attribution value, or API key that
  contains a line break. The rejected value is never echoed.

## 0.1.0-beta.3 - 2026-07-14

### Fixed

- Treat a returned route `models` list as available route detail even when an
  older or transitional gateway omits the redundant `model_count` field.
- Derive `RouteSummary::$modelCount` from the parsed models in that compatibility
  case instead of reporting an unavailable or zero-model route.

## 0.1.0-beta.2 - 2026-07-14

### Added

- Typed, null-safe `/v1/me` connection, route, ordered-model, credit, and
  latest-request response objects through `me->retrieveContext()`.
- Shared five-model route fixtures that verify the PHP, Python, and JavaScript
  SDKs interpret the same gateway response contract.

### Preserved

- `me->retrieve()` continues to return the original associative array for
  backward compatibility.
- Runtime SDK credentials remain read-only for routing; route changes stay in
  the LatentKit control plane.

## 0.1.0-beta.1 - 2026-07-14

### Added

- PHP 8.1+ client for LatentKit's canonical, route-based `/v1` API.
- `/v1/me`, non-streaming chat, vision, embeddings, image generation, and enqueue-only queue resources.
- Queue idempotency and the complete public queue endpoint set.
- SDK-wide rejection of caller-controlled provider, model, route, and policy fields.
- Normalized API exceptions with error codes, response bodies, and request IDs.
- Zero-configuration cURL transport and injectable PSR-18/PSR-17 transport support.
- Network-free unit tests, PHP linting, Composer validation, and PHP 8.1–8.4 CI.

### Deferred

- Streaming, completions, speech, transcription, translation, video, control-plane helpers, IDE authorization, and agent sessions are planned for later releases as integration requirements are confirmed.
