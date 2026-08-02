<?php

namespace Mono\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Mockery;
use Mockery\MockInterface;
use Mono\Mono;
use PHPUnit\Framework\TestCase;

class TransferTest extends TestCase
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

    public function test_payouts_hit_payouts_path(): void
    {
        $payload = ['status' => 'successful', 'data' => [['id' => 'payout_1']]];

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'v2/payments/payouts', [])
            ->andReturn(new Response(200, [], json_encode($payload)));

        $result = $this->mono->transfers()->payouts();
        $this->assertCount(1, $result['data']);
    }

    public function test_payouts_appends_status_filter(): void
    {
        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'v2/payments/payouts?status=successful', [])
            ->andReturn(new Response(200, [], json_encode(['status' => 'successful', 'data' => []])));

        $this->mono->transfers()->listPayouts(['status' => 'successful']);
        $this->assertTrue(true);
    }

    public function test_payout_transactions(): void
    {
        $payload = ['status' => 'successful', 'data' => [['reference' => 'ref-001']]];

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'v2/payments/payout/payout_123/transactions', [])
            ->andReturn(new Response(200, [], json_encode($payload)));

        $result = $this->mono->transfers()->payoutTransactions('payout_123');
        $this->assertCount(1, $result['data']);
    }

    public function test_refund_posts_to_refund_path(): void
    {
        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('POST', 'v2/payments/refund', Mockery::on(function (array $opts) {
                return ($opts['json']['reference'] ?? null) === 'ref-123-456'
                    && ($opts['json']['source'] ?? null) === 'wallet';
            }))
            ->andReturn(new Response(200, [], json_encode(['status' => 'successful'])));

        $result = $this->mono->transfers()->refund(['reference' => 'ref-123-456', 'source' => 'wallet']);
        $this->assertEquals('successful', $result['status']);
    }

    public function test_create_sub_account(): void
    {
        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('POST', 'v2/payments/payout/sub-account', Mockery::type('array'))
            ->andReturn(new Response(200, [], json_encode(['status' => 'successful', 'data' => ['id' => 'sub_1']])));

        $result = $this->mono->transfers()->createSubAccount(['nip_code' => '000000', 'account_number' => '1234567890']);
        $this->assertEquals('sub_1', $result['data']['id']);
    }

    public function test_list_sub_accounts(): void
    {
        $payload = ['status' => 'successful', 'data' => [['id' => 'sub_1']]];

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'v2/payments/payout/sub-accounts', [])
            ->andReturn(new Response(200, [], json_encode($payload)));

        $result = $this->mono->moneyOperations()->subAccounts();
        $this->assertCount(1, $result['data']);
    }
}
