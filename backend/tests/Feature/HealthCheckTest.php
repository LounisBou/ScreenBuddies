<?php

declare(strict_types=1);

namespace Tests\Feature;

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
}
