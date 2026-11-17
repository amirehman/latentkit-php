# Changelog

All notable changes to the LatentKit PHP SDK will be documented in this file.

The project follows [Semantic Versioning](https://semver.org/).

## Unreleased

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
