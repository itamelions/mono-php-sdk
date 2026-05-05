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
    'email'     => 'john@example.com',
    'firstName' => 'John',
    'lastName'  => 'Doe',
    'phone'     => '+2348000000000',
    'identity'  => ['type' => 'bvn', 'number' => '12345678901'],
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
    'start_date'   => '2026-01-01 00:00:00',
    'end_date'     => '2031-01-01 00:00:00',
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

$webhook->on('mandate_created', function (array $data) {
    // persist $data or enqueue a job
    echo "Mandate created: " . $data['id'];
});

$webhook->on('debit_successful', function (array $data) {
    echo "Debit succeeded: " . $data['reference'];
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

### Supported webhook events (non-exhaustive)

| Event | Description |
|---|---|
| `mandate_created` | A new mandate has been authorised |
| `mandate_paused` | A mandate was paused |
| `mandate_reinstated` | A paused mandate was reinstated |
| `mandate_cancelled` | A mandate was cancelled |
| `debit_successful` | A debit transaction succeeded |
| `debit_failed` | A debit transaction failed |

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
