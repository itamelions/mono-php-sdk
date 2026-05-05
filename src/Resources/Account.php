<?php

namespace Mono\Resources;

use Mono\Mono;

class Account
{
    public function __construct(protected Mono $client) {}

    /**
     * Exchange a Mono Connect auth code for an account ID.
     * Called once after the user completes Connect flow.
     */
    public function auth(string $code): array
    {
        return $this->client->call('POST', 'v2/accounts/auth', ['code' => $code]);
    }

    /**
     * Fetch account details by account ID.
     */
    public function fetch(string $accountId): array
    {
        return $this->client->call('GET', "v2/accounts/{$accountId}");
    }

    /**
     * Fetch account statement / transactions.
     *
     * @param int $limit  Number of records to return (default 100)
     * @param array $query Additional filters: period, start, end, paginate
     */
    public function transactions(string $accountId, int $limit = 100, array $query = []): array
    {
        $params = array_merge(['limit' => $limit], $query);
        return $this->client->call('GET', "v2/accounts/{$accountId}/transactions?" . http_build_query($params));
    }

    /**
     * Fetch account identity (name, BVN-masked, etc.).
     */
    public function identity(string $accountId): array
    {
        return $this->client->call('GET', "v2/accounts/{$accountId}/identity");
    }

    /**
     * Fetch income information derived from the account.
     */
    public function income(string $accountId): array
    {
        return $this->client->call('GET', "v2/accounts/{$accountId}/income");
    }

    /**
     * Unlink (revoke) an account.
     */
    public function unlink(string $accountId): array
    {
        return $this->client->call('POST', "v2/accounts/{$accountId}/unlink");
    }
}
