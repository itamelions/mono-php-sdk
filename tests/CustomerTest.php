<?php

namespace Mono\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Mockery;
use Mockery\MockInterface;
use Mono\Mono;
use PHPUnit\Framework\TestCase;

class CustomerTest extends TestCase
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

    public function test_create_individual_customer(): void
    {
        $payload = ['status' => 'successful', 'data' => ['id' => 'cus_123']];

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('POST', 'v2/customers', Mockery::type('array'))
            ->andReturn(new Response(200, [], json_encode($payload)));

        $result = $this->mono->customers()->create([
            'email'      => 'john@example.com',
            'first_name' => 'John',
            'last_name'  => 'Doe',
            'phone'      => '+2348000000000',
        ]);
        $this->assertEquals('cus_123', $result['data']['id']);
    }

    public function test_create_individual_is_alias_of_create(): void
    {
        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('POST', 'v2/customers', Mockery::type('array'))
            ->andReturn(new Response(200, [], json_encode(['status' => 'successful'])));

        $this->mono->customers()->createIndividual(['email' => 'john@example.com']);
        $this->assertTrue(true);
    }

    public function test_create_business_customer(): void
    {
        $payload = ['status' => 'successful', 'data' => ['id' => 'cus_biz_1']];

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('POST', 'v2/customers', Mockery::on(function (array $opts) {
                return ($opts['json']['business_name'] ?? null) === 'Acme Inc';
            }))
            ->andReturn(new Response(200, [], json_encode($payload)));

        $result = $this->mono->customers()->createBusiness([
            'email'         => 'biz@acme.com',
            'business_name' => 'Acme Inc',
            'phone'         => '+2348000000000',
        ]);
        $this->assertEquals('cus_biz_1', $result['data']['id']);
    }

    public function test_delete_customer(): void
    {
        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('DELETE', 'v2/customers/cus_123', [])
            ->andReturn(new Response(200, [], json_encode(['status' => 'successful'])));

        $result = $this->mono->customers()->delete('cus_123');
        $this->assertEquals('successful', $result['status']);
    }

    public function test_customer_transactions(): void
    {
        $payload = ['status' => 'successful', 'data' => [['reference' => 'ref-001']]];

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'v2/customers/cus_123/transactions?period=last12months&page=1', [])
            ->andReturn(new Response(200, [], json_encode($payload)));

        $result = $this->mono->customers()->transactions('cus_123', ['period' => 'last12months', 'page' => 1]);
        $this->assertCount(1, $result['data']);
    }

    public function test_customer_linked_accounts_hits_accounts_path(): void
    {
        $payload = ['status' => 'successful', 'data' => [['id' => 'acc_1']]];

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'v2/accounts', [])
            ->andReturn(new Response(200, [], json_encode($payload)));

        $result = $this->mono->customers()->linkedAccounts('cus_123');
        $this->assertCount(1, $result['data']);
    }

    public function test_accounts_all_lists_linked_accounts(): void
    {
        $payload = ['status' => 'successful', 'data' => [['id' => 'acc_1'], ['id' => 'acc_2']]];

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'v2/accounts', [])
            ->andReturn(new Response(200, [], json_encode($payload)));

        $result = $this->mono->accounts()->all();
        $this->assertCount(2, $result['data']);
    }
}
