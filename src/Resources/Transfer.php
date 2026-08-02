<?php

namespace Mono\Resources;

use Mono\Mono;

/**
 * Money operations — payouts, refunds and split-payment sub-accounts.
 *
 * Payouts represent funds Mono moves to you (from your payment wallet).
 * Sub-accounts are used to split incoming payments across multiple accounts.
 */
class Transfer
{
    public function __construct(protected Mono $client) {}

    /**
     * Retrieve payout details (optionally filtered by status).
     *
     * @param array $query Optional filters: status, page, limit
     */
    public function payouts(array $query = []): array
    {
        $path = 'v2/payments/payouts';
        if ($query !== []) {
            $path .= '?' . http_build_query($query);
        }
        return $this->client->call('GET', $path);
    }

    /**
     * List payouts (alias of payouts()).
     */
    public function listPayouts(array $query = []): array
    {
        return $this->payouts($query);
    }

    /**
     * Retrieve the status of a payout's transactions.
     *
     * @param string $payoutId The payout / account ID
     * @param array  $query    Optional filters: page, limit
     */
    public function payoutTransactions(string $payoutId, array $query = []): array
    {
        $path = "v2/payments/payout/{$payoutId}/transactions";
        if ($query !== []) {
            $path .= '?' . http_build_query($query);
        }
        return $this->client->call('GET', $path);
    }

    /**
     * Initiate a refund for a payment.
     *
     * @param array $params Required: reference. Optional: source ('wallet'|'pending_payout')
     */
    public function refund(array $params): array
    {
        return $this->client->call('POST', 'v2/payments/refund', $params);
    }

    /**
     * Create a sub-account for split payments.
     *
     * @param array $params Required: nip_code, account_number
     */
    public function createSubAccount(array $params): array
    {
        return $this->client->call('POST', 'v2/payments/payout/sub-account', $params);
    }

    /**
     * Fetch all created sub-accounts used for split payments.
     *
     * @param array $query Optional filters: page, limit
     */
    public function subAccounts(array $query = []): array
    {
        $path = 'v2/payments/payout/sub-accounts';
        if ($query !== []) {
            $path .= '?' . http_build_query($query);
        }
        return $this->client->call('GET', $path);
    }

    /**
     * List all sub-accounts (alias of subAccounts()).
     */
    public function listSubAccounts(array $query = []): array
    {
        return $this->subAccounts($query);
    }
}
