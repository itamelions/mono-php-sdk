<?php

namespace Mono\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Mockery;
use Mockery\MockInterface;
use Mono\Mono;
use PHPUnit\Framework\TestCase;

class DebitTest extends TestCase
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

    // ── charge ───────────────────────────────────────────────────────────────

    public function test_charge_posts_to_singular_debit_path(): void
    {
        $payload = [
            'status' => 'successful',
            'data'   => ['reference' => 'ref-001', 'status' => 'successful', 'amount' => 500000],
        ];

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('POST', 'v3/payments/mandates/mmc_abc/debit', Mockery::type('array'))
            ->andReturn(new Response(200, [], json_encode($payload)));

        $result = $this->mono->debit()->charge('mmc_abc', [
            'amount'    => 500000,
            'reference' => 'ref-001',
            'narration' => 'Loan repayment',
        ]);

        $this->assertEquals('successful', $result['status']);
        $this->assertEquals('ref-001', $result['data']['reference']);
    }

    // ── fetch ────────────────────────────────────────────────────────────────

    public function test_fetch_uses_singular_debit_path(): void
    {
        $payload = [
            'status' => 'successful',
            'data'   => ['reference' => 'ref-001', 'status' => 'successful'],
        ];

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'v3/payments/mandates/mmc_abc/debit/ref-001', [])
            ->andReturn(new Response(200, [], json_encode($payload)));

        $result = $this->mono->debit()->fetch('mmc_abc', 'ref-001');

        $this->assertEquals('successful', $result['status']);
        $this->assertEquals('ref-001', $result['data']['reference']);
    }

    public function test_fetch_path_differs_from_list_path(): void
    {
        // Explicit guard: single-fetch is /debit/{ref} (singular),
        // not /debits/{ref} (plural), which would 404 on Mono's API.
        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'v3/payments/mandates/mmc_xyz/debit/my-ref', [])
            ->andReturn(new Response(200, [], json_encode(['status' => 'successful', 'data' => []])));

        $this->mono->debit()->fetch('mmc_xyz', 'my-ref');

        // Mockery will fail the test if the path does not match exactly.
        $this->assertTrue(true);
    }

    // ── all ──────────────────────────────────────────────────────────────────

    public function test_all_uses_plural_debits_path(): void
    {
        $payload = [
            'status' => 'successful',
            'data'   => [['reference' => 'ref-001'], ['reference' => 'ref-002']],
        ];

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'v3/payments/mandates/mmc_abc/debits', [])
            ->andReturn(new Response(200, [], json_encode($payload)));

        $result = $this->mono->debit()->all('mmc_abc');

        $this->assertCount(2, $result['data']);
    }

    public function test_all_appends_pagination_query(): void
    {
        $this->mockHttp->shouldReceive('request')
            ->once()
            ->with('GET', 'v3/payments/mandates/mmc_abc/debits?page=2&limit=10', [])
            ->andReturn(new Response(200, [], json_encode(['status' => 'successful', 'data' => []])));

        $this->mono->debit()->all('mmc_abc', ['page' => 2, 'limit' => 10]);

        // Mockery path expectation enforces the query string is appended correctly.
        $this->assertTrue(true);
    }
}
