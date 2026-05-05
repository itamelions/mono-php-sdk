<?php

namespace Mono\Tests;

use Mono\Exceptions\MonoApiException;
use Mono\Webhook;
use PHPUnit\Framework\TestCase;

class WebhookTest extends TestCase
{
    private const SECRET = 'test_webhook_secret_key';

    private function makeSignature(string $body, string $secret = self::SECRET): string
    {
        return hash_hmac('sha512', $body, $secret);
    }

    // ── verifySignature ───────────────────────────────────────────────────────

    public function test_valid_signature_passes(): void
    {
        $webhook = new Webhook(self::SECRET);
        $body    = json_encode(['event' => 'mandate_created', 'data' => ['id' => 'mmc_1']]);
        $sig     = $this->makeSignature($body);

        $this->assertTrue($webhook->verifySignature($body, $sig));
    }

    public function test_invalid_signature_fails(): void
    {
        $webhook = new Webhook(self::SECRET);
        $body    = json_encode(['event' => 'mandate_created', 'data' => []]);

        $this->assertFalse($webhook->verifySignature($body, 'bad_signature'));
    }

    public function test_tampered_body_fails(): void
    {
        $webhook      = new Webhook(self::SECRET);
        $originalBody = json_encode(['event' => 'mandate_created', 'data' => ['amount' => 100]]);
        $tamperedBody = json_encode(['event' => 'mandate_created', 'data' => ['amount' => 999]]);
        $sig          = $this->makeSignature($originalBody);

        $this->assertFalse($webhook->verifySignature($tamperedBody, $sig));
    }

    public function test_empty_signature_fails(): void
    {
        $webhook = new Webhook(self::SECRET);
        $body    = json_encode(['event' => 'mandate_created', 'data' => []]);

        $this->assertFalse($webhook->verifySignature($body, ''));
    }

    public function test_empty_secret_fails(): void
    {
        $webhook = new Webhook('');
        $body    = json_encode(['event' => 'mandate_created', 'data' => []]);
        $sig     = $this->makeSignature($body);

        $this->assertFalse($webhook->verifySignature($body, $sig));
    }

    public function test_signature_comparison_is_case_insensitive(): void
    {
        $webhook = new Webhook(self::SECRET);
        $body    = json_encode(['event' => 'mandate_created', 'data' => []]);
        $sig     = strtoupper($this->makeSignature($body));

        $this->assertTrue($webhook->verifySignature($body, $sig));
    }

    // ── process ───────────────────────────────────────────────────────────────

    public function test_process_dispatches_named_listener(): void
    {
        $webhook  = new Webhook(self::SECRET);
        $received = null;

        $webhook->on('events.mandates.created', function (array $data) use (&$received) {
            $received = $data;
        });

        $body = json_encode(['event' => 'events.mandates.created', 'data' => ['id' => 'mmc_abc']]);
        $sig  = $this->makeSignature($body);

        $webhook->process($body, $sig);

        $this->assertNotNull($received);
        $this->assertIsArray($received);
        $this->assertEquals('mmc_abc', $received['id']);
    }

    public function test_process_dispatches_wildcard_listener(): void
    {
        $webhook       = new Webhook(self::SECRET);
        $receivedEvent = null;
        $receivedData  = null;

        $webhook->on('*', function (string $event, array $data) use (&$receivedEvent, &$receivedData) {
            $receivedEvent = $event;
            $receivedData  = $data;
        });

        $body = json_encode(['event' => 'events.mandates.debit.successful', 'data' => ['reference' => 'ref-001']]);
        $sig  = $this->makeSignature($body);

        $webhook->process($body, $sig);

        $this->assertNotNull($receivedData);
        $this->assertIsArray($receivedData);
        $this->assertEquals('events.mandates.debit.successful', $receivedEvent);
        $this->assertEquals('ref-001', $receivedData['reference']);
    }

    public function test_process_dispatches_both_specific_and_wildcard(): void
    {
        $webhook        = new Webhook(self::SECRET);
        $specificCalled = false;
        $wildcardCalled = false;

        $webhook->on('events.mandate.action.pause', function (array $data) use (&$specificCalled) {
            $specificCalled = true;
        });
        $webhook->on('*', function (string $event, array $data) use (&$wildcardCalled) {
            $wildcardCalled = true;
        });

        $body = json_encode(['event' => 'events.mandate.action.pause', 'data' => ['id' => 'mmc_x']]);
        $sig  = $this->makeSignature($body);

        $webhook->process($body, $sig);

        $this->assertTrue($specificCalled);
        $this->assertTrue($wildcardCalled);
    }

    public function test_process_no_listener_registered_is_silent(): void
    {
        $webhook = new Webhook(self::SECRET);

        $body = json_encode(['event' => 'unknown_event', 'data' => []]);
        $sig  = $this->makeSignature($body);

        // Should not throw — unhandled events are simply ignored
        $webhook->process($body, $sig);
        $this->assertTrue(true);
    }

    public function test_process_throws_on_invalid_signature(): void
    {
        $this->expectException(MonoApiException::class);
        $this->expectExceptionCode(401);

        $webhook = new Webhook(self::SECRET);
        $body    = json_encode(['event' => 'mandate_created', 'data' => []]);

        $webhook->process($body, 'invalid_signature');
    }

    public function test_process_throws_on_invalid_json_body(): void
    {
        $this->expectException(MonoApiException::class);
        $this->expectExceptionCode(400);

        $webhook = new Webhook(self::SECRET);
        $body    = 'not valid json';
        $sig     = $this->makeSignature($body);

        $webhook->process($body, $sig);
    }

    // ── on() fluent chaining ─────────────────────────────────────────────────

    public function test_on_returns_same_instance_for_chaining(): void
    {
        $webhook = new Webhook(self::SECRET);
        $result  = $webhook->on('mandate_created', fn ($d) => null);

        $this->assertSame($webhook, $result);
    }

    public function test_multiple_listeners_for_same_event_all_fire(): void
    {
        $webhook = new Webhook(self::SECRET);
        $count   = 0;

        $webhook->on('events.mandates.debit.successful', function () use (&$count) { $count++; });
        $webhook->on('events.mandates.debit.successful', function () use (&$count) { $count++; });

        $body = json_encode(['event' => 'events.mandates.debit.successful', 'data' => []]);
        $sig  = $this->makeSignature($body);

        $webhook->process($body, $sig);

        $this->assertEquals(2, $count);
    }
}
