<?php

namespace Mono\Resources;

use Mono\Mono;

/**
 * One-time payments (Mono DirectPay).
 *
 * An initiate call returns a payment link (mono_url) your customer opens in a
 * browser or web view to complete the payment. Always verify the payment via
 * verify() before granting value.
 */
class Payment
{
    public function __construct(protected Mono $client) {}

    /**
     * Initiate a one-time payment.
     *
     * @param array $params amount, type (defaults to 'onetime-debit'),
     *                      method ('account'|'transfer'|'whatsapp'), account,
     *                      description, reference, redirect_url, customer,
     *                      split, meta
     */
    public function initiate(array $params): array
    {
        return $this->client->call('POST', 'v2/payments/initiate', $params);
    }

    /**
     * Initiate a one-time payment (convenience alias of initiate()).
     *
     * Sets type to 'onetime-debit' unless one is already provided.
     */
    public function initiateOneTimePayment(array $params): array
    {
        $params['type'] = $params['type'] ?? 'onetime-debit';
        return $this->initiate($params);
    }

    /**
     * Verify a payment's status using the reference passed at initiation.
     */
    public function verify(string $reference): array
    {
        return $this->client->call('GET', "v2/payments/verify/{$reference}");
    }

    /**
     * Verify a payment's status (alias of verify()).
     */
    public function verifyPayment(string $reference): array
    {
        return $this->verify($reference);
    }

    /**
     * Fetch a single payment by reference (alias of verify()).
     */
    public function getPayment(string $reference): array
    {
        return $this->verify($reference);
    }

    /**
     * Fetch all payments/transactions.
     *
     * @param array $query Optional filters: page, start, end, status, limit
     */
    public function list(array $query = []): array
    {
        $path = 'v2/payments/transactions';
        if ($query !== []) {
            $path .= '?' . http_build_query($query);
        }
        return $this->client->call('GET', $path);
    }

    /**
     * Fetch all payments/transactions (alias of list()).
     */
    public function listPayments(array $query = []): array
    {
        return $this->list($query);
    }
}
