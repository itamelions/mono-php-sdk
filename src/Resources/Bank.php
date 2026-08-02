<?php

namespace Mono\Resources;

use Mono\Mono;

class Bank
{
    public function __construct(protected Mono $client) {}

    /**
     * GET v3/banks/list
     * Returns a list of supported banks and their metadata.
     */
    public function list(): array
    {
        return $this->client->call('GET', 'v3/banks/list');
    }

    /**
     * GET v3/institutions
     * Returns bank coverage across Mono's supported institutions and product
     * scopes (e.g. Financial data or Directpay).
     *
     * @param array $query Optional filters (e.g. product)
     */
    public function coverage(array $query = []): array
    {
        $path = 'v3/institutions';
        if ($query !== []) {
            $path .= '?' . http_build_query($query);
        }
        return $this->client->call('GET', $path);
    }

    /**
     * Bank coverage (alias of coverage()).
     */
    public function getBankCoverage(array $query = []): array
    {
        return $this->coverage($query);
    }

    /**
     * GET v3/lookup/banks
     * Returns all banks supported under NIBSS / NIP for transfers.
     *
     * @param array $query Optional pagination: page, limit
     */
    public function nip(array $query = []): array
    {
        $path = 'v3/lookup/banks';
        if ($query !== []) {
            $path .= '?' . http_build_query($query);
        }
        return $this->client->call('GET', $path);
    }

    /**
     * POST v3/lookup/account-number
     * Verify an account number (returns the masked BVN attached to it).
     *
     * @param array $params Required: nip_code, account_number
     */
    public function lookupAccountNumber(array $params): array
    {
        return $this->client->call('POST', 'v3/lookup/account-number', $params);
    }
}
