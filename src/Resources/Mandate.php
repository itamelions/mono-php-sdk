<?php

namespace Mono\Resources;

use Mono\Mono;

class Mandate
{
    public function __construct(protected Mono $client) {}

    /**
     * POST v2/payments/initiate
     * Creates a hosted mandate setup page (returns a mono_url to redirect the user to).
     *
     * Required params: amount, type, method, mandate_type, debit_type,
     *                  reference, redirect_url, customer, start_date, end_date
     */
    public function initiate(array $params): array
    {
        return $this->client->call('POST', 'v2/payments/initiate', $params);
    }

    /**
     * POST v3/payments/mandates
     * Direct / e-mandate creation (no hosted page — requires customer BVN/OTP flow).
     */
    public function create(array $params): array
    {
        return $this->client->call('POST', 'v3/payments/mandates', $params);
    }

    /**
     * GET v3/payments/mandates/{id}
     * Fetch the details and current status of a mandate.
     */
    public function fetch(string $mandateId): array
    {
        return $this->client->call('GET', "v3/payments/mandates/{$mandateId}");
    }

    /**
     * GET v3/payments/mandates
     * List all mandates. Supports optional pagination: page, limit.
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
     * PATCH v3/payments/mandates/{id}/pause
     * Pause an active mandate (prevents future debits without cancelling).
     */
    public function pause(string $mandateId): array
    {
        return $this->client->call('PATCH', "v3/payments/mandates/{$mandateId}/pause");
    }

    /**
     * PATCH v3/payments/mandates/{id}/reinstate
     * Reinstate a previously paused mandate.
     */
    public function reinstate(string $mandateId): array
    {
        return $this->client->call('PATCH', "v3/payments/mandates/{$mandateId}/reinstate");
    }

    /**
     * PATCH v3/payments/mandates/{id}/cancel
     * Permanently cancel a mandate.
     */
    public function cancel(string $mandateId): array
    {
        return $this->client->call('PATCH', "v3/payments/mandates/{$mandateId}/cancel");
    }

    /**
     * GET v3/payments/mandates/{id}/balance-inquiry
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
}
