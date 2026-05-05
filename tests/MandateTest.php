<?php

namespace Mono\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Mockery;
use Mockery\MockInterface;
use Mono\Exceptions\MonoApiException;
use Mono\Exceptions\MonoNotFoundException;
use Mono\Mono;
use PHPUnit\Framework\TestCase;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Request;

class MandateTest extends TestCase
{
    private Mono $mono;
    private Client&MockInterface $mockHttp;

    protected function setUp(): void
    {
        $this->mono = new Mono('test_secret_key');
        $this->mockHttp = Mockery::mock(Client::class);

        $reflection = new \ReflectionProperty(Mono::class, 'http');
        $reflection->setValue($this->mono, $this->mockHttp);
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }

    // ── fetch ────────────────────────────────────────────────────────────────

    public function test_fetch_returns_mandate_data(): void
    {
        $payload = [
            'status' => 'successful',
            'data'   => ['id' => 'mmc_abc123', 'status' => 'active'],
        ];

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'v3/payments/mandates/mmc_abc123', [])
            ->andReturn(new Response(200, [], json_encode($payload)));

        $result = $this->mono->mandate()->fetch('mmc_abc123');

        $this->assertEquals('successful', $result['status']);
        $this->assertEquals('active', $result['data']['status']);
        $this->assertEquals('mmc_abc123', $result['data']['id']);
    }

    // ── initiate ─────────────────────────────────────────────────────────────

    public function test_initiate_returns_mono_url(): void
    {
        $payload = [
            'status' => 'successful',
            'data'   => ['mono_url' => 'https://connect.withmono.com/?code=xxx'],
        ];

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('POST', 'v2/payments/initiate', Mockery::type('array'))
            ->andReturn(new Response(200, [], json_encode($payload)));

        $result = $this->mono->mandate()->initiate([
            'amount'       => 5000000,
            'type'         => 'recurring-debit',
            'method'       => 'mandate',
            'mandate_type' => 'sweep',
            'debit_type'   => 'variable',
            'reference'    => 'ref-001',
            'redirect_url' => 'https://example.com/callback',
            'customer'     => ['id' => 'cus_123'],
            'start_date'   => '2026-01-01 00:00:00',
            'end_date'     => '2031-01-01 00:00:00',
        ]);

        $this->assertArrayHasKey('mono_url', $result['data']);
    }

    // ── create ───────────────────────────────────────────────────────────────

    public function test_create_mandate_returns_data(): void
    {
        $payload = ['status' => 'successful', 'data' => ['id' => 'mmc_new']];

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->andReturn(new Response(200, [], json_encode($payload)));

        $result = $this->mono->mandate()->create(['customer' => ['id' => 'cus_123']]);
        $this->assertEquals('mmc_new', $result['data']['id']);
    }

    // ── pause / reinstate / cancel ────────────────────────────────────────────

    public function test_pause_mandate(): void
    {
        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('PATCH', 'v3/payments/mandates/mmc_abc123/pause', [])
            ->andReturn(new Response(200, [], json_encode(['status' => 'successful'])));

        $result = $this->mono->mandate()->pause('mmc_abc123');
        $this->assertEquals('successful', $result['status']);
    }

    public function test_reinstate_mandate(): void
    {
        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('PATCH', 'v3/payments/mandates/mmc_abc123/reinstate', [])
            ->andReturn(new Response(200, [], json_encode(['status' => 'successful'])));

        $result = $this->mono->mandate()->reinstate('mmc_abc123');
        $this->assertEquals('successful', $result['status']);
    }

    public function test_cancel_mandate(): void
    {
        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('PATCH', 'v3/payments/mandates/mmc_abc123/cancel', [])
            ->andReturn(new Response(200, [], json_encode(['status' => 'successful'])));

        $result = $this->mono->mandate()->cancel('mmc_abc123');
        $this->assertEquals('successful', $result['status']);
    }

    // ── balanceCheck ─────────────────────────────────────────────────────────

    public function test_balance_check_without_amount(): void
    {
        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'v3/payments/mandates/mmc_abc123/balance-inquiry', [])
            ->andReturn(new Response(200, [], json_encode(['status' => 'successful', 'data' => ['balance' => 200000]])));

        $result = $this->mono->mandate()->balanceCheck('mmc_abc123');
        $this->assertArrayHasKey('balance', $result['data']);
    }

    public function test_balance_check_with_amount_appends_query(): void
    {
        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'v3/payments/mandates/mmc_abc123/balance-inquiry?amount=5000000', [])
            ->andReturn(new Response(200, [], json_encode(['status' => 'successful', 'data' => ['sufficient' => true]])));

        $result = $this->mono->mandate()->balanceCheck('mmc_abc123', 5000000);
        $this->assertTrue($result['data']['sufficient']);
    }

    // ── 404 → MonoNotFoundException ──────────────────────────────────────────

    public function test_fetch_nonexistent_mandate_throws_not_found(): void
    {
        $this->expectException(MonoNotFoundException::class);

        $guzzleRequest  = new Request('GET', 'v3/payments/mandates/invalid_id');
        $guzzleResponse = new Response(404, [], json_encode(['message' => 'Mandate not found']));

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->andThrow(new ClientException('Not Found', $guzzleRequest, $guzzleResponse));

        $this->mono->mandate()->fetch('invalid_id');
    }

    // ── list ─────────────────────────────────────────────────────────────────

    public function test_list_mandates(): void
    {
        $payload = ['status' => 'successful', 'data' => [['id' => 'mmc_1'], ['id' => 'mmc_2']]];

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->andReturn(new Response(200, [], json_encode($payload)));

        $result = $this->mono->mandate()->list();
        $this->assertCount(2, $result['data']);
    }
}
