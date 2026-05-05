<?php

namespace Mono;

use Mono\Exceptions\MonoApiException;

/**
 * Webhook handler for Mono event notifications.
 *
 * Usage:
 *
 *   $webhook = new Webhook('your-webhook-secret');
 *
 *   // Register listeners
 *   $webhook->on('mandate_created', function (array $data) { ... });
 *   $webhook->on('*', function (string $event, array $data) { ... }); // catch-all
 *
 *   // Process an incoming request
 *   $webhook->process(
 *       $rawBody,
 *       $_SERVER['HTTP_MONO_WEBHOOK_SECRET'] ?? ''
 *   );
 */
class Webhook
{
    /** @var array<string, list<callable>> */
    private array $listeners = [];

    public function __construct(private readonly string $webhookSecret) {}

    /**
     * Register a listener for a specific event type.
     *
     * Use '*' as the event name to receive every event. The wildcard callback
     * receives (string $event, array $data) instead of just (array $data).
     *
     * @param string   $event    Event name, e.g. 'mandate_created', or '*'
     * @param callable $callback Callable to invoke when the event fires
     */
    public function on(string $event, callable $callback): static
    {
        $this->listeners[$event][] = $callback;
        return $this;
    }

    /**
     * Verify the signature and dispatch the event to registered listeners.
     *
     * @param string $rawBody       The raw HTTP request body (do NOT json_decode first)
     * @param string $signatureHeader  Value of the 'mono-webhook-secret' header
     *
     * @throws MonoApiException if the signature is invalid
     */
    public function process(string $rawBody, string $signatureHeader): void
    {
        if (!$this->verifySignature($rawBody, $signatureHeader)) {
            throw new MonoApiException('Invalid webhook signature.', 401);
        }

        $payload = json_decode($rawBody, true);
        if (!is_array($payload)) {
            throw new MonoApiException('Invalid webhook payload: could not decode JSON.', 400);
        }

        $event = (string) ($payload['event'] ?? '');
        $data  = (array)  ($payload['data']  ?? []);

        $this->dispatch($event, $data);
    }

    /**
     * Verify the HMAC-SHA512 signature sent by Mono.
     *
     * Mono signs the raw request body with your webhook secret using
     * HMAC-SHA512 and sends the hex digest in the 'mono-webhook-secret' header.
     */
    public function verifySignature(string $rawBody, string $signatureHeader): bool
    {
        if ($this->webhookSecret === '' || $signatureHeader === '') {
            return false;
        }

        $expected = hash_hmac('sha512', $rawBody, $this->webhookSecret);

        // Use hash_equals to prevent timing attacks
        return hash_equals($expected, strtolower($signatureHeader));
    }

    /**
     * Dispatch a verified event to all matching listeners.
     */
    private function dispatch(string $event, array $data): void
    {
        // Specific listeners
        foreach ($this->listeners[$event] ?? [] as $callback) {
            $callback($data);
        }

        // Wildcard listeners receive both event name and data
        foreach ($this->listeners['*'] ?? [] as $callback) {
            $callback($event, $data);
        }
    }
}
