<?php

namespace Mono\Resources;

use Mono\Mono;

class Debit
{
    public function __construct(protected Mono $client) {}

    /**
     * POST v3/payments/mandates/{id}/debit
     * Charge (debit) a mandate.
     *
     * Required params: amount (kobo), reference, narration
     */
    public function charge(string $mandateId, array $params): array
    {
        return $this->client->call('POST', "v3/payments/mandates/{$mandateId}/debit", $params);
    }

    /**
     * GET v3/payments/mandates/{id}/debit/{reference}
     * Fetch a single debit transaction by its reference.
     */
    public function fetch(string $mandateId, string $reference): array
    {
        return $this->client->call('GET', "v3/payments/mandates/{$mandateId}/debit/{$reference}");
    }

    /**
     * GET v3/payments/mandates/{id}/debits
     * List all debit transactions for a mandate. Supports pagination: page, limit.
     */
    public function all(string $mandateId, array $query = []): array
    {
        $path = "v3/payments/mandates/{$mandateId}/debits";
        if ($query !== []) {
            $path .= '?' . http_build_query($query);
        }
        return $this->client->call('GET', $path);
    }
}
