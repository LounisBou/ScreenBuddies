<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Sentry\State\HubInterface;
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
     * Test that environment config defaults to app.env.
     */
    public function test_environment_defaults_to_app_env(): void
    {
        $this->assertEquals(
            config('app.env'),
            config('sentry.environment')
        );
    }

    /**
     * Test that ValidationException should not be reported to Sentry.
     */
    public function test_validation_exceptions_are_not_reported(): void
    {
        $handler = $this->app->make(ExceptionHandler::class);

        $exception = ValidationException::withMessages(['test' => 'error']);
        $this->assertFalse($handler->shouldReport($exception));
    }

    /**
     * Test that NotFoundHttpException should not be reported to Sentry.
     */
    public function test_not_found_exceptions_are_not_reported(): void
    {
        $handler = $this->app->make(ExceptionHandler::class);

        $exception = new NotFoundHttpException('Not found');
        $this->assertFalse($handler->shouldReport($exception));
    }

    /**
     * Test that regular exceptions should be reported to Sentry.
     */
    public function test_regular_exceptions_are_reported(): void
    {
        $handler = $this->app->make(ExceptionHandler::class);

        $exception = new RuntimeException('Test error');
        $this->assertTrue($handler->shouldReport($exception));
    }

    /**
     * Test that the health check endpoint still works with Sentry integration.
     */
    public function test_health_check_endpoint_works(): void
    {
        $response = $this->get('/up');

        $response->assertStatus(200);
    }

    /**
     * Test that Sentry Hub is created when DSN is empty (testing environment).
     */
    public function test_sentry_hub_is_created_when_dsn_is_empty(): void
    {
        // DSN is empty in testing environment (via phpunit.xml)
        $hub = $this->app->make(HubInterface::class);

        $this->assertInstanceOf(HubInterface::class, $hub);
        // Empty hub has no client
        $this->assertNull($hub->getClient());
    }

    /**
     * Test that Sentry alias resolves to HubInterface.
     */
    public function test_sentry_alias_resolves_to_hub(): void
    {
        $hub = $this->app->make('sentry');

        $this->assertInstanceOf(HubInterface::class, $hub);
    }

    /**
     * Test that tracing default integrations are disabled for Laravel 12 compatibility.
     */
    public function test_tracing_default_integrations_are_disabled(): void
    {
        $tracingConfig = config('sentry.tracing');

        $this->assertIsArray($tracingConfig);
        $this->assertFalse($tracingConfig['default_integrations']);
    }
}
