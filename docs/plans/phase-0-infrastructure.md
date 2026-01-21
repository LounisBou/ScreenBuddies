# Phase 0: Infrastructure Setup

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Set up development environment, CI/CD pipeline, error monitoring, and health checks before starting feature development.

**Architecture:** Native PHP/PostgreSQL/Redis development, GitHub Actions for CI/CD, Sentry for error monitoring.

**Tech Stack:** PHP 8.3, PostgreSQL 16, Redis 7, GitHub Actions, Sentry (free tier)

**Prerequisites:** Git repository created, GitHub account

**Reference:** See `docs/infrastructure.md` for full documentation.

---

## Task 1: Initialize Laravel Project

**Files:**
- Create: `backend/` directory with fresh Laravel 11 installation

**Step 1: Create Laravel project**

```bash
composer create-project laravel/laravel backend
cd backend
```

**Step 2: Configure .env for PostgreSQL**

Update `backend/.env`:
```env
APP_NAME=ScreenBuddies
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=screenbuddies
DB_USERNAME=your_user
DB_PASSWORD=your_password

REDIS_HOST=127.0.0.1
REDIS_PORT=6379

CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
```

**Step 3: Create database**

```bash
createdb screenbuddies
```

**Step 4: Test connection**

```bash
php artisan migrate
php artisan serve
```

Visit `http://localhost:8000` - should see Laravel welcome page.

**Step 5: Commit**

```bash
git add .
git commit -m "chore: initialize Laravel 11 project"
```

---

## Task 2: Install Development Tools

**Files:**
- Modify: `backend/composer.json`
- Create: `backend/phpstan.neon`
- Create: `backend/.php-cs-fixer.php`

**Step 1: Install dev dependencies**

```bash
cd backend
composer require --dev phpstan/phpstan larastan/larastan
composer require --dev friendsofphp/php-cs-fixer
composer require --dev pestphp/pest pestphp/pest-plugin-laravel
```

**Step 2: Initialize Pest**

```bash
php artisan pest:install
```

**Step 3: Create PHPStan config**

Create `backend/phpstan.neon`:
```neon
includes:
    - vendor/larastan/larastan/extension.neon

parameters:
    paths:
        - app/
    level: 5
    ignoreErrors:
    excludePaths:
```

**Step 4: Create PHP CS Fixer config**

Create `backend/.php-cs-fixer.php`:
```php
<?php

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__ . '/app')
    ->in(__DIR__ . '/config')
    ->in(__DIR__ . '/database')
    ->in(__DIR__ . '/routes')
    ->in(__DIR__ . '/tests');

return (new PhpCsFixer\Config())
    ->setRules([
        '@PSR12' => true,
        'array_syntax' => ['syntax' => 'short'],
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'no_unused_imports' => true,
        'trailing_comma_in_multiline' => true,
        'single_quote' => true,
    ])
    ->setFinder($finder);
```

**Step 5: Add scripts to composer.json**

Add to `backend/composer.json` scripts section:
```json
"scripts": {
    "analyse": "vendor/bin/phpstan analyse",
    "format": "vendor/bin/php-cs-fixer fix",
    "format:check": "vendor/bin/php-cs-fixer fix --dry-run --diff",
    "test": "php artisan test"
}
```

**Step 6: Test tools**

```bash
composer analyse
composer format:check
composer test
```

**Step 7: Commit**

```bash
git add .
git commit -m "chore: add PHPStan, PHP CS Fixer, and Pest"
```

---

## Task 3: Setup GitHub Actions CI

**Files:**
- Create: `.github/workflows/ci.yml`

**Step 1: Create CI workflow**

Create `.github/workflows/ci.yml`:
```yaml
name: CI

on:
  push:
    branches: [main, develop]
  pull_request:
    branches: [main, develop]

jobs:
  backend:
    name: Backend Tests
    runs-on: ubuntu-latest

    services:
      postgres:
        image: postgres:16
        env:
          POSTGRES_USER: postgres
          POSTGRES_PASSWORD: postgres
          POSTGRES_DB: testing
        ports:
          - 5432:5432
        options: >-
          --health-cmd pg_isready
          --health-interval 10s
          --health-timeout 5s
          --health-retries 5

      redis:
        image: redis:7
        ports:
          - 6379:6379
        options: >-
          --health-cmd "redis-cli ping"
          --health-interval 10s
          --health-timeout 5s
          --health-retries 5

    steps:
      - name: Checkout code
        uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          extensions: pgsql, redis, mbstring, xml, curl, zip
          coverage: xdebug

      - name: Cache Composer dependencies
        uses: actions/cache@v4
        with:
          path: backend/vendor
          key: composer-${{ hashFiles('backend/composer.lock') }}
          restore-keys: composer-

      - name: Install dependencies
        working-directory: backend
        run: composer install --prefer-dist --no-progress

      - name: Copy .env
        working-directory: backend
        run: cp .env.example .env

      - name: Generate key
        working-directory: backend
        run: php artisan key:generate

      - name: Run static analysis
        working-directory: backend
        run: composer analyse
        continue-on-error: true

      - name: Check code style
        working-directory: backend
        run: composer format:check
        continue-on-error: true

      - name: Run tests
        working-directory: backend
        env:
          DB_CONNECTION: pgsql
          DB_HOST: 127.0.0.1
          DB_PORT: 5432
          DB_DATABASE: testing
          DB_USERNAME: postgres
          DB_PASSWORD: postgres
          REDIS_HOST: 127.0.0.1
          REDIS_PORT: 6379
        run: composer test

  flutter:
    name: Flutter Tests
    runs-on: ubuntu-latest
    # Skip if app/ directory doesn't exist yet
    if: hashFiles('app/pubspec.yaml') != ''

    steps:
      - name: Checkout code
        uses: actions/checkout@v4

      - name: Setup Flutter
        uses: subosito/flutter-action@v2
        with:
          flutter-version: '3.24.0'
          channel: 'stable'
          cache: true

      - name: Install dependencies
        working-directory: app
        run: flutter pub get

      - name: Analyze code
        working-directory: app
        run: flutter analyze

      - name: Run tests
        working-directory: app
        run: flutter test
```

**Step 2: Create .env.example for CI**

Update `backend/.env.example` to include all required variables with safe defaults:
```env
APP_NAME=ScreenBuddies
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8000

LOG_CHANNEL=stack
LOG_LEVEL=debug

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=screenbuddies
DB_USERNAME=app
DB_PASSWORD=secret

REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=null

CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

SANCTUM_TOKEN_EXPIRATION=15
SANCTUM_REFRESH_TOKEN_EXPIRATION=10080

TMDB_API_KEY=
RAWG_API_KEY=

SENTRY_LARAVEL_DSN=
```

**Step 3: Commit and test**

```bash
git add .
git commit -m "ci: add GitHub Actions workflow"
git push origin main
```

Check GitHub Actions tab to verify workflow runs.

---

## Task 4: Setup Sentry Error Monitoring

**Files:**
- Modify: `backend/composer.json`
- Create: `backend/config/sentry.php`
- Modify: `backend/app/Exceptions/Handler.php`

**Step 1: Install Sentry Laravel SDK**

```bash
cd backend
composer require sentry/sentry-laravel
```

**Step 2: Publish Sentry config**

```bash
php artisan sentry:publish
```

**Step 3: Configure Sentry**

Update `backend/config/sentry.php`:
```php
<?php

return [
    'dsn' => env('SENTRY_LARAVEL_DSN'),

    'release' => env('APP_VERSION', '1.0.0'),

    'environment' => env('APP_ENV', 'production'),

    // Don't send PII by default
    'send_default_pii' => false,

    // Sample rate for performance monitoring
    'traces_sample_rate' => env('APP_ENV') === 'production' ? 0.1 : 1.0,

    // Breadcrumbs
    'breadcrumbs' => [
        'logs' => true,
        'sql_queries' => true,
        'sql_bindings' => false,
        'queue_info' => true,
        'command_info' => true,
    ],
];
```

**Step 4: Configure exception filtering**

Update `backend/app/Exceptions/Handler.php`:
```php
<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    protected $dontReport = [
        //
    ];

    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            // Don't report validation errors to Sentry
            if ($e instanceof ValidationException) {
                return false;
            }

            // Don't report 404 errors
            if ($e instanceof NotFoundHttpException) {
                return false;
            }

            // Report to Sentry
            if (app()->bound('sentry')) {
                app('sentry')->captureException($e);
            }
        });
    }
}
```

**Step 5: Add Sentry DSN to .env.example**

Already added in Task 3.

**Step 6: Test Sentry (optional)**

Create test route in `backend/routes/web.php`:
```php
Route::get('/debug-sentry', function () {
    throw new \Exception('Sentry test exception!');
});
```

Visit `/debug-sentry` and check Sentry dashboard.

**Step 7: Remove test route and commit**

```bash
git add .
git commit -m "feat: add Sentry error monitoring"
```

---

## Task 5: Setup Health Check Endpoint

**Files:**
- Create: `backend/app/Http/Controllers/Api/HealthController.php`
- Modify: `backend/routes/api.php`

**Step 1: Create HealthController**

Create `backend/app/Http/Controllers/Api/HealthController.php`:
```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
        ];

        $healthy = $checks['database'] && $checks['redis'];
        $status = $healthy ? 'ok' : 'degraded';

        return response()->json([
            'status' => $status,
            'checks' => $checks,
            'timestamp' => now()->toIso8601String(),
        ], $healthy ? 200 : 503);
    }

    private function checkDatabase(): bool
    {
        try {
            DB::select('SELECT 1');
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function checkRedis(): bool
    {
        try {
            Cache::store('redis')->put('health_check', true, 10);
            return Cache::store('redis')->get('health_check') === true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
```

**Step 2: Add health route**

Update `backend/routes/api.php`:
```php
<?php

use App\Http\Controllers\Api\HealthController;
use Illuminate\Support\Facades\Route;

// Health check (no auth required)
Route::get('health', HealthController::class);
```

**Step 3: Write health check test**

Create `backend/tests/Feature/HealthCheckTest.php`:
```php
<?php

test('health check returns ok when services are healthy', function () {
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
});
```

**Step 4: Run test**

```bash
php artisan test tests/Feature/HealthCheckTest.php
```
Expected: PASS

**Step 5: Commit**

```bash
git add .
git commit -m "feat: add health check endpoint"
```

---

## Task 6: Setup Laravel Telescope (Local Only)

**Files:**
- Modify: `backend/composer.json`
- Create: Telescope migrations and config

**Step 1: Install Telescope as dev dependency**

```bash
cd backend
composer require laravel/telescope --dev
```

**Step 2: Install Telescope**

```bash
php artisan telescope:install
php artisan migrate
```

**Step 3: Configure Telescope for local only**

Update `backend/app/Providers/TelescopeServiceProvider.php`:
```php
<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\TelescopeApplicationServiceProvider;

class TelescopeServiceProvider extends TelescopeApplicationServiceProvider
{
    public function register(): void
    {
        // Only load Telescope in local environment
        if ($this->app->environment('local')) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
        }

        Telescope::night();

        $this->hideSensitiveRequestDetails();

        Telescope::filter(function (IncomingEntry $entry) {
            if ($this->app->environment('local')) {
                return true;
            }

            return $entry->isReportableException() ||
                   $entry->isFailedRequest() ||
                   $entry->isFailedJob() ||
                   $entry->isScheduledTask() ||
                   $entry->hasMonitoredTag();
        });
    }

    protected function hideSensitiveRequestDetails(): void
    {
        Telescope::hideRequestParameters(['_token', 'password', 'password_confirmation']);
        Telescope::hideRequestHeaders(['cookie', 'x-csrf-token', 'x-xsrf-token', 'authorization']);
    }

    protected function gate(): void
    {
        Gate::define('viewTelescope', function ($user = null) {
            return $this->app->environment('local');
        });
    }
}
```

**Step 4: Test Telescope**

```bash
php artisan serve
```

Visit `http://localhost:8000/telescope`

**Step 5: Commit**

```bash
git add .
git commit -m "feat: add Laravel Telescope for local debugging"
```

---

## Task 7: Setup Structured Logging

**Files:**
- Modify: `backend/config/logging.php`

**Step 1: Update logging config**

Update `backend/config/logging.php`:
```php
<?php

use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogUdpHandler;
use Monolog\Processor\PsrLogMessageProcessor;

return [
    'default' => env('LOG_CHANNEL', 'stack'),

    'deprecations' => [
        'channel' => env('LOG_DEPRECATIONS_CHANNEL', 'null'),
        'trace' => false,
    ],

    'channels' => [
        'stack' => [
            'driver' => 'stack',
            'channels' => ['daily'],
            'ignore_exceptions' => false,
        ],

        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => 14,
            'formatter' => env('LOG_FORMAT', 'default') === 'json'
                ? Monolog\Formatter\JsonFormatter::class
                : null,
        ],

        'stderr' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => StreamHandler::class,
            'formatter' => Monolog\Formatter\JsonFormatter::class,
            'with' => [
                'stream' => 'php://stderr',
            ],
            'processors' => [PsrLogMessageProcessor::class],
        ],

        'syslog' => [
            'driver' => 'syslog',
            'level' => env('LOG_LEVEL', 'debug'),
            'facility' => LOG_USER,
            'replace_placeholders' => true,
        ],

        'errorlog' => [
            'driver' => 'errorlog',
            'level' => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
        ],

        'null' => [
            'driver' => 'monolog',
            'handler' => NullHandler::class,
        ],

        'emergency' => [
            'path' => storage_path('logs/laravel.log'),
        ],
    ],
];
```

**Step 2: Add LOG_FORMAT to .env.example**

Add to `backend/.env.example`:
```env
LOG_FORMAT=default  # Use 'json' for production
```

**Step 3: Commit**

```bash
git add .
git commit -m "feat: add structured JSON logging support"
```

---

## Task 8: Create .gitignore and Finalize

**Files:**
- Update: `backend/.gitignore`
- Create: `.gitignore` (root)

**Step 1: Update backend .gitignore**

Ensure `backend/.gitignore` includes:
```
/node_modules
/public/hot
/public/storage
/storage/*.key
/vendor
.env
.env.backup
.env.production
.phpunit.result.cache
Homestead.json
Homestead.yaml
npm-debug.log
yarn-error.log
/.idea
/.vscode
/.php-cs-fixer.cache
```

**Step 2: Create root .gitignore**

Create `.gitignore` at project root:
```
# IDEs
.idea/
.vscode/
*.swp
*.swo

# OS
.DS_Store
Thumbs.db

# Logs
*.log

# Environment
.env.local
.env.*.local
```

**Step 3: Final commit**

```bash
git add .
git commit -m "chore: phase 0 complete - infrastructure setup"
```

---

## Phase 0 Completion Checklist

- [ ] Laravel 11 project initialized
- [ ] PostgreSQL database configured
- [ ] Redis configured for cache/queue
- [ ] PHPStan static analysis
- [ ] PHP CS Fixer code style
- [ ] Pest test framework
- [ ] GitHub Actions CI workflow
- [ ] Sentry error monitoring
- [ ] Health check endpoint
- [ ] Laravel Telescope (local)
- [ ] Structured logging
- [ ] All tests passing

---

## Next Phase

Proceed to **Phase 1: Backend Foundation** for database schema and base models.
