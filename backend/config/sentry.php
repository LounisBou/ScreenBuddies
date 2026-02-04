<?php

declare(strict_types=1);

return [
    'dsn' => env('SENTRY_LARAVEL_DSN'),

    'release' => env('APP_VERSION', '1.0.0'),

    'environment' => env('APP_ENV', 'production'),

    // Don't send PII by default
    'send_default_pii' => false,

    // Sample rate for performance monitoring (0 = disabled, 1.0 = 100%)
    // In production: 10%, in testing: disabled, otherwise: 100%
    'traces_sample_rate' => (float) env('SENTRY_TRACES_SAMPLE_RATE', env('APP_ENV') === 'production' ? 0.1 : (env('APP_ENV') === 'testing' ? 0 : 1.0)),

    // Profiles sample rate (relative to traces_sample_rate)
    'profiles_sample_rate' => 1.0,

    // Tracing configuration - disable default integrations due to Laravel 12 compatibility
    // (TracingServiceProvider references old ValidateCsrfToken class path)
    'tracing' => [
        'default_integrations' => false,
    ],

    // Breadcrumbs
    'breadcrumbs' => [
        'logs' => true,
        'sql_queries' => true,
        'sql_bindings' => false,
        'queue_info' => true,
        'command_info' => true,
    ],

    // Controller base namespace for route transaction naming
    'controllers_base_namespace' => 'App\\Http\\Controllers',
];
