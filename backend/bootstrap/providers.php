<?php

return [
    App\Providers\AppServiceProvider::class,
    // Custom Sentry provider for Laravel 12 compatibility
    // (Official Sentry ServiceProvider has issues with TracingServiceProvider
    // referencing Illuminate\Foundation\Http\Middleware\ValidateCsrfToken)
    App\Providers\SentryServiceProvider::class,
];
