<?php

namespace Mono\Resources;

use Mono\Mono;

/**
 * Recurring payments (Mono Direct Debit).
 *
 * Friendly, discoverable wrappers around the mandate + debit endpoints.
 * A recurring payment is modelled as a mandate: initiate a hosted
 * authorisation (mono_url) or create a direct e-mandate, then charge it.
 */
class RecurringPayment
{
    public function __construct(protected Mono $client) {}

    /**
     * Initiate a hosted mandate authorisation. Returns a mono_url to
     * redirect the customer to.
     *
     * Sets type to 'recurring-debit' unless one is already provided.
     *
     * @param array $params amount, method, mandate_type ('emandate'|'sweep'),
     *                      debit_type ('variable'|'fixed'), reference,
     *                      redirect_url, customer, start_date, end_date
     */
    public function create(array $params): array
    {
        $params['type'] = $params['type'] ?? 'recurring-debit';
        return $this->client->call('POST', 'v2/payments/initiate', $params);
    }

    /**
     * Initiate a hosted mandate authorisation (alias of create()).
     */
    public function createRecurringPayment(array $params): array
    {
        return $this->create($params);
    }

    /**
     * Create a direct / e-mandate without a hosted page.
     * Requires the customer BVN / OTP flow.
     */
    public function createDirect(array $params): array
    {
        return $this->client->call('POST', 'v3/payments/mandates', $params);
    }

    /**
     * List all recurring payments (mandates).
     *
     * @param array $query Optional pagination: page, limit
     */
    public function list(array $query = []): array
    {
        $path = 'v3/payments/mandates';
        if ($query !== []) {
            $path .= '?' . http_build_query($query);
        }
        return $this->client->call('GET', $path);
    }

    /**
     * List all recurring payments (alias of list()).
     */
    public function listRecurringPayments(array $query = []): array
    {
        return $this->list($query);
    }

    /**
     * Fetch a single recurring payment (mandate) by ID.
     */
    public function get(string $mandateId): array
    {
        return $this->client->call('GET', "v3/payments/mandates/{$mandateId}");
    }

    /**
     * Fetch a single recurring payment (alias of get()).
     */
    public function getRecurringPayment(string $mandateId): array
    {
        return $this->get($mandateId);
    }

    /**
     * Pause an active recurring payment (prevents future debits).
     */
    public function pause(string $mandateId): array
    {
        return $this->client->call('PATCH', "v3/payments/mandates/{$mandateId}/pause");
    }

    /**
     * Pause an active recurring payment (alias of pause()).
     */
    public function pauseRecurringPayment(string $mandateId): array
    {
        return $this->pause($mandateId);
    }

    /**
     * Resume a previously paused recurring payment.
     */
    public function resume(string $mandateId): array
    {
        return $this->client->call('PATCH', "v3/payments/mandates/{$mandateId}/reinstate");
    }

    /**
     * Resume a previously paused recurring payment (alias of resume()).
     */
    public function resumeRecurringPayment(string $mandateId): array
    {
        return $this->resume($mandateId);
    }

    /**
     * Permanently cancel a recurring payment.
     */
    public function cancel(string $mandateId): array
    {
        return $this->client->call('PATCH', "v3/payments/mandates/{$mandateId}/cancel");
    }

    /**
     * Permanently cancel a recurring payment (alias of cancel()).
     */
    public function cancelRecurringPayment(string $mandateId): array
    {
        return $this->cancel($mandateId);
    }

    /**
     * Check whether the linked account has sufficient funds.
     *
     * @param int|null $amountInKobo  Optional amount to check against (in kobo)
     */
    public function balanceCheck(string $mandateId, ?int $amountInKobo = null): array
    {
        $path = "v3/payments/mandates/{$mandateId}/balance-inquiry";
        if ($amountInKobo !== null) {
            $path .= '?amount=' . $amountInKobo;
        }
        return $this->client->call('GET', $path);
    }

    /**
     * Charge (debit) a recurring payment.
     *
     * @param array $params Required: amount (kobo), reference, narration
     */
    public function charge(string $mandateId, array $params): array
    {
        return $this->client->call('POST', "v3/payments/mandates/{$mandateId}/debit", $params);
    }

    /**
     * List all debits for a recurring payment.
     *
     * @param array $query Optional pagination: page, limit
     */
    public function debits(string $mandateId, array $query = []): array
    {
        $path = "v3/payments/mandates/{$mandateId}/debits";
        if ($query !== []) {
            $path .= '?' . http_build_query($query);
        }
        return $this->client->call('GET', $path);
    }
}
