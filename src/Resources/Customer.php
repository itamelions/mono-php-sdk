<?php

namespace Mono\Resources;

use Mono\Mono;

class Customer
{
    public function __construct(protected Mono $client) {}

    /**
     * Create a new individual customer.
     *
     * Required params: email, first_name, last_name, phone
     * Optional params: identity (type + number, e.g. BVN), type, address
     */
    public function create(array $params): array
    {
        return $this->client->call('POST', 'v2/customers', $params);
    }

    /**
     * Create a new individual customer (alias of create()).
     */
    public function createIndividual(array $params): array
    {
        return $this->create($params);
    }

    /**
     * Create a new business customer.
     *
     * Required params: email, business_name, phone
     * Optional params: identity, type, address
     */
    public function createBusiness(array $params): array
    {
        return $this->client->call('POST', 'v2/customers', $params);
    }

    /**
     * Update an existing customer by ID.
     */
    public function update(string $customerId, array $params): array
    {
        return $this->client->call('PATCH', "v2/customers/{$customerId}", $params);
    }

    /**
     * Fetch a customer by ID.
     */
    public function fetch(string $customerId): array
    {
        return $this->client->call('GET', "v2/customers/{$customerId}");
    }

    /**
     * List all customers. Supports optional pagination: page, limit.
     */
    public function list(array $query = []): array
    {
        $path = 'v2/customers';
        if ($query !== []) {
            $path .= '?' . http_build_query($query);
        }
        return $this->client->call('GET', $path);
    }

    /**
     * Permanently delete a customer by ID.
     */
    public function delete(string $customerId): array
    {
        return $this->client->call('DELETE', "v2/customers/{$customerId}");
    }

    /**
     * Fetch all transactions performed by a customer via Mono's payment products.
     *
     * @param array $query Optional filters: period, page, account
     */
    public function transactions(string $customerId, array $query = []): array
    {
        $path = "v2/customers/{$customerId}/transactions";
        if ($query !== []) {
            $path .= '?' . http_build_query($query);
        }
        return $this->client->call('GET', $path);
    }

    /**
     * Fetch all accounts linked to the business.
     *
     * The Mono API exposes all linked accounts via GET /v2/accounts (no
     * customer filter is supported).
     *
     * @param array $query Optional filters: page, limit
     */
    public function linkedAccounts(string $customerId, array $query = []): array
    {
        $path = 'v2/accounts';
        if ($query !== []) {
            $path .= '?' . http_build_query($query);
        }
        return $this->client->call('GET', $path);
    }
}
