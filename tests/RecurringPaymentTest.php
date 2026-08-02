<?php

namespace Mono\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Mockery;
use Mockery\MockInterface;
use Mono\Mono;
use PHPUnit\Framework\TestCase;

class RecurringPaymentTest extends TestCase
{
    private Mono $mono;
    private Client&MockInterface $mockHttp;

    protected function setUp(): void
    {
        $this->mockHttp = Mockery::mock(Client::class);
        $this->mono     = Mono::fake('test_secret_key', $this->mockHttp);
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function test_create_posts_hosted_authorisation_with_recurring_type(): void
    {
        $payload = [
            'status' => 'successful',
            'data'   => ['mono_url' => 'https://connect.withmono.com/?code=xyz'],
        ];

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('POST', 'v2/payments/initiate', Mockery::on(function (array $opts) {
                return ($opts['json']['type'] ?? null) === 'recurring-debit';
            }))
            ->andReturn(new Response(200, [], json_encode($payload)));

        $result = $this->mono->recurringPayments()->createRecurringPayment([
            'amount'       => 9190030,
            'method'       => 'mandate',
            'mandate_type' => 'emandate',
            'debit_type'   => 'variable',
            'reference'    => 'ref-001',
            'redirect_url' => 'https://mono.co',
            'customer'     => ['id' => 'cus_123'],
        ]);

        $this->assertArrayHasKey('mono_url', $result['data']);
    }

    public function test_create_direct_posts_to_mandates_path(): void
    {
        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('POST', 'v3/payments/mandates', Mockery::type('array'))
            ->andReturn(new Response(200, [], json_encode(['status' => 'successful', 'data' => ['id' => 'mmc_new']])));

        $result = $this->mono->recurringPayments()->createDirect(['customer' => ['id' => 'cus_123']]);
        $this->assertEquals('mmc_new', $result['data']['id']);
    }

    public function test_list_recurring_payments(): void
    {
        $payload = ['status' => 'successful', 'data' => [['id' => 'mmc_1'], ['id' => 'mmc_2']]];

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'v3/payments/mandates', [])
            ->andReturn(new Response(200, [], json_encode($payload)));

        $result = $this->mono->recurringPayments()->listRecurringPayments();
        $this->assertCount(2, $result['data']);
    }

    public function test_get_recurring_payment(): void
    {
        $payload = ['status' => 'successful', 'data' => ['id' => 'mmc_abc123', 'status' => 'active']];

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'v3/payments/mandates/mmc_abc123', [])
            ->andReturn(new Response(200, [], json_encode($payload)));

        $result = $this->mono->recurringPayments()->getRecurringPayment('mmc_abc123');
        $this->assertEquals('active', $result['data']['status']);
    }

    public function test_pause_recurring_payment(): void
    {
        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('PATCH', 'v3/payments/mandates/mmc_abc123/pause', [])
            ->andReturn(new Response(200, [], json_encode(['status' => 'successful'])));

        $result = $this->mono->recurringPayments()->pauseRecurringPayment('mmc_abc123');
        $this->assertEquals('successful', $result['status']);
    }

    public function test_resume_recurring_payment_uses_reinstate_path(): void
    {
        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('PATCH', 'v3/payments/mandates/mmc_abc123/reinstate', [])
            ->andReturn(new Response(200, [], json_encode(['status' => 'successful'])));

        $result = $this->mono->recurringPayments()->resumeRecurringPayment('mmc_abc123');
        $this->assertEquals('successful', $result['status']);
    }

    public function test_cancel_recurring_payment(): void
    {
        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('PATCH', 'v3/payments/mandates/mmc_abc123/cancel', [])
            ->andReturn(new Response(200, [], json_encode(['status' => 'successful'])));

        $result = $this->mono->recurringPayments()->cancelRecurringPayment('mmc_abc123');
        $this->assertEquals('successful', $result['status']);
    }

    public function test_charge_and_debits_use_debit_paths(): void
    {
        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('POST', 'v3/payments/mandates/mmc_abc123/debit', Mockery::type('array'))
            ->andReturn(new Response(200, [], json_encode(['status' => 'successful'])));

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'v3/payments/mandates/mmc_abc123/debits', [])
            ->andReturn(new Response(200, [], json_encode(['status' => 'successful', 'data' => []])));

        $this->mono->recurringPayments()->charge('mmc_abc123', ['amount' => 500000, 'reference' => 'ref-001']);
        $this->mono->recurringPayments()->debits('mmc_abc123');
        $this->assertTrue(true);
    }
}
