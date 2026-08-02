<?php

namespace Mono\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Mockery;
use Mockery\MockInterface;
use Mono\Mono;
use PHPUnit\Framework\TestCase;

class WhatsAppPaymentTest extends TestCase
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

    public function test_user_status_uses_phone_query(): void
    {
        $payload = ['status' => 'successful', 'data' => ['status' => 'active']];

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'owo/v1/users/status?phone=2349136923755', [])
            ->andReturn(new Response(200, [], json_encode($payload)));

        $result = $this->mono->whatsapp()->userStatus('2349136923755');
        $this->assertEquals('active', $result['data']['status']);
    }

    public function test_link_and_unlink_beneficiary(): void
    {
        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('POST', 'owo/v1/beneficiaries/link', Mockery::type('array'))
            ->andReturn(new Response(200, [], json_encode(['status' => 'successful'])));

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('POST', 'owo/v1/beneficiaries/unlink', Mockery::on(function (array $opts) {
                return ($opts['json']['id'] ?? null) === 'ben_b0TSrl7gucHg';
            }))
            ->andReturn(new Response(200, [], json_encode(['status' => 'successful'])));

        $this->mono->whatsapp()->linkBeneficiary(['phone' => '2349136923755']);
        $this->mono->whatsapp()->unlinkBeneficiary(['id' => 'ben_b0TSrl7gucHg']);
        $this->assertTrue(true);
    }

    public function test_get_and_list_beneficiaries(): void
    {
        $payload = ['status' => 'successful', 'data' => [['id' => 'ben_1']]];

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'owo/v1/beneficiaries/ben_1', [])
            ->andReturn(new Response(200, [], json_encode($payload)));

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'owo/v1/beneficiaries', [])
            ->andReturn(new Response(200, [], json_encode($payload)));

        $this->mono->whatsapp()->getBeneficiary('ben_1');
        $this->assertCount(1, $this->mono->whatsapp()->beneficiaries()['data']);
    }

    public function test_create_one_time_fund_request_defaults_type(): void
    {
        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('POST', 'owo/v1/fund-requests', Mockery::on(function (array $opts) {
                return ($opts['json']['type'] ?? null) === 'onetime';
            }))
            ->andReturn(new Response(200, [], json_encode(['status' => 'successful'])));

        $result = $this->mono->whatsapp()->createOneTimeFundRequest(['phone' => '2349136923755', 'amount' => 20000]);
        $this->assertEquals('successful', $result['status']);
    }

    public function test_create_recurring_fund_request_defaults_type(): void
    {
        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('POST', 'owo/v1/fund-requests', Mockery::on(function (array $opts) {
                return ($opts['json']['type'] ?? null) === 'recurring';
            }))
            ->andReturn(new Response(200, [], json_encode(['status' => 'successful'])));

        $result = $this->mono->whatsapp()->createRecurringFundRequest(['phone' => '2349136923755', 'amount' => 67877]);
        $this->assertEquals('successful', $result['status']);
    }

    public function test_create_whatsapp_payment_is_generic_fund_request(): void
    {
        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('POST', 'owo/v1/fund-requests', Mockery::type('array'))
            ->andReturn(new Response(200, [], json_encode(['status' => 'successful', 'data' => ['id' => 'fr_1']])));

        $result = $this->mono->whatsapp()->createWhatsappPayment(['phone' => '2349136923755']);
        $this->assertEquals('fr_1', $result['data']['id']);
    }

    public function test_fund_requests_and_get_by_id(): void
    {
        $payload = ['status' => 'successful', 'data' => ['id' => 'fr_1']];

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'owo/v1/fund-requests', [])
            ->andReturn(new Response(200, [], json_encode($payload)));

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'owo/v1/fund-requests/fr_1', [])
            ->andReturn(new Response(200, [], json_encode($payload)));

        $this->mono->whatsapp()->fundRequests();
        $this->assertEquals('fr_1', $this->mono->whatsapp()->getWhatsappPayment('fr_1')['data']['id']);
    }

    public function test_fund_request_payments(): void
    {
        $payload = ['status' => 'successful', 'data' => [['id' => 'pay_1']]];

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'owo/v1/fund-requests/fr_1/payments', [])
            ->andReturn(new Response(200, [], json_encode($payload)));

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'owo/v1/fund-requests/fr_1/payments/pay_1', [])
            ->andReturn(new Response(200, [], json_encode(['status' => 'successful', 'data' => ['id' => 'pay_1']])));

        $this->assertCount(1, $this->mono->whatsapp()->payments('fr_1')['data']);
        $this->assertEquals('pay_1', $this->mono->whatsapp()->getPayment('fr_1', 'pay_1')['data']['id']);
    }
}
