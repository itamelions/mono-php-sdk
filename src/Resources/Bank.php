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
}
