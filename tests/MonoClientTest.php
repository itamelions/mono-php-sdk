<?php

namespace Mono\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Mockery;
use Mockery\MockInterface;
use Mono\Exceptions\MonoApiException;
use Mono\Exceptions\MonoConnectionException;
use Mono\Exceptions\MonoRateLimitException;
use Mono\Mono;
use PHPUnit\Framework\TestCase;

class MonoClientTest extends TestCase
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

    // ── 5xx handling ─────────────────────────────────────────────────────────

    public function test_5xx_response_throws_mono_api_exception(): void
    {
        $this->expectException(MonoApiException::class);
        $this->expectExceptionCode(500);

        $request  = new Request('GET', 'v3/payments/mandates/mmc_abc');
        $response = new Response(500, [], json_encode(['message' => 'Internal server error']));

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->andThrow(new ServerException('Server error', $request, $response));

        $this->mono->call('GET', 'v3/payments/mandates/mmc_abc');
    }

    public function test_5xx_message_extracted_from_response_body(): void
    {
        $request  = new Request('GET', 'v3/payments/mandates/mmc_abc');
        $response = new Response(503, [], json_encode(['message' => 'Service unavailable']));

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->andThrow(new ServerException('Server error', $request, $response));

        try {
            $this->mono->call('GET', 'v3/payments/mandates/mmc_abc');
            $this->fail('Expected MonoApiException');
        } catch (MonoApiException $e) {
            $this->assertEquals(503, $e->getCode());
            $this->assertEquals('Service unavailable', $e->getMessage());
        }
    }

    // ── invalid JSON ─────────────────────────────────────────────────────────

    public function test_non_json_response_throws_mono_api_exception(): void
    {
        $this->expectException(MonoApiException::class);
        $this->expectExceptionMessage('non-JSON response');

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->andReturn(new Response(200, [], '<html>Gateway error</html>'));

        $this->mono->call('GET', 'v3/payments/mandates/mmc_abc');
    }

    public function test_empty_body_does_not_throw_and_returns_empty_array(): void
    {
        $this->mockHttp->shouldReceive('request')
            ->once()
            ->andReturn(new Response(200, [], ''));

        $result = $this->mono->call('GET', 'v3/payments/mandates/mmc_abc');

        $this->assertSame([], $result);
    }

    // ── network failure ───────────────────────────────────────────────────────

    public function test_connection_failure_throws_mono_connection_exception(): void
    {
        $this->expectException(MonoConnectionException::class);

        $request = new Request('GET', 'v3/payments/mandates/mmc_abc');

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->andThrow(new ConnectException('Connection refused', $request));

        $this->mono->call('GET', 'v3/payments/mandates/mmc_abc');
    }

    public function test_connection_exception_is_subtype_of_mono_api_exception(): void
    {
        // Callers that catch MonoApiException must also catch connection errors.
        $request = new Request('GET', 'v3/payments/mandates/mmc_abc');

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->andThrow(new ConnectException('Timeout', $request));

        try {
            $this->mono->call('GET', 'v3/payments/mandates/mmc_abc');
            $this->fail('Expected MonoConnectionException');
        } catch (MonoApiException $e) {
            $this->assertInstanceOf(MonoConnectionException::class, $e);
        }
    }

    // ── 429 rate limiting ─────────────────────────────────────────────────────

    public function test_429_throws_mono_rate_limit_exception(): void
    {
        $this->expectException(MonoRateLimitException::class);
        $this->expectExceptionCode(429);

        $request  = new Request('GET', 'v3/payments/mandates/mmc_abc');
        $response = new Response(429, ['Retry-After' => ['60']], json_encode(['message' => 'Rate limit exceeded']));

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->andThrow(new ClientException('Too Many Requests', $request, $response));

        $this->mono->call('GET', 'v3/payments/mandates/mmc_abc');
    }

    public function test_429_retry_after_header_is_accessible(): void
    {
        $request  = new Request('GET', 'v3/payments/mandates/mmc_abc');
        $response = new Response(429, ['Retry-After' => ['30']], json_encode(['message' => 'Rate limit exceeded']));

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->andThrow(new ClientException('Too Many Requests', $request, $response));

        try {
            $this->mono->call('GET', 'v3/payments/mandates/mmc_abc');
            $this->fail('Expected MonoRateLimitException');
        } catch (MonoRateLimitException $e) {
            $this->assertEquals(30, $e->getRetryAfter());
        }
    }

    public function test_429_without_retry_after_header_returns_null(): void
    {
        $request  = new Request('GET', 'v3/payments/mandates/mmc_abc');
        $response = new Response(429, [], json_encode(['message' => 'Rate limit exceeded']));

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->andThrow(new ClientException('Too Many Requests', $request, $response));

        try {
            $this->mono->call('GET', 'v3/payments/mandates/mmc_abc');
            $this->fail('Expected MonoRateLimitException');
        } catch (MonoRateLimitException $e) {
            $this->assertNull($e->getRetryAfter());
        }
    }

    public function test_rate_limit_exception_is_subtype_of_mono_api_exception(): void
    {
        $request  = new Request('GET', 'v3/payments/mandates/mmc_abc');
        $response = new Response(429, [], json_encode(['message' => 'Rate limit exceeded']));

        $this->mockHttp->shouldReceive('request')
            ->once()
            ->andThrow(new ClientException('Too Many Requests', $request, $response));

        try {
            $this->mono->call('GET', 'v3/payments/mandates/mmc_abc');
            $this->fail('Expected MonoRateLimitException');
        } catch (MonoApiException $e) {
            $this->assertInstanceOf(MonoRateLimitException::class, $e);
        }
    }

    // ── constructor options ───────────────────────────────────────────────────

    public function test_custom_timeout_is_accepted(): void
    {
        // Verify the constructor does not throw and returns a usable instance.
        $mono = new Mono('test_key', ['timeout' => 60, 'connect_timeout' => 5]);
        $this->assertInstanceOf(Mono::class, $mono);
    }

    public function test_max_retries_option_is_accepted(): void
    {
        $mono = new Mono('test_key', ['max_retries' => 3]);
        $this->assertInstanceOf(Mono::class, $mono);
    }
}
