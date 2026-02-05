<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    /**
     * Test that health check returns ok when services are healthy.
     */
    public function test_health_check_returns_ok_when_services_are_healthy(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'ok',
            ])
            ->assertJsonStructure([
                'status',
                'checks' => ['database', 'redis'],
                'timestamp',
            ]);
    }

    /**
     * Test that health check database check returns boolean.
     */
    public function test_health_check_database_check_returns_boolean(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertStatus(200);

        $data = $response->json();
        $this->assertIsBool($data['checks']['database']);
    }

    /**
     * Test that health check redis check returns boolean.
     */
    public function test_health_check_redis_check_returns_boolean(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertStatus(200);

        $data = $response->json();
        $this->assertIsBool($data['checks']['redis']);
    }

    /**
     * Test that health check timestamp is valid ISO 8601.
     */
    public function test_health_check_timestamp_is_valid_iso_8601(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertStatus(200);

        $data = $response->json();
        $timestamp = \DateTime::createFromFormat(\DateTime::ATOM, $data['timestamp']);
        $this->assertNotFalse($timestamp);
    }

    /**
     * Test that health check returns degraded when database is down.
     */
    public function test_health_check_returns_degraded_when_database_is_down(): void
    {
        Log::spy();

        DB::shouldReceive('select')
            ->once()
            ->with('SELECT 1')
            ->andThrow(new \RuntimeException('Connection refused'));

        $response = $this->getJson('/api/health');

        $response->assertStatus(503)
            ->assertJson([
                'status' => 'degraded',
                'checks' => ['database' => false],
            ]);

        Log::shouldHaveReceived('error')
            ->with('Health check: Database connection failed', \Mockery::type('array'));
    }

    /**
     * Test that health check returns degraded when Redis is down.
     */
    public function test_health_check_returns_degraded_when_redis_is_down(): void
    {
        Log::spy();

        $cacheStoreMock = \Mockery::mock(\Illuminate\Contracts\Cache\Repository::class);
        $cacheStoreMock->shouldReceive('put')
            ->once()
            ->andThrow(new \RuntimeException('Connection refused'));

        Cache::shouldReceive('store')
            ->with('redis')
            ->andReturn($cacheStoreMock);

        $response = $this->getJson('/api/health');

        $response->assertStatus(503)
            ->assertJson([
                'status' => 'degraded',
                'checks' => ['redis' => false],
            ]);

        Log::shouldHaveReceived('error')
            ->with('Health check: Redis connection failed', \Mockery::type('array'));
    }

    /**
     * Test that health check returns degraded when Redis write-read verification fails.
     */
    public function test_health_check_returns_degraded_when_redis_verification_fails(): void
    {
        Log::spy();

        $cacheStoreMock = \Mockery::mock(\Illuminate\Contracts\Cache\Repository::class);
        $cacheStoreMock->shouldReceive('put')
            ->once()
            ->with('health_check', true, 10)
            ->andReturn(true);
        $cacheStoreMock->shouldReceive('get')
            ->once()
            ->with('health_check')
            ->andReturn(null); // Simulate failed retrieval

        Cache::shouldReceive('store')
            ->with('redis')
            ->andReturn($cacheStoreMock);

        $response = $this->getJson('/api/health');

        $response->assertStatus(503)
            ->assertJson([
                'status' => 'degraded',
                'checks' => ['redis' => false],
            ]);

        Log::shouldHaveReceived('warning')
            ->with('Health check: Redis write-read verification failed', \Mockery::type('array'));
    }

    /**
     * Test that database errors are logged with proper context.
     */
    public function test_database_failure_is_logged_with_context(): void
    {
        Log::spy();

        DB::shouldReceive('select')
            ->once()
            ->with('SELECT 1')
            ->andThrow(new \RuntimeException('SQLSTATE[HY000]: Connection refused'));

        $this->getJson('/api/health');

        Log::shouldHaveReceived('error')
            ->withArgs(function (string $message, array $context) {
                return $message === 'Health check: Database connection failed'
                    && isset($context['exception'])
                    && isset($context['message'])
                    && isset($context['connection']);
            });
    }

    /**
     * Test that Redis errors are logged with proper context.
     */
    public function test_redis_failure_is_logged_with_context(): void
    {
        Log::spy();

        $cacheStoreMock = \Mockery::mock(\Illuminate\Contracts\Cache\Repository::class);
        $cacheStoreMock->shouldReceive('put')
            ->once()
            ->andThrow(new \RuntimeException('Connection to Redis server failed'));

        Cache::shouldReceive('store')
            ->with('redis')
            ->andReturn($cacheStoreMock);

        $this->getJson('/api/health');

        Log::shouldHaveReceived('error')
            ->withArgs(function (string $message, array $context) {
                return $message === 'Health check: Redis connection failed'
                    && isset($context['exception'])
                    && isset($context['message'])
                    && isset($context['store']);
            });
    }
}
