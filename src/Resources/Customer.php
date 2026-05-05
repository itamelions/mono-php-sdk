<?php

namespace Mono\Resources;

use Mono\Mono;

class Customer
{
    public function __construct(protected Mono $client) {}

    /**
     * Create a new customer.
     *
     * Required params: email, first_name, last_name, phone
     * Optional params: identity (type + number, e.g. BVN)
     */
    public function create(array $params): array
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
}
