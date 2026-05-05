# mono-php

[![Latest Version on Packagist](https://img.shields.io/packagist/v/itamelions/mono-php.svg)](https://packagist.org/packages/itamelions/mono-php)
[![PHP Version](https://img.shields.io/badge/php-%5E8.1-blue.svg)](https://www.php.net/)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)
[![CI](https://github.com/itamelions/mono-php/actions/workflows/ci.yml/badge.svg)](https://github.com/itamelions/mono-php/actions)

Unofficial community PHP SDK for the [Mono](https://mono.co) open banking API.  
Supports **Mandates**, **Debits**, **Customers**, **Accounts**, **Banks**, and **Webhooks**.  
No framework coupling — works in any PHP 8.1+ project (Laravel, Symfony, plain PHP, etc.).

---

## Requirements

- PHP 8.1 or higher
- [Composer](https://getcomposer.org/)

---

## Installation

```bash
composer require itamelions/mono-php
```

---

## Quick Start

```php
use Mono\Mono;

$mono = new Mono($_ENV['MONO_SECRET_KEY']);

// Create a customer
$customer = $mono->customer()->create([
    'email'      => 'john@example.com',
    'first_name' => 'John',
    'last_name'  => 'Doe',
    'phone'      => '+2348000000000',
    'identity'   => ['type' => 'bvn', 'number' => '12345678901'],
]);
$customerId = $customer['data']['id'];

// Initiate a hosted mandate (returns a mono_url — redirect your user there)
$initiation = $mono->mandate()->initiate([
    'amount'       => 5000000,        // amount in kobo (₦50,000)
    'type'         => 'recurring-debit',
    'method'       => 'mandate',
    'mandate_type' => 'sweep',
    'debit_type'   => 'variable',
    'reference'    => 'your-unique-ref',
    'redirect_url' => 'https://yourapp.com/mandate/callback',
    'customer'     => ['id' => $customerId],
    'start_date'   => '2026-01-01',           // Mono interprets bare dates as midnight UTC
    'end_date'     => '2031-01-01',
]);

$monoUrl = $initiation['data']['mono_url'];
// → redirect the user to $monoUrl to complete mandate authorisation
```

---

## Configuration

Set your Mono secret key as an environment variable:

```
MONO_SECRET_KEY=test_sk_xxxxxxxxxxxxxxxx
```

Then inject it:

```php
$mono = new Mono(getenv('MONO_SECRET_KEY'));
```

---

## Resource Reference

### Customer

```php
$mono->customer()->create(array $params): array
$mono->customer()->update(string $customerId, array $params): array
$mono->customer()->fetch(string $customerId): array
$mono->customer()->list(array $query = []): array   // supports: page, limit
```

### Account

```php
// Exchange a Mono Connect auth code for an account ID (call once after Connect flow)
$mono->account()->auth(string $code): array

$mono->account()->fetch(string $accountId): array
$mono->account()->transactions(string $accountId, int $limit = 100, array $query = []): array
$mono->account()->identity(string $accountId): array
$mono->account()->income(string $accountId): array
$mono->account()->unlink(string $accountId): array
```

### Mandate

```php
// Hosted mandate setup — returns mono_url to redirect user
$mono->mandate()->initiate(array $params): array

// Direct / e-mandate creation
$mono->mandate()->create(array $params): array

$mono->mandate()->fetch(string $mandateId): array
$mono->mandate()->list(array $query = []): array       // supports: page, limit
$mono->mandate()->pause(string $mandateId): array
$mono->mandate()->reinstate(string $mandateId): array
$mono->mandate()->cancel(string $mandateId): array
$mono->mandate()->balanceCheck(string $mandateId, ?int $amountInKobo = null): array
```

> **E-mandate / Sweep activation delay:** Do not call `charge()` until you receive the `events.mandates.ready` webhook. After a customer approves a sweep or e-mandate, Mono requires up to 3 hours before the mandate is ready to debit. Calling `charge()` before `ready` fires will return a Mono API error.
>
> **Amounts:** All `amount` values must be in the **lowest denomination** of the account currency — kobo for NGN (100 kobo = ₦1). Pass `500000` for ₦5,000, not `5000`.
>
> **Dates:** `start_date` and `end_date` accept `Y-m-d` strings (e.g. `'2026-01-01'`). Mono interprets them as **midnight UTC**. If your application runs in a non-UTC timezone, use UTC-based date logic to avoid off-by-one errors on the mandate start or expiry day.

### Debit

```php
// Charge a mandate (required params: amount in kobo, reference, narration)
$mono->debit()->charge(string $mandateId, array $params): array

$mono->debit()->fetch(string $mandateId, string $reference): array
$mono->debit()->all(string $mandateId, array $query = []): array
```

### Bank

```php
$mono->bank()->list(): array
```

---

## Webhook Verification

Mono signs each webhook request body with your **webhook secret** using HMAC-SHA512 and sends
the hex digest in the `mono-webhook-secret` HTTP header.

### Basic usage

```php
use Mono\Webhook;
use Mono\Exceptions\MonoApiException;

$webhook = new Webhook($_ENV['MONO_WEBHOOK_SECRET']);

$webhook->on('events.mandates.created', function (array $data) {
    // persist $data or enqueue a job
    echo "Mandate created: " . $data['id'];
});

$webhook->on('events.mandates.debit.successful', function (array $data) {
    echo "Debit succeeded: " . $data['reference_number'];
});

// Catch-all — receives every event
$webhook->on('*', function (string $event, array $data) {
    error_log("Mono event: {$event}");
});

try {
    $rawBody  = file_get_contents('php://input');
    $sigHeader = $_SERVER['HTTP_MONO_WEBHOOK_SECRET'] ?? '';

    $webhook->process($rawBody, $sigHeader);

    http_response_code(200);
} catch (MonoApiException $e) {
    http_response_code($e->getCode() ?: 400);
    echo $e->getMessage();
}
```

### Manual signature verification

```php
$isValid = $webhook->verifySignature($rawBody, $sigHeader); // bool
```

### Idempotency

Mono may retry unacknowledged webhook deliveries up to 25 times over 48 hours. Always deduplicate on `event_id` **before** calling `process()` to avoid double-processing:

```php
$rawBody   = file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_MONO_WEBHOOK_SECRET'] ?? '';

$preview = json_decode($rawBody, true);
$eventId = $preview['event_id'] ?? null;

if ($eventId && YourCache::has($eventId)) {
    http_response_code(200);
    exit; // already handled
}

$webhook->process($rawBody, $sigHeader);

if ($eventId) {
    YourCache::put($eventId, true, ttl: 86400);
}
```

### Supported webhook events (non-exhaustive)

| Event | Description |
|---|---|
| `events.mandates.created` | Mandate initiated; awaiting customer approval |
| `events.mandates.approved` | Customer approved the mandate |
| `events.mandates.ready` | Mandate ready to debit — **wait for this before calling `charge()`** |
| `events.mandates.rejected` | Mandate was rejected |
| `events.mandate.action.pause` | Mandate was paused |
| `events.mandate.action.reinstate` | Paused mandate was reinstated |
| `events.mandate.action.cancel` | Mandate was cancelled |
| `events.mandates.expired` | Mandate has passed its end date |
| `events.mandates.debit.processing` | Debit pending NIBSS confirmation |
| `events.mandates.debit.successful` | Debit succeeded |
| `events.mandates.debit.failed` | Debit failed |

---

## Error Handling

All API errors throw subclasses of `Mono\Exceptions\MonoApiException`:

| Exception | When thrown |
|---|---|
| `MonoApiException` | Any API error (4xx/5xx) or network failure |
| `MonoNotFoundException` | API returns `404 Not Found` |

```php
use Mono\Exceptions\MonoApiException;
use Mono\Exceptions\MonoNotFoundException;

try {
    $mandate = $mono->mandate()->fetch('mmc_invalid');
} catch (MonoNotFoundException $e) {
    echo "Not found: " . $e->getMessage();
} catch (MonoApiException $e) {
    echo "API error {$e->getCode()}: " . $e->getMessage();
}
```

---

## Running Tests

```bash
composer install
./vendor/bin/phpunit
```

Tests use **Mockery** to mock the Guzzle HTTP client — no live API calls are made.

---

## What This SDK Covers

| Resource | Methods |
|---|---|
| Customer | create, update, fetch, list |
| Account | auth, fetch, transactions, identity, income, unlink |
| Mandate | initiate, create, fetch, list, pause, reinstate, cancel, balanceCheck |
| Debit | charge, fetch, all |
| Bank | list |
| Webhook | process, verifySignature, on() listener |

---

## Version Roadmap

| Version | Scope |
|---|---|
| `v1.0` | Accounts, Customers, Mandates, Debits, Banks, Webhooks |
| `v1.1` | Laravel service provider + facade |
| `v1.2` | Retry middleware, configurable timeout |
| `v2.0` | Full Mono Connect (statement, income, identity) |

---

## Contributing

Contributions are welcome! Please open an issue or submit a pull request.

1. Fork the repository
2. Create a feature branch: `git checkout -b feature/my-feature`
3. Write tests for your changes
4. Ensure all tests pass: `./vendor/bin/phpunit`
5. Open a pull request

---

## License

MIT — see [LICENSE](LICENSE).

---

## Disclaimer

This is an **unofficial** community SDK. It is not maintained by or affiliated with
[Mono](https://mono.co). Refer to the [official Mono API docs](https://docs.mono.co)
for the authoritative API reference.
