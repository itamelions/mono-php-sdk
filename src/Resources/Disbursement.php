<?php

namespace Mono\Resources;

use Mono\Mono;

/**
 * Disburse — pay out to one or more recipients from a source account.
 *
 * A disbursement is a "batch" that contains one or more "distributions"
 * (individual payouts). Batches can be executed instantly or scheduled.
 */
class Disbursement
{
    public function __construct(protected Mono $client) {}

    // ── Source accounts ──────────────────────────────────────────────────────

    /**
     * Create a source account to be used for disbursements.
     *
     * @param array $params Required: app, account_number, bank_code. Optional: email
     */
    public function createSourceAccount(array $params): array
    {
        return $this->client->call('POST', 'v3/payments/disburse/source-accounts', $params);
    }

    /**
     * Update a source account by ID.
     */
    public function updateSourceAccount(string $accountId, array $params): array
    {
        return $this->client->call('PUT', "v3/payments/disburse/source-accounts/{$accountId}", $params);
    }

    /**
     * Fetch all source accounts.
     *
     * @param array $query Optional pagination: page, limit
     */
    public function sourceAccounts(array $query = []): array
    {
        $path = 'v3/payments/disburse/source-accounts';
        if ($query !== []) {
            $path .= '?' . http_build_query($query);
        }
        return $this->client->call('GET', $path);
    }

    /**
     * Fetch all source accounts (alias of sourceAccounts()).
     */
    public function listSourceAccounts(array $query = []): array
    {
        return $this->sourceAccounts($query);
    }

    /**
     * Fetch a single source account by ID.
     */
    public function getSourceAccount(string $accountId): array
    {
        return $this->client->call('GET', "v3/payments/disburse/source-accounts/{$accountId}");
    }

    // ── Disbursement batches ─────────────────────────────────────────────────

    /**
     * Create a disbursement batch. Pass `type` of 'instant' or 'scheduled'.
     *
     * @param array $params reference, source, account, type, total_amount,
     *                      description, distribution[]
     */
    public function create(array $params): array
    {
        return $this->client->call('POST', 'v3/payments/disburse/disbursements', $params);
    }

    /**
     * Create a disbursement batch (alias of create()).
     */
    public function createDisbursement(array $params): array
    {
        return $this->create($params);
    }

    /**
     * Create an instant disbursement (immediate payouts).
     */
    public function createInstant(array $params): array
    {
        $params['type'] = 'instant';
        return $this->create($params);
    }

    /**
     * Create a scheduled disbursement (executed at a later time).
     */
    public function createScheduled(array $params): array
    {
        $params['type'] = 'scheduled';
        return $this->create($params);
    }

    /**
     * Fetch a paginated list of all disbursement batches.
     *
     * @param array $query Optional pagination: page, limit
     */
    public function list(array $query = []): array
    {
        $path = 'v3/payments/disburse/disbursements';
        if ($query !== []) {
            $path .= '?' . http_build_query($query);
        }
        return $this->client->call('GET', $path);
    }

    /**
     * Fetch a paginated list of all disbursement batches (alias of list()).
     */
    public function listDisbursements(array $query = []): array
    {
        return $this->list($query);
    }

    /**
     * Fetch a specific disbursement batch by its ID.
     */
    public function get(string $batchId): array
    {
        return $this->client->call('GET', "v3/payments/disburse/disbursements/{$batchId}");
    }

    /**
     * Fetch a specific disbursement batch (alias of get()).
     */
    public function getDisbursement(string $batchId): array
    {
        return $this->get($batchId);
    }

    /**
     * Transition a scheduled disbursement — e.g. 'trigger' its execution or
     * 'cancel' it.
     */
    public function transition(string $batchId, string $action): array
    {
        return $this->client->call(
            'POST',
            "v3/payments/disburse/disbursements/{$batchId}/transition",
            ['action' => $action]
        );
    }

    /**
     * Trigger execution of a scheduled disbursement.
     */
    public function trigger(string $batchId): array
    {
        return $this->transition($batchId, 'trigger');
    }

    /**
     * Retry a disbursement (trigger a pending / failed scheduled batch).
     */
    public function retryDisbursement(string $batchId): array
    {
        return $this->trigger($batchId);
    }

    /**
     * Cancel a scheduled disbursement.
     */
    public function cancel(string $batchId): array
    {
        return $this->transition($batchId, 'cancel');
    }

    // ── Distributions ────────────────────────────────────────────────────────

    /**
     * Add distributions to an existing batch.
     *
     * @param string $batchId      The disbursement batch ID
     * @param array  $distribution List of distributions, each containing
     *                             account_number, bank_code, amount, narration,
     *                             reference, recipient_email
     */
    public function addDistributions(string $batchId, array $distribution): array
    {
        return $this->client->call(
            'POST',
            "v3/payments/disburse/disbursements/{$batchId}/distributions",
            ['distribution' => $distribution]
        );
    }

    /**
     * Update a distribution inside a batch.
     */
    public function updateDistribution(string $batchId, string $distributionId, array $params): array
    {
        return $this->client->call(
            'PATCH',
            "v3/payments/disburse/disbursements/{$batchId}/distributions/{$distributionId}",
            $params
        );
    }

    /**
     * Delete a distribution inside a batch.
     */
    public function deleteDistribution(string $batchId, string $distributionId): array
    {
        return $this->client->call(
            'DELETE',
            "v3/payments/disburse/disbursements/{$batchId}/distributions/{$distributionId}"
        );
    }

    /**
     * Fetch all distributions within a batch.
     *
     * @param array $query Optional pagination: page, limit
     */
    public function distributions(string $batchId, array $query = []): array
    {
        $path = "v3/payments/disburse/disbursements/{$batchId}/distributions";
        if ($query !== []) {
            $path .= '?' . http_build_query($query);
        }
        return $this->client->call('GET', $path);
    }

    /**
     * Fetch a single distribution within a batch.
     */
    public function getDistribution(string $batchId, string $distributionId): array
    {
        return $this->client->call(
            'GET',
            "v3/payments/disburse/disbursements/{$batchId}/distributions/{$distributionId}"
        );
    }
}
