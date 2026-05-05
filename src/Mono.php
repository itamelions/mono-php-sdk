<?php

namespace Mono;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Mono\Resources\Account;
use Mono\Resources\Bank;
use Mono\Resources\Customer;
use Mono\Resources\Debit;
use Mono\Resources\Mandate;
use Mono\Exceptions\MonoApiException;
use Mono\Exceptions\MonoConnectionException;
use Mono\Exceptions\MonoNotFoundException;
use Mono\Exceptions\MonoRateLimitException;

class Mono
{
    protected Client $http;
    protected string $secretKey;
    protected string $baseUrl = 'https://api.withmono.com/';

    /**
     * @param string $secretKey   Your Mono secret key.
     * @param array{
     *     timeout?: int,
     *     connect_timeout?: int,
     *     max_retries?: int,
     * } $options
     *   timeout         – Total request timeout in seconds (default 30).
     *   connect_timeout – TCP connection timeout in seconds (default 10).
     *   max_retries     – Automatic retries on 429 / 503. Honours the
     *                     Retry-After header when present (default 0 = no retries).
     */
    public function __construct(string $secretKey, array $options = [])
    {
        $this->secretKey = $secretKey;

        $timeout        = (int) ($options['timeout']         ?? 30);
        $connectTimeout = (int) ($options['connect_timeout'] ?? 10);
        $maxRetries     = (int) ($options['max_retries']     ?? 0);

        $stack = HandlerStack::create();

        if ($maxRetries > 0) {
            $stack->push(Middleware::retry(
                $this->retryDecider($maxRetries),
                $this->retryDelay()
            ));
        }

        $this->http = new Client([
            'base_uri'        => $this->baseUrl,
            'handler'         => $stack,
            'headers'         => [
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
                'mono-sec-key' => $this->secretKey,
            ],
            'timeout'         => $timeout,
            'connect_timeout' => $connectTimeout,
        ]);
    }

    /**
     * Decides whether to retry a request.
     * Retries on network errors and on 429 / 503 HTTP responses.
     */
    private function retryDecider(int $maxRetries): \Closure
    {
        return function (
            int $retries,
            RequestInterface $request,
            ?ResponseInterface $response = null,
        ) use ($maxRetries): bool {
            if ($retries >= $maxRetries) {
                return false;
            }
            // No response → network failure, retry.
            if ($response === null) {
                return true;
            }
            return in_array($response->getStatusCode(), [429, 503], true);
        };
    }

    /**
     * Calculates delay (ms) before the next retry attempt.
     * Respects the Retry-After header; falls back to exponential back-off.
     */
    private function retryDelay(): \Closure
    {
        return function (int $retries, ?ResponseInterface $response): int {
            if ($response !== null && $response->hasHeader('Retry-After')) {
                $seconds = (int) $response->getHeaderLine('Retry-After');
                if ($seconds > 0) {
                    return $seconds * 1000;
                }
            }
            // Exponential back-off: 1 s, 2 s, 4 s …
            return (int) (1000 * 2 ** ($retries - 1));
        };
    }

    /**
     * Low-level HTTP call. Used internally by resource classes.
     *
     * @throws MonoNotFoundException   on 404 responses
     * @throws MonoApiException        on 4xx / 5xx responses or invalid JSON
     * @throws MonoConnectionException on network-level failures (timeout, DNS, etc.)
     */
    public function call(string $method, string $path, array $payload = []): array
    {
        try {
            $options = [];
            if ($method !== 'GET' && $payload !== []) {
                $options['json'] = $payload;
            }
            $response = $this->http->request($method, $path, $options);
            $rawBody  = $response->getBody()->getContents();
            $decoded  = json_decode($rawBody, true);
            if ($decoded === null && $rawBody !== '') {
                throw new MonoApiException(
                    "Mono API returned non-JSON response for {$path}",
                    $response->getStatusCode()
                );
            }
            return $decoded ?? [];
        } catch (BadResponseException $e) {
            // Covers both 4xx (ClientException) and 5xx (ServerException).
            $statusCode = $e->getResponse()->getStatusCode();
            if ($statusCode === 404) {
                throw new MonoNotFoundException("Resource not found: {$path}", 404, $e);
            }
            if ($statusCode === 429) {
                $retryAfterRaw = $e->getResponse()->getHeaderLine('Retry-After');
                $retryAfter    = $retryAfterRaw !== '' ? (int) $retryAfterRaw : null;
                throw new MonoRateLimitException('Mono API rate limit exceeded', 429, $retryAfter, $e);
            }
            $body    = $e->getResponse()->getBody()->getContents();
            $decoded = json_decode($body, true);
            $message = $decoded['message'] ?? $e->getMessage();
            throw new MonoApiException($message, $statusCode, $e);
        } catch (GuzzleException $e) {
            // Network-level failure — the API never responded.
            throw new MonoConnectionException($e->getMessage(), $e->getCode(), $e);
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

    // ── Testing ──────────────────────────────────────────────────────────────

    /**
     * Create a Mono instance with a pre-built HTTP client injected.
     * Intended for unit tests — avoids ReflectionProperty hacks.
     *
     * Usage:
     *   $mockHttp = Mockery::mock(Client::class);
     *   $mono     = Mono::fake('test_key', $mockHttp);
     */
    public static function fake(string $secretKey, Client $http): static
    {
        $instance       = new static($secretKey);
        $instance->http = $http;
        return $instance;
    }
}
