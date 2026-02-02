<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class SentryIntegrationTest extends TestCase
{
    /**
     * Test that Sentry configuration is loaded.
     */
    public function test_sentry_config_is_loaded(): void
    {
        $this->assertNotNull(config('sentry'));
        $this->assertArrayHasKey('dsn', config('sentry'));
        $this->assertArrayHasKey('environment', config('sentry'));
        $this->assertArrayHasKey('release', config('sentry'));
    }

    /**
     * Test that PII sending is disabled by default.
     */
    public function test_pii_sending_is_disabled(): void
    {
        $this->assertFalse(config('sentry.send_default_pii'));
    }

    /**
     * Test that breadcrumbs are configured.
     */
    public function test_breadcrumbs_are_configured(): void
    {
        $breadcrumbs = config('sentry.breadcrumbs');

        $this->assertIsArray($breadcrumbs);
        $this->assertTrue($breadcrumbs['logs']);
        $this->assertTrue($breadcrumbs['sql_queries']);
        $this->assertFalse($breadcrumbs['sql_bindings']);
        $this->assertTrue($breadcrumbs['queue_info']);
        $this->assertTrue($breadcrumbs['command_info']);
    }

    /**
     * Test that traces sample rate is configured based on environment.
     */
    public function test_traces_sample_rate_is_configured(): void
    {
        $sampleRate = config('sentry.traces_sample_rate');

        // In testing environment, should be 0 (disabled via phpunit.xml)
        $this->assertEquals(0.0, $sampleRate);
    }

    /**
     * Test that ValidationException is in the dontReport list.
     */
    public function test_validation_exceptions_are_not_reported(): void
    {
        $handler = $this->app->make(\Illuminate\Contracts\Debug\ExceptionHandler::class);

        // Check that ValidationException should not be reported
        $reflection = new \ReflectionClass($handler);

        // Get the internal dontReport property
        if ($reflection->hasProperty('internalDontReport')) {
            $property = $reflection->getProperty('internalDontReport');
            $property->setAccessible(true);
            $dontReport = $property->getValue($handler);

            $this->assertContains(ValidationException::class, $dontReport);
        } else {
            // For newer Laravel versions, check via shouldReport method
            $exception = ValidationException::withMessages(['test' => 'error']);
            $this->assertFalse($handler->shouldReport($exception));
        }
    }

    /**
     * Test that NotFoundHttpException is in the dontReport list.
     */
    public function test_not_found_exceptions_are_not_reported(): void
    {
        $handler = $this->app->make(\Illuminate\Contracts\Debug\ExceptionHandler::class);

        $exception = new NotFoundHttpException('Not found');
        $this->assertFalse($handler->shouldReport($exception));
    }

    /**
     * Test that the health check endpoint still works.
     */
    public function test_health_check_endpoint_works(): void
    {
        $response = $this->get('/up');

        $response->assertStatus(200);
    }
}
