<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Sentry\ClientBuilder;
use Sentry\Laravel\Version;
use Sentry\SentrySdk;
use Sentry\State\Hub;
use Sentry\State\HubInterface;

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
     * Register the service provider.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(config_path('sentry.php'), 'sentry');

        $this->app->singleton(HubInterface::class, function () {
            $dsn = config('sentry.dsn');

            // If no DSN is set, return an empty hub
            if (empty($dsn)) {
                $hub = new Hub();
                SentrySdk::setCurrentHub($hub);

                return $hub;
            }

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
        });

        $this->app->alias(HubInterface::class, 'sentry');
    }

    /**
     * Boot the service provider.
     */
    public function boot(): void
    {
        // Initialize the hub on boot
        $this->app->make(HubInterface::class);
    }
}
