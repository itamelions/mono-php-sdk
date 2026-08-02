# Changelog

All notable changes to `itamelions/mono-php-sdk` are documented here.  
This project adheres to [Semantic Versioning](https://semver.org/).

---

## [Unreleased]

### Added
- New resource accessors on `Mono`: `payments()`, `recurringPayments()`, `transfers()` /
  `moneyOperations()`, `disbursements()`, `lookup()` / `identity()`, `whatsapp()`,
  `customers()` / `customer()`, `accounts()` / `account()`, `banks()` / `bank()`, `webhooks()`
- `Mono` constructor now accepts a `webhook_secret` option (used by the `webhooks()` helper)
- `Mono::callRaw()` — performs a request and returns the raw response body (used for
  binary endpoints such as CAC status reports and watchlist reports)
- `Payments\Payment` — one-time payments: `initiate()`, `initiateOneTimePayment()`,
  `verify()`, `getPayment()`, `list()` (`v2/payments/initiate`, `v2/payments/verify/{reference}`,
  `v2/payments/transactions`)
- `Payments\RecurringPayment` — `create()` (hosted, defaults to `recurring-debit`),
  `createDirect()`, `list()`, `get()`, `pause()`, `resume()` (reinstate), `cancel()`,
  `balanceCheck()`, `charge()`, `debits()` (`v3/payments/mandates`)
- `Payments\Transfer` — money operations: `payouts()`, `payoutTransactions()`, `refund()`,
  `createSubAccount()`, `subAccounts()` (`v2/payments/payouts`, `v2/payments/refund`,
  `v2/payments/payout/sub-account`)
- `Payments\Disbursement` — source-account CRUD, `create()` / `createInstant()` /
  `createScheduled()`, `list()` / `get()`, `trigger()` / `retryDisbursement()` / `cancel()`
  (transition), and distribution CRUD (`v3/payments/disburse/...`)
- `Payments\Lookup` — BVN (initiate/verify/fetch), CAC company lookup with
  shareholders/PSC/secretary/directors/profile/status-report, watchlist screening with
  batch, audit-log and report endpoints, NIN (+ job polling), TIN, passport, driver's
  license, address, account number, credit history, mashup and bank list
- `Payments\WhatsAppPayment` — Owo payments: `userStatus()`, beneficiary link/unlink,
  beneficiary list/get, fund-request creation (one-time / recurring), fund-request
  list/get and payments (`owo/v1/...`)
- `Resources\Customer` — `createIndividual()`, `createBusiness()`, `delete()`,
  `transactions()`, `linkedAccounts()`
- `Resources\Account::all()` — list linked accounts (`v2/accounts`)
- `Resources\Bank` — `coverage()` / `getBankCoverage()` (`v3/institutions`), `nip()`
  (`v3/lookup/banks`), `lookupAccountNumber()` (`v3/lookup/account-number`)
- Tests for all new resources: `PaymentTest`, `RecurringPaymentTest`, `TransferTest`,
  `DisbursementTest`, `LookupTest`, `WhatsAppPaymentTest`, expanded `CustomerTest`,
  `BankTest`

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

[Unreleased]: https://github.com/itamelions/mono-php-sdk/compare/v1.2.0...HEAD
[1.2.0]: https://github.com/itamelions/mono-php-sdk/compare/v1.1.0...v1.2.0
[1.1.0]: https://github.com/itamelions/mono-php-sdk/compare/v1.0.1...v1.1.0
[1.0.1]: https://github.com/itamelions/mono-php-sdk/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/itamelions/mono-php-sdk/releases/tag/v1.0.0
