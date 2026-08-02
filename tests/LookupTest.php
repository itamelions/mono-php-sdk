<?php

namespace Mono\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Mockery;
use Mockery\MockInterface;
use Mono\Mono;
use PHPUnit\Framework\TestCase;

class LookupTest extends TestCase
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

    public function test_lookup_bvn_posts_to_initiate_path(): void
    {
        $payload = ['status' => 'successful', 'data' => ['session_id' => 'sess_1']];

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('POST', 'v2/lookup/bvn/initiate', Mockery::type('array'))
            ->andReturn(new Response(200, [], json_encode($payload)));

        $result = $this->mono->identity()->lookupBVN(['bvn' => '22110033445']);
        $this->assertEquals('sess_1', $result['data']['session_id']);
    }

    public function test_verify_and_fetch_bvn(): void
    {
        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('POST', 'v2/lookup/bvn/verify', Mockery::type('array'))
            ->andReturn(new Response(200, [], json_encode(['status' => 'successful'])));

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('POST', 'v2/lookup/bvn/fetch', Mockery::type('array'))
            ->andReturn(new Response(200, [], json_encode(['status' => 'successful'])));

        $this->mono->identity()->verifyBVN(['method' => 'phone', 'phone_number' => '08012345678']);
        $this->mono->identity()->fetchBVN(['session_id' => 'sess_1']);
        $this->assertTrue(true);
    }

    public function test_cac_lookup_and_company_endpoints(): void
    {
        $payload = ['status' => 'successful', 'data' => ['id' => 'RC123456']];

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'v3/lookup/cac?search=mono', [])
            ->andReturn(new Response(200, [], json_encode($payload)));

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'v3/lookup/cac/company/RC123456', [])
            ->andReturn(new Response(200, [], json_encode($payload)));

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'v3/lookup/cac/company/RC123456/psc', [])
            ->andReturn(new Response(200, [], json_encode($payload)));

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'v3/lookup/cac/company/RC123456/secretary', [])
            ->andReturn(new Response(200, [], json_encode($payload)));

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'v3/lookup/cac/company/RC123456/directors', [])
            ->andReturn(new Response(200, [], json_encode($payload)));

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'v3/lookup/cac/profile/RC123456', [])
            ->andReturn(new Response(200, [], json_encode($payload)));

        $result = $this->mono->lookup()->lookupCAC(['search' => 'mono']);
        $this->assertEquals('RC123456', $result['data']['id']);

        $this->mono->lookup()->cacShareholders('RC123456');
        $this->mono->lookup()->cacPSC('RC123456');
        $this->mono->lookup()->cacSecretary('RC123456');
        $this->mono->lookup()->cacDirectors('RC123456');
        $this->mono->lookup()->cacProfile('RC123456');
        $this->assertTrue(true);
    }

    public function test_cac_status_report_returns_raw_binary(): void
    {
        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'v3/lookup/cac/company/RC123456/status-report', [])
            ->andReturn(new Response(200, ['Content-Type' => 'application/pdf'], '%PDF-1.4 binary'));

        $result = $this->mono->lookup()->cacStatusReport('RC123456');
        $this->assertSame('%PDF-1.4 binary', $result);
    }

    public function test_watchlist_screening_endpoints(): void
    {
        $payload = ['status' => 'successful', 'data' => ['id' => 'wl_1']];

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('POST', 'v3/lookup/watchlist', Mockery::type('array'))
            ->andReturn(new Response(200, [], json_encode($payload)));

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('POST', 'v3/lookup/watchlist/batch', Mockery::on(function (array $opts) {
                return isset($opts['json']['entries']) && is_array($opts['json']['entries']);
            }))
            ->andReturn(new Response(200, [], json_encode($payload)));

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'v3/lookup/watchlist/wl_1', [])
            ->andReturn(new Response(200, [], json_encode($payload)));

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'v3/lookup/watchlist/wl_1/audit-log', [])
            ->andReturn(new Response(200, [], json_encode($payload)));

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('POST', 'v3/lookup/watchlist/monitor', Mockery::type('array'))
            ->andReturn(new Response(200, [], json_encode($payload)));

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('DELETE', 'v3/lookup/watchlist/monitor/wl_1', [])
            ->andReturn(new Response(200, [], json_encode(['status' => 'successful'])));

        $entry = ['type' => 'individual', 'name' => 'Amina Kolade', 'date_of_birth' => '1941-08-17', 'country' => 'ng'];

        $this->mono->lookup()->watchlist($entry);
        $this->mono->lookup()->watchlistBatch([$entry]);
        $this->mono->lookup()->watchlistResult('wl_1');
        $this->mono->lookup()->watchlistAuditLog('wl_1');
        $this->mono->lookup()->startMonitoring($entry);
        $this->mono->lookup()->stopMonitoring('wl_1');
        $this->assertTrue(true);
    }

    public function test_watchlist_report_returns_raw_binary(): void
    {
        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'v3/lookup/watchlist/wl_1/report', [])
            ->andReturn(new Response(200, ['Content-Type' => 'application/pdf'], '%PDF-1.4'));

        $result = $this->mono->lookup()->watchlistReport('wl_1');
        $this->assertSame('%PDF-1.4', $result);
    }

    public function test_individual_lookup_endpoints(): void
    {
        $payload = ['status' => 'successful', 'data' => []];

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('POST', 'v3/lookup/nin', Mockery::type('array'))
            ->andReturn(new Response(200, [], json_encode($payload)));

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'v3/lookup/nin/job_1/job', [])
            ->andReturn(new Response(200, [], json_encode($payload)));

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('POST', 'v3/lookup/tin', Mockery::type('array'))
            ->andReturn(new Response(200, [], json_encode($payload)));

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('POST', 'v3/lookup/passport', Mockery::type('array'))
            ->andReturn(new Response(200, [], json_encode($payload)));

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('POST', 'v3/lookup/driver_license', Mockery::type('array'))
            ->andReturn(new Response(200, [], json_encode($payload)));

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('POST', 'v3/lookup/address', Mockery::type('array'))
            ->andReturn(new Response(200, [], json_encode($payload)));

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('POST', 'v3/lookup/account-number', Mockery::type('array'))
            ->andReturn(new Response(200, [], json_encode($payload)));

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('POST', 'v3/lookup/credit-history/crc', Mockery::type('array'))
            ->andReturn(new Response(200, [], json_encode($payload)));

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('POST', 'v3/lookup/mashup', Mockery::type('array'))
            ->andReturn(new Response(200, [], json_encode($payload)));

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'v3/lookup/banks', [])
            ->andReturn(new Response(200, [], json_encode($payload)));

        $this->mono->identity()->nin(['nin' => '12345678901']);
        $this->mono->identity()->pollNINJob('job_1');
        $this->mono->identity()->verifyTIN(['number' => '12345678', 'channel' => 'TIN']);
        $this->mono->identity()->verifyPassport(['passport_number' => 'A1234567']);
        $this->mono->identity()->verifyDriversLicense(['license_number' => 'DL1234']);
        $this->mono->identity()->verifyAddress(['meter_number' => '123', 'address' => 'x', 'disco_code' => 'EKO']);
        $this->mono->identity()->lookupAccountNumber(['nip_code' => '044', 'account_number' => '1234567890']);
        $this->mono->identity()->verifyCreditHistory('crc', ['bvn' => '224*****012']);
        $this->mono->identity()->lookupMashup(['nin' => '1', 'bvn' => '2']);
        $this->mono->identity()->banks();
        $this->assertTrue(true);
    }

    public function test_friendly_lookup_aliases(): void
    {
        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'v3/lookup/cac?search=mono', [])
            ->andReturn(new Response(200, [], json_encode(['status' => 'successful'])));

        $this->mono->identity()->verifyCAC(['search' => 'mono']);
        $this->assertTrue(true);
    }
}
