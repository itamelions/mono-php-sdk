<?php

namespace Mono\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Mockery;
use Mockery\MockInterface;
use Mono\Mono;
use PHPUnit\Framework\TestCase;

class BankTest extends TestCase
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

    public function test_list_banks(): void
    {
        $payload = ['status' => 'successful', 'data' => [['id' => 'bank_1']]];

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'v3/banks/list', [])
            ->andReturn(new Response(200, [], json_encode($payload)));

        $result = $this->mono->banks()->list();
        $this->assertCount(1, $result['data']);
    }

    public function test_coverage_hits_institutions_path(): void
    {
        $payload = ['status' => 'successful', 'data' => [['institution' => 'GTBank']]];

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'v3/institutions', [])
            ->andReturn(new Response(200, [], json_encode($payload)));

        $result = $this->mono->banks()->getBankCoverage();
        $this->assertCount(1, $result['data']);
    }

    public function test_coverage_appends_query(): void
    {
        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'v3/institutions?product=directpay', [])
            ->andReturn(new Response(200, [], json_encode(['status' => 'successful', 'data' => []])));

        $this->mono->banks()->coverage(['product' => 'directpay']);
        $this->assertTrue(true);
    }

    public function test_nip_banks_hit_lookup_banks_path(): void
    {
        $payload = ['status' => 'successful', 'data' => [['nip_code' => '000013']]];

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'v3/lookup/banks', [])
            ->andReturn(new Response(200, [], json_encode($payload)));

        $result = $this->mono->banks()->nip();
        $this->assertCount(1, $result['data']);
    }

    public function test_lookup_account_number(): void
    {
        $payload = ['status' => 'successful', 'data' => ['account_name' => 'John Doe', 'bvn' => '221*****345']];

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('POST', 'v3/lookup/account-number', Mockery::type('array'))
            ->andReturn(new Response(200, [], json_encode($payload)));

        $result = $this->mono->banks()->lookupAccountNumber(['nip_code' => '044', 'account_number' => '1234567890']);
        $this->assertEquals('John Doe', $result['data']['account_name']);
    }
}
