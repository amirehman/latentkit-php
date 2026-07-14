# Changelog

All notable changes to the LatentKit PHP SDK will be documented in this file.

The project follows [Semantic Versioning](https://semver.org/).

## Unreleased

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
