<?php

namespace Mono\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Mockery;
use Mockery\MockInterface;
use Mono\Mono;
use PHPUnit\Framework\TestCase;

class DisbursementTest extends TestCase
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

    public function test_create_source_account(): void
    {
        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('POST', 'v3/payments/disburse/source-accounts', Mockery::type('array'))
            ->andReturn(new Response(200, [], json_encode(['status' => 'successful', 'data' => ['id' => 'src_1']])));

        $result = $this->mono->disbursements()->createSourceAccount([
            'app'            => '62229d670c34e0c3b9139f44',
            'account_number' => '1122334455',
            'bank_code'      => '044',
            'email'          => 'support@merchant.co',
        ]);
        $this->assertEquals('src_1', $result['data']['id']);
    }

    public function test_list_and_get_source_accounts(): void
    {
        $payload = ['status' => 'successful', 'data' => [['id' => 'src_1']]];

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'v3/payments/disburse/source-accounts', [])
            ->andReturn(new Response(200, [], json_encode($payload)));

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'v3/payments/disburse/source-accounts/src_1', [])
            ->andReturn(new Response(200, [], json_encode(['status' => 'successful', 'data' => ['id' => 'src_1']])));

        $this->assertCount(1, $this->mono->disbursements()->sourceAccounts()['data']);
        $this->assertEquals('src_1', $this->mono->disbursements()->getSourceAccount('src_1')['data']['id']);
    }

    public function test_create_instant_disbursement_defaults_type(): void
    {
        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('POST', 'v3/payments/disburse/disbursements', Mockery::on(function (array $opts) {
                return ($opts['json']['type'] ?? null) === 'instant';
            }))
            ->andReturn(new Response(200, [], json_encode(['status' => 'successful'])));

        $result = $this->mono->disbursements()->createInstant(['reference' => 'disburse_ref_12345']);
        $this->assertEquals('successful', $result['status']);
    }

    public function test_create_scheduled_disbursement_defaults_type(): void
    {
        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('POST', 'v3/payments/disburse/disbursements', Mockery::on(function (array $opts) {
                return ($opts['json']['type'] ?? null) === 'scheduled';
            }))
            ->andReturn(new Response(200, [], json_encode(['status' => 'successful'])));

        $result = $this->mono->disbursements()->createScheduled(['reference' => 'disburse_ref_67890']);
        $this->assertEquals('successful', $result['status']);
    }

    public function test_list_and_get_disbursements(): void
    {
        $payload = ['status' => 'successful', 'data' => [['id' => 'batch_1']]];

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'v3/payments/disburse/disbursements', [])
            ->andReturn(new Response(200, [], json_encode($payload)));

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'v3/payments/disburse/disbursements/batch_1', [])
            ->andReturn(new Response(200, [], json_encode(['status' => 'successful', 'data' => ['id' => 'batch_1']])));

        $this->assertCount(1, $this->mono->disbursements()->listDisbursements()['data']);
        $this->assertEquals('batch_1', $this->mono->disbursements()->getDisbursement('batch_1')['data']['id']);
    }

    public function test_transition_trigger_action(): void
    {
        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('POST', 'v3/payments/disburse/disbursements/batch_1/transition', Mockery::on(function (array $opts) {
                return ($opts['json']['action'] ?? null) === 'trigger';
            }))
            ->andReturn(new Response(200, [], json_encode(['status' => 'successful'])));

        $result = $this->mono->disbursements()->retryDisbursement('batch_1');
        $this->assertEquals('successful', $result['status']);
    }

    public function test_distribution_crud_uses_correct_paths(): void
    {
        $payload = ['status' => 'successful', 'data' => [['id' => 'dist_1']]];

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('POST', 'v3/payments/disburse/disbursements/batch_1/distributions', Mockery::type('array'))
            ->andReturn(new Response(200, [], json_encode($payload)));

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('PATCH', 'v3/payments/disburse/disbursements/batch_1/distributions/dist_1', Mockery::type('array'))
            ->andReturn(new Response(200, [], json_encode(['status' => 'successful'])));

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('DELETE', 'v3/payments/disburse/disbursements/batch_1/distributions/dist_1', [])
            ->andReturn(new Response(200, [], json_encode(['status' => 'successful'])));

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'v3/payments/disburse/disbursements/batch_1/distributions', [])
            ->andReturn(new Response(200, [], json_encode($payload)));

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'v3/payments/disburse/disbursements/batch_1/distributions/dist_1', [])
            ->andReturn(new Response(200, [], json_encode(['status' => 'successful', 'data' => ['id' => 'dist_1']])));

        $this->mono->disbursements()->addDistributions('batch_1', [['account_number' => '9876543210']]);
        $this->mono->disbursements()->updateDistribution('batch_1', 'dist_1', ['amount' => 1000]);
        $this->mono->disbursements()->deleteDistribution('batch_1', 'dist_1');
        $this->assertCount(1, $this->mono->disbursements()->distributions('batch_1')['data']);
        $this->assertEquals('dist_1', $this->mono->disbursements()->getDistribution('batch_1', 'dist_1')['data']['id']);
    }
}
