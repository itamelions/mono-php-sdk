<?php

namespace Mono\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Mockery;
use Mockery\MockInterface;
use Mono\Mono;
use PHPUnit\Framework\TestCase;

class PaymentTest extends TestCase
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

    public function test_initiate_one_time_payment_posts_to_initiate_path(): void
    {
        $payload = [
            'status' => 'successful',
            'data'   => ['mono_url' => 'https://connect.withmono.com/?code=xyz', 'reference' => 'ref-001'],
        ];

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('POST', 'v2/payments/initiate', Mockery::type('array'))
            ->andReturn(new Response(200, [], json_encode($payload)));

        $result = $this->mono->payments()->initiateOneTimePayment([
            'amount'       => 20000,
            'method'       => 'account',
            'account'      => '678f7bc977eb9afb06fff11f',
            'description'  => 'testing',
            'reference'    => 'ref-001',
            'redirect_url' => 'https://mono.co',
        ]);

        $this->assertEquals('successful', $result['status']);
        $this->assertArrayHasKey('mono_url', $result['data']);
    }

    public function test_initiate_one_time_payment_defaults_type_to_onetime_debit(): void
    {
        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('POST', 'v2/payments/initiate', Mockery::on(function (array $opts) {
                return ($opts['json']['type'] ?? null) === 'onetime-debit';
            }))
            ->andReturn(new Response(200, [], json_encode(['status' => 'successful'])));

        $result = $this->mono->payments()->initiateOneTimePayment(['amount' => 20000]);
        $this->assertEquals('successful', $result['status']);
    }

    public function test_verify_payment_hits_verify_reference_path(): void
    {
        $payload = ['status' => 'successful', 'data' => ['reference' => 'ref-001', 'status' => 'successful']];

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'v2/payments/verify/ref-001', [])
            ->andReturn(new Response(200, [], json_encode($payload)));

        $result = $this->mono->payments()->verifyPayment('ref-001');
        $this->assertEquals('successful', $result['data']['status']);
    }

    public function test_get_payment_is_alias_of_verify(): void
    {
        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'v2/payments/verify/ref-002', [])
            ->andReturn(new Response(200, [], json_encode(['status' => 'successful', 'data' => []])));

        $this->mono->payments()->getPayment('ref-002');
        $this->assertTrue(true);
    }

    public function test_list_payments_hits_transactions_path(): void
    {
        $payload = ['status' => 'successful', 'data' => [['reference' => 'ref-001']]];

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'v2/payments/transactions', [])
            ->andReturn(new Response(200, [], json_encode($payload)));

        $result = $this->mono->payments()->list();
        $this->assertCount(1, $result['data']);
    }

    public function test_list_payments_appends_query(): void
    {
        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'v2/payments/transactions?page=1&status=successful&limit=100', [])
            ->andReturn(new Response(200, [], json_encode(['status' => 'successful', 'data' => []])));

        $this->mono->payments()->listPayments(['page' => 1, 'status' => 'successful', 'limit' => 100]);
        $this->assertTrue(true);
    }
}
