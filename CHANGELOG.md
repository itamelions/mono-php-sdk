# Changelog

All notable changes to `itamelions/mono-php` are documented here.  
This project adheres to [Semantic Versioning](https://semver.org/).

---

## [Unreleased]

---

## [1.1.0] — 2026-05-05

### Added
- `Mono` constructor now accepts a second `$options` array:
  - `timeout` (int, default 30) — total request timeout in seconds
  - `connect_timeout` (int, default 10) — TCP connection timeout in seconds
  - `max_retries` (int, default 0) — automatic retries on HTTP 429 / 503 responses,
    honouring the `Retry-After` header; falls back to exponential back-off
- `Mono::fake(string $secretKey, Client $http): static` — test factory that accepts
  a pre-built (or mocked) Guzzle client, removing the need for `ReflectionProperty`
  hacks in test suites
- `MonoRateLimitException` — thrown on HTTP 429; exposes `getRetryAfter(): ?int`
  so callers know how long to wait before the next attempt
- `MonoConnectionException` — thrown on network-level failures (DNS, timeout,
  connection refused); extends `MonoApiException` so existing catch blocks still work

### Fixed
- HTTP 5xx responses from Mono were silently swallowed (fell through to the
  `GuzzleException` handler which discarded the response body and status code).
  All `BadResponseException` subclasses — including `ServerException` — are now
  caught and converted to `MonoApiException` with the correct status code and
  Mono error message
- A non-JSON response body (HTML gateway errors, etc.) previously caused
  `json_decode` to return `null`, which was silently converted to `[]`. It now
  throws `MonoApiException("Mono API returned non-JSON response…")`

---

## [1.0.1] — 2026-05-05

### Fixed
- `Debit::fetch()` used the wrong URL path `…/debits/{reference}` (plural).
  Corrected to `…/debit/{reference}` (singular) per the Mono API docs
- `Customer::create()` docblock listed `firstName` / `lastName` as required params;
  the Mono API uses snake_case `first_name` / `last_name`
- Webhook event names throughout (`WebhookTest`, `README`) used a legacy simplified
  format (`mandate_created`, `debit_successful`). All corrected to the real dotted
  format (`events.mandates.created`, `events.mandates.debit.successful`, etc.)
- Mandate `start_date` / `end_date` examples in `README` and `MandateTest` used
  `'Y-m-d H:i:s'` format; Mono accepts `'Y-m-d'` only

### Added
- `tests/DebitTest.php` — path assertions for `charge()`, `fetch()` (singular),
  `all()` (plural), and `all()` with pagination query string
- `README`: idempotency / `event_id` deduplication section
- `README`: blockquote notes on e-mandate activation delay, kobo amounts, UTC dates
- `README`: webhook events table expanded to all 11 real Mono event names

---

## [1.0.0] — initial release

- `Mono` client with Guzzle 7 transport
- Resources: `Account`, `Bank`, `Customer`, `Debit`, `Mandate`
- `Webhook` class with HMAC-SHA512 signature verification, named and wildcard listeners
- Exceptions: `MonoApiException`, `MonoNotFoundException`
- PHPUnit 10 test suite

[Unreleased]: https://github.com/itamelions/mono-php/compare/v1.1.0...HEAD
[1.1.0]: https://github.com/itamelions/mono-php/compare/v1.0.1...v1.1.0
[1.0.1]: https://github.com/itamelions/mono-php/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/itamelions/mono-php/releases/tag/v1.0.0
