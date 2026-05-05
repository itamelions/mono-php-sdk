<?php

namespace Mono;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use Mono\Resources\Account;
use Mono\Resources\Bank;
use Mono\Resources\Customer;
use Mono\Resources\Debit;
use Mono\Resources\Mandate;
use Mono\Exceptions\MonoApiException;
use Mono\Exceptions\MonoNotFoundException;

class Mono
{
    protected Client $http;
    protected string $secretKey;
    protected string $baseUrl = 'https://api.withmono.com/';

    public function __construct(string $secretKey)
    {
        $this->secretKey = $secretKey;
        $this->http = new Client([
            'base_uri' => $this->baseUrl,
            'headers'  => [
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
                'mono-sec-key' => $this->secretKey,
            ],
            'timeout'  => 30,
        ]);
    }

    /**
     * Low-level HTTP call. Used internally by resource classes.
     *
     * @throws MonoNotFoundException on 404 responses
     * @throws MonoApiException on all other HTTP or network errors
     */
    public function call(string $method, string $path, array $payload = []): array
    {
        try {
            $options = [];
            if ($method !== 'GET' && $payload !== []) {
                $options['json'] = $payload;
            }
            $response = $this->http->request($method, $path, $options);
            return json_decode($response->getBody()->getContents(), true) ?? [];
        } catch (ClientException $e) {
            if ($e->getResponse()->getStatusCode() === 404) {
                throw new MonoNotFoundException(
                    "Resource not found: {$path}",
                    404,
                    $e
                );
            }
            $body = $e->getResponse()->getBody()->getContents();
            $decoded = json_decode($body, true);
            $message = $decoded['message'] ?? $e->getMessage();
            throw new MonoApiException($message, $e->getResponse()->getStatusCode(), $e);
        } catch (GuzzleException $e) {
            throw new MonoApiException($e->getMessage(), $e->getCode(), $e);
        }
    }

    // ── Resource accessors ───────────────────────────────────────────────────

    public function customer(): Customer
    {
        return new Customer($this);
    }

    public function account(): Account
    {
        return new Account($this);
    }

    public function mandate(): Mandate
    {
        return new Mandate($this);
    }

    public function debit(): Debit
    {
        return new Debit($this);
    }

    public function bank(): Bank
    {
        return new Bank($this);
    }
}
