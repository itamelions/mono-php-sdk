# mono-php

[![Latest Version on Packagist](https://img.shields.io/packagist/v/itamelions/mono-php-sdk.svg)](https://packagist.org/packages/itamelions/mono-php-sdk)
[![PHP Version](https://img.shields.io/badge/php-%5E8.1-blue.svg)](https://www.php.net/)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)
[![CI](https://github.com/itamelions/mono-php-sdk/actions/workflows/ci.yml/badge.svg)](https://github.com/itamelions/mono-php-sdk/actions)

Unofficial community PHP SDK for the [Mono](https://mono.co) open banking API.  
Supports **Payments**, **Recurring Payments / Mandates**, **Debits**, **Money Operations**, **Disbursements**, **Customers**, **Accounts**, **Banks**, **Lookup / Identity**, **WhatsApp (Owo)**, and **Webhooks**.  
No framework coupling — works in any PHP 8.1+ project (Laravel, Symfony, plain PHP, etc.).

---

## Requirements

- PHP 8.1 or higher
- [Composer](https://getcomposer.org/)

---

## Installation

```bash
composer require itamelions/mono-php-sdk
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

Resources are accessed via accessor methods on the client. Both singular and plural
aliases work: `$mono->payments()` / `$mono->payment()`, `$mono->identity()` / `$mono->lookup()`,
`$mono->transfers()` / `$mono->moneyOperations()`, `$mono->recurringPayments()`.

### Customer

```php
$mono->customer()->create(array $params): array
$mono->customer()->createIndividual(array $params): array        // alias of create()
$mono->customer()->createBusiness(array $params): array
$mono->customer()->update(string $customerId, array $params): array
$mono->customer()->fetch(string $customerId): array
$mono->customer()->list(array $query = []): array               // supports: page, limit
$mono->customer()->delete(string $customerId): array
$mono->customer()->transactions(string $customerId, array $query = []): array
$mono->customer()->linkedAccounts(string $customerId): array
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
$mono->account()->all(array $query = []): array                 // list linked accounts
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

### Payments (One-Time)

```php
// Initiate a one-time payment — defaults to type 'onetime-debit'
$mono->payments()->initiate(array $params): array
$mono->payments()->initiateOneTimePayment(array $params): array

$mono->payments()->verify(string $reference): array
$mono->payments()->getPayment(string $reference): array
$mono->payments()->list(array $query = []): array
```

### Recurring Payments

```php
// Hosted recurring-debit mandate (returns mono_url) — defaults to type 'recurring-debit'
$mono->recurringPayments()->create(array $params): array
$mono->recurringPayments()->createRecurringPayment(array $params): array

// Direct mandate creation (v3/payments/mandates)
$mono->recurringPayments()->createDirect(array $params): array

$mono->recurringPayments()->list(array $query = []): array
$mono->recurringPayments()->get(string $mandateId): array
$mono->recurringPayments()->pause(string $mandateId): array
$mono->recurringPayments()->resume(string $mandateId): array   // reinstate
$mono->recurringPayments()->cancel(string $mandateId): array
$mono->recurringPayments()->balanceCheck(string $mandateId, ?int $amountInKobo = null): array

// Charge a recurring mandate
$mono->recurringPayments()->charge(string $mandateId, array $params): array
$mono->recurringPayments()->debits(string $mandateId, array $query = []): array
```

### Money Operations (Transfers)

```php
$mono->transfers()->payouts(array $query = []): array
$mono->transfers()->payoutTransactions(string $payoutId, array $query = []): array
$mono->transfers()->refund(array $params): array
$mono->transfers()->createSubAccount(array $params): array
$mono->transfers()->subAccounts(array $query = []): array
```

### Disbursements

```php
// Source accounts
$mono->disbursements()->createSourceAccount(array $params): array
$mono->disbursements()->updateSourceAccount(string $id, array $params): array
$mono->disbursements()->deleteSourceAccount(string $id): array
$mono->disbursements()->sourceAccounts(): array
$mono->disbursements()->getSourceAccount(string $id): array

// Disbursements — createInstant()/createScheduled() default the 'type' for you
$mono->disbursements()->create(array $params): array
$mono->disbursements()->createInstant(array $params): array
$mono->disbursements()->createScheduled(array $params): array
$mono->disbursements()->list(array $query = []): array
$mono->disbursements()->listDisbursements(array $query = []): array
$mono->disbursements()->get(string $id): array
$mono->disbursements()->getDisbursement(string $id): array

// Transitions: trigger / cancel
$mono->disbursements()->trigger(string $id): array
$mono->disbursements()->retryDisbursement(string $id): array
$mono->disbursements()->cancel(string $id): array

// Distributions
$mono->disbursements()->addDistributions(string $disbursementId, array $distributions): array
$mono->disbursements()->updateDistribution(string $disbursementId, string $distId, array $params): array
$mono->disbursements()->deleteDistribution(string $disbursementId, string $distId): array
$mono->disbursements()->distributions(string $disbursementId): array
$mono->disbursements()->getDistribution(string $disbursementId, string $distId): array
```

### Bank

```php
$mono->bank()->list(): array                                     // v3/banks/list
$mono->bank()->coverage(array $query = []): array                // v3/institutions
$mono->bank()->getBankCoverage(array $query = []): array
$mono->bank()->nip(): array                                      // v3/lookup/banks
$mono->bank()->lookupAccountNumber(array $params): array         // v3/lookup/account-number
```

### Lookup / Identity

```php
$mono->identity()->lookupBVN(array $params): array               // v2/lookup/bvn/initiate
$mono->identity()->verifyBVN(array $params): array               // v2/lookup/bvn/verify
$mono->identity()->fetchBVN(array $params): array                // v2/lookup/bvn/fetch

// CAC company data
$mono->identity()->lookupCAC(array $query): array
$mono->identity()->cacShareholders(string $companyId): array
$mono->identity()->cacPSC(string $companyId): array
$mono->identity()->cacSecretary(string $companyId): array
$mono->identity()->cacDirectors(string $companyId): array
$mono->identity()->cacProfile(string $rcNumber): array
$mono->identity()->cacStatusReport(string $companyId): string    // raw PDF bytes

// Watchlist screening
$mono->identity()->watchlist(array $entry): array
$mono->identity()->watchlistBatch(array $entries): array
$mono->identity()->watchlistResult(string $id): array
$mono->identity()->watchlistAuditLog(string $id): array
$mono->identity()->watchlistReport(string $id): string           // raw PDF bytes
$mono->identity()->startMonitoring(array $entry): array
$mono->identity()->stopMonitoring(string $id): array

// Individual verifications
$mono->identity()->nin(array $params): array                     // v3/lookup/nin
$mono->identity()->pollNINJob(string $jobId): array              // v3/lookup/nin/{job}/job
$mono->identity()->verifyTIN(array $params): array               // v3/lookup/tin
$mono->identity()->verifyPassport(array $params): array          // v3/lookup/passport
$mono->identity()->verifyDriversLicense(array $params): array    // v3/lookup/driver_license
$mono->identity()->verifyAddress(array $params): array           // v3/lookup/address
$mono->identity()->lookupAccountNumber(array $params): array     // v3/lookup/account-number
$mono->identity()->verifyCreditHistory(string $provider, array $params): array
$mono->identity()->lookupMashup(array $params): array            // v3/lookup/mashup
$mono->identity()->banks(): array                                // v3/lookup/banks
```

> **Friendly aliases** (`verifyCAC`, `lookupNIN`, …) are provided on `$mono->lookup()`.

### WhatsApp (Owo)

```php
$mono->whatsapp()->userStatus(string $phone): array
$mono->whatsapp()->linkBeneficiary(array $params): array
$mono->whatsapp()->unlinkBeneficiary(array $params): array
$mono->whatsapp()->beneficiaries(): array
$mono->whatsapp()->getBeneficiary(string $id): array

// Fund requests — createOneTimeFundRequest()/createRecurringFundRequest() default 'type'
$mono->whatsapp()->createFundRequest(array $params): array
$mono->whatsapp()->createOneTimeFundRequest(array $params): array
$mono->whatsapp()->createRecurringFundRequest(array $params): array
$mono->whatsapp()->createWhatsappPayment(array $params): array   // generic alias
$mono->whatsapp()->fundRequests(): array
$mono->whatsapp()->getWhatsappPayment(string $id): array
$mono->whatsapp()->payments(string $fundRequestId): array
$mono->whatsapp()->getPayment(string $fundRequestId, string $paymentId): array
```

> **Binary responses:** `cacStatusReport()` and `watchlistReport()` return raw response
> bytes (PDF), not decoded JSON.

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
| Customer | create, createIndividual, createBusiness, update, fetch, list, delete, transactions, linkedAccounts |
| Account | auth, fetch, transactions, identity, income, unlink, all |
| Mandate | initiate, create, fetch, list, pause, reinstate, cancel, balanceCheck |
| Debit | charge, fetch, all |
| Payment | initiate, initiateOneTimePayment, verify, getPayment, list |
| Recurring Payment | create, createRecurringPayment, createDirect, list, get, pause, resume, cancel, balanceCheck, charge, debits |
| Transfer | payouts, payoutTransactions, refund, createSubAccount, subAccounts |
| Disbursement | source accounts CRUD, create, createInstant, createScheduled, list, get, trigger, retryDisbursement, cancel, distributions CRUD |
| Bank | list, coverage, getBankCoverage, nip, lookupAccountNumber |
| Lookup / Identity | BVN, CAC (incl. status report), watchlist (incl. report), NIN, TIN, passport, driver's license, address, account number, credit history, mashup, banks |
| WhatsApp (Owo) | userStatus, beneficiaries, fund requests, payments |
| Webhook | process, verifySignature, on() listener |

---

## Version Roadmap

| Version | Scope |
|---|---|
| `v1.0` | Accounts, Customers, Mandates, Debits, Banks, Webhooks |
| `v1.1` | Laravel service provider + facade |
| `v1.2` | Retry middleware, configurable timeout |
| `v2.0` | Full Mono Connect (statement, income, identity) |
| `v2.1` | Payments, Recurring Payments, Money Operations, Disbursements, Lookup/Identity, WhatsApp (Owo) |

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
