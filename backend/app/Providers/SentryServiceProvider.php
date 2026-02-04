<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Sentry\ClientBuilder;
use Sentry\Dsn;
use Sentry\Laravel\Version;
use Sentry\SentrySdk;
use Sentry\State\Hub;
use Sentry\State\HubInterface;
use Throwable;

/**
 * Custom Sentry service provider for Laravel 12 compatibility.
 *
 * This provider bypasses the official Sentry ServiceProvider which has
 * Laravel 12 compatibility issues due to TracingServiceProvider referencing
 * Illuminate\Foundation\Http\Middleware\ValidateCsrfToken (moved in Laravel 12).
 */
class SentryServiceProvider extends ServiceProvider
{
    /**
     * Register the Sentry service provider.
     *
     * Configures the Sentry Hub singleton with proper error handling.
     * Falls back to an empty hub if DSN is missing or invalid.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(config_path('sentry.php'), 'sentry');

        $this->app->singleton(HubInterface::class, function () {
            return $this->createSentryHub();
        });

        $this->app->alias(HubInterface::class, 'sentry');
    }

    /**
     * Boot the service provider.
     *
     * Eagerly initializes the Sentry hub to catch early errors.
     */
    public function boot(): void
    {
        try {
            // Initialize the hub on boot
            $this->app->make(HubInterface::class);
        } catch (Throwable $e) {
            // Sentry initialization failed - already logged in createSentryHub()
            // Application should continue without Sentry
            Log::error('Sentry failed to initialize during boot.', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Create and configure the Sentry Hub.
     *
     * @return HubInterface The configured hub or an empty hub on failure
     */
    private function createSentryHub(): HubInterface
    {
        $dsn = config('sentry.dsn');

        // If no DSN is set, return an empty hub with appropriate logging
        if (empty(trim($dsn ?? ''))) {
            return $this->createEmptyHub('DSN is not configured');
        }

        // Validate DSN format before proceeding
        try {
            Dsn::createFromString($dsn);
        } catch (Throwable $e) {
            Log::error('Invalid Sentry DSN format. Error monitoring is DISABLED.', [
                'error' => $e->getMessage(),
            ]);

            return $this->createEmptyHub('Invalid DSN format');
        }

        // Try to create the Sentry client
        try {
            $options = [
                'dsn' => $dsn,
                'environment' => config('sentry.environment', config('app.env')),
                'release' => config('sentry.release'),
                'send_default_pii' => config('sentry.send_default_pii', false),
                'traces_sample_rate' => config('sentry.traces_sample_rate', 0.0),
                'profiles_sample_rate' => config('sentry.profiles_sample_rate', 1.0),
                'prefixes' => [base_path()],
                'in_app_exclude' => [
                    base_path('vendor'),
                    base_path('artisan'),
                ],
            ];

            $clientBuilder = ClientBuilder::create($options);
            $clientBuilder->setSdkIdentifier(Version::SDK_IDENTIFIER);
            $clientBuilder->setSdkVersion(Version::SDK_VERSION);

            $hub = new Hub($clientBuilder->getClient());
            SentrySdk::setCurrentHub($hub);

            return $hub;
        } catch (Throwable $e) {
            Log::error('Failed to initialize Sentry client. Error monitoring is DISABLED.', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);

            return $this->createEmptyHub('Client creation failed');
        }
    }

    /**
     * Create an empty Sentry hub (no-op for error reporting).
     *
     * Logs a warning in non-local/non-testing environments.
     *
     * @param string $reason The reason for creating an empty hub
     * @return HubInterface An empty hub instance
     */
    private function createEmptyHub(string $reason): HubInterface
    {
        $hub = new Hub();
        SentrySdk::setCurrentHub($hub);

        // Warn in non-local/non-testing environments
        $env = config('app.env');
        if (! in_array($env, ['local', 'testing'], true)) {
            Log::warning(
                "Sentry error monitoring is DISABLED: {$reason}. ".
                'Set SENTRY_LARAVEL_DSN environment variable to enable error tracking.'
            );
        }

        return $hub;
    }
}
