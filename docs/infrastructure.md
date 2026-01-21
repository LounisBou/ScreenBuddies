# Infrastructure & DevOps

**Date:** 2026-01-20
**Status:** Approved

---

## Overview

This document describes the infrastructure setup for ScreenBuddies, covering local development, CI/CD, monitoring, logging, and deployment strategies.

---

## Architecture Overview

```
┌─────────────────────────────────────────────────────────────────┐
│                        DEVELOPMENT                               │
├─────────────────────────────────────────────────────────────────┤
│  Developer Machine (Native)                                      │
│  ├── PHP 8.3 + Composer                                         │
│  ├── PostgreSQL 16                                              │
│  ├── Redis 7                                                    │
│  └── Laravel Valet / php artisan serve                          │
└─────────────────────────────────────────────────────────────────┘
                              │
                              │ git push
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                        CI/CD (GitHub Actions)                    │
├─────────────────────────────────────────────────────────────────┤
│  On Pull Request:                                                │
│  ├── Lint (PHP CS Fixer, Larastan)                              │
│  ├── Test (PHPUnit/Pest)                                        │
│  └── Build check                                                 │
│                                                                  │
│  On Merge to Main:                                               │
│  ├── All above checks                                           │
│  └── Deploy to staging (optional)                               │
└─────────────────────────────────────────────────────────────────┘
                              │
                              │ deploy
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                        PRODUCTION (TBD)                          │
├─────────────────────────────────────────────────────────────────┤
│  Options:                                                        │
│  ├── Laravel Forge + DigitalOcean                               │
│  ├── AWS (EC2 / ECS / Lambda)                                   │
│  ├── Railway / Render                                           │
│  └── Self-managed VPS                                           │
└─────────────────────────────────────────────────────────────────┘
                              │
                              │ errors/logs
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                        MONITORING                                │
├─────────────────────────────────────────────────────────────────┤
│  ├── Sentry (errors: backend + Flutter)                         │
│  ├── Health checks (/health endpoint)                           │
│  └── Uptime monitoring (BetterUptime / UptimeRobot)             │
└─────────────────────────────────────────────────────────────────┘
```

---

## Local Development

### Requirements

| Tool | Version | Purpose |
|------|---------|---------|
| PHP | 8.3+ | Laravel runtime |
| Composer | 2.x | PHP dependency management |
| PostgreSQL | 16+ | Database |
| Redis | 7+ | Cache, queues, circuit breaker |
| Node.js | 20+ | Asset compilation (if needed) |
| Flutter | 3.x | Mobile app development |

### Installation (macOS)

```bash
# Install Homebrew packages
brew install php@8.3 composer postgresql@16 redis node

# Start services
brew services start postgresql@16
brew services start redis

# Create database
createdb screenbuddies

# Clone and setup
git clone <repo-url> screenbuddies
cd screenbuddies/backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
```

### Installation (Ubuntu/Debian)

```bash
# Add PHP repository
sudo add-apt-repository ppa:ondrej/php

# Install packages
sudo apt update
sudo apt install php8.3 php8.3-{cli,fpm,pgsql,redis,mbstring,xml,curl,zip} \
    postgresql-16 redis-server nodejs npm

# Start services
sudo systemctl start postgresql redis-server

# Create database
sudo -u postgres createdb screenbuddies
sudo -u postgres createuser --interactive  # Create your user

# Continue with clone and setup...
```

### Running Locally

```bash
# Terminal 1: Laravel server
cd backend
php artisan serve

# Terminal 2: Queue worker (optional, for async jobs)
php artisan queue:work

# Terminal 3: Flutter app
cd app
flutter run
```

### Environment Variables

Create `backend/.env`:

```env
APP_NAME=ScreenBuddies
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

# Database (PostgreSQL)
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=screenbuddies
DB_USERNAME=your_user
DB_PASSWORD=your_password

# Redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=null

# Sanctum
SANCTUM_TOKEN_EXPIRATION=15
SANCTUM_REFRESH_TOKEN_EXPIRATION=10080

# External APIs
TMDB_API_KEY=your-tmdb-key
RAWG_API_KEY=your-rawg-key

# Sentry (optional for local)
SENTRY_LARAVEL_DSN=

# Queue
QUEUE_CONNECTION=redis
```

---

## CI/CD Pipeline

### GitHub Actions Workflow

Create `.github/workflows/ci.yml`:

```yaml
name: CI

on:
  push:
    branches: [main, develop]
  pull_request:
    branches: [main, develop]

jobs:
  test:
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
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          extensions: pgsql, redis, mbstring, xml, curl, zip
          coverage: xdebug

      - name: Install dependencies
        working-directory: backend
        run: composer install --prefer-dist --no-progress

      - name: Copy .env
        working-directory: backend
        run: cp .env.example .env

      - name: Generate key
        working-directory: backend
        run: php artisan key:generate

      - name: Run Larastan (static analysis)
        working-directory: backend
        run: vendor/bin/phpstan analyse --memory-limit=512M
        continue-on-error: true  # Warning only for now

      - name: Run PHP CS Fixer (dry run)
        working-directory: backend
        run: vendor/bin/php-cs-fixer fix --dry-run --diff
        continue-on-error: true  # Warning only for now

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
        run: php artisan test --coverage --min=70

  flutter:
    runs-on: ubuntu-latest

    steps:
      - uses: actions/checkout@v4

      - name: Setup Flutter
        uses: subosito/flutter-action@v2
        with:
          flutter-version: '3.24.0'
          channel: 'stable'

      - name: Install dependencies
        working-directory: app
        run: flutter pub get

      - name: Analyze
        working-directory: app
        run: flutter analyze

      - name: Run tests
        working-directory: app
        run: flutter test
```

### Workflow Triggers

| Event | Actions |
|-------|---------|
| PR opened/updated | Run all checks, block merge if fails |
| Push to main | Run checks + deploy to staging |
| Push to develop | Run checks only |

### Branch Protection Rules

Configure in GitHub Settings → Branches → main:

- [x] Require status checks to pass
- [x] Require branches to be up to date
- [x] Required checks: `test`, `flutter`
- [x] Require pull request reviews (optional)

---

## Error Monitoring (Sentry)

### Why Sentry?

- Automatic exception capture
- Stack traces with context
- Release tracking
- Performance monitoring
- Official Laravel & Flutter SDKs

### Free Tier Limits

- 5,000 errors/month
- 10,000 performance transactions/month
- 1 user
- 30-day retention

### Backend Setup (Laravel)

```bash
cd backend
composer require sentry/sentry-laravel
php artisan sentry:publish --dsn=YOUR_DSN
```

Update `config/sentry.php`:

```php
return [
    'dsn' => env('SENTRY_LARAVEL_DSN'),
    'release' => env('APP_VERSION', '1.0.0'),
    'environment' => env('APP_ENV', 'production'),
    'send_default_pii' => false,  // Don't send emails/IPs by default
    'traces_sample_rate' => env('APP_ENV') === 'production' ? 0.1 : 1.0,
];
```

Add to `.env`:

```env
SENTRY_LARAVEL_DSN=https://xxx@xxx.ingest.sentry.io/xxx
```

### Frontend Setup (Flutter)

Add to `pubspec.yaml`:

```yaml
dependencies:
  sentry_flutter: ^8.0.0
```

Update `main.dart`:

```dart
import 'package:sentry_flutter/sentry_flutter.dart';

Future<void> main() async {
  await SentryFlutter.init(
    (options) {
      options.dsn = 'https://xxx@xxx.ingest.sentry.io/xxx';
      options.environment = const String.fromEnvironment('ENV', defaultValue: 'development');
      options.tracesSampleRate = 0.1;
    },
    appRunner: () => runApp(const MyApp()),
  );
}
```

### Filtering & Privacy

```php
// In app/Exceptions/Handler.php
public function register(): void
{
    $this->reportable(function (Throwable $e) {
        // Don't report validation errors
        if ($e instanceof ValidationException) {
            return false;
        }

        // Don't report 404s
        if ($e instanceof NotFoundHttpException) {
            return false;
        }
    });
}
```

### Alerts

Configure in Sentry → Alerts:

- **First seen error:** Email immediately
- **Error spike (10x normal):** Email + Slack
- **New release regression:** Email

---

## Logging

### Structured Logging

Update `config/logging.php`:

```php
'channels' => [
    'stack' => [
        'driver' => 'stack',
        'channels' => ['daily', 'stderr'],
    ],

    'daily' => [
        'driver' => 'daily',
        'path' => storage_path('logs/laravel.log'),
        'level' => env('LOG_LEVEL', 'debug'),
        'days' => 14,
        'formatter' => Monolog\Formatter\JsonFormatter::class,  // JSON format
    ],

    'stderr' => [
        'driver' => 'monolog',
        'level' => env('LOG_LEVEL', 'debug'),
        'handler' => Monolog\Handler\StreamHandler::class,
        'formatter' => Monolog\Formatter\JsonFormatter::class,
        'with' => [
            'stream' => 'php://stderr',
        ],
    ],
],
```

### Log Levels

| Level | Use For |
|-------|---------|
| `emergency` | System unusable |
| `alert` | Immediate action needed |
| `critical` | Critical conditions |
| `error` | Runtime errors (caught) |
| `warning` | Exceptional but handled |
| `notice` | Normal but significant |
| `info` | Interesting events |
| `debug` | Detailed debug info |

### Usage

```php
use Illuminate\Support\Facades\Log;

// With context
Log::info('Election created', [
    'election_id' => $election->id,
    'user_id' => auth()->id(),
    'candidate_count' => $election->candidates()->count(),
]);

// Error with exception
Log::error('External API failed', [
    'service' => 'tmdb',
    'exception' => $e->getMessage(),
]);
```

### Laravel Telescope (Local)

```bash
composer require laravel/telescope --dev
php artisan telescope:install
php artisan migrate
```

Access at: `http://localhost:8000/telescope`

Features:
- Request/response inspector
- Database queries
- Jobs & queues
- Exceptions
- Logs
- Cache operations

---

## Health Checks

### Health Endpoint

Create `app/Http/Controllers/Api/HealthController.php`:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CircuitBreaker\GaneshaService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    public function __invoke(GaneshaService $ganesha)
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
            'circuits' => [
                'tmdb' => $ganesha->getStatus('tmdb'),
                'rawg' => $ganesha->getStatus('rawg'),
            ],
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

Register route in `routes/api.php`:

```php
Route::get('health', \App\Http\Controllers\Api\HealthController::class);
```

### Response Examples

**Healthy (200):**
```json
{
  "status": "ok",
  "checks": {
    "database": true,
    "redis": true,
    "circuits": {
      "tmdb": "closed",
      "rawg": "closed"
    }
  },
  "timestamp": "2026-01-20T12:00:00+00:00"
}
```

**Degraded (503):**
```json
{
  "status": "degraded",
  "checks": {
    "database": true,
    "redis": false,
    "circuits": {
      "tmdb": "open",
      "rawg": "closed"
    }
  },
  "timestamp": "2026-01-20T12:00:00+00:00"
}
```

### Uptime Monitoring

Configure external monitoring (free tiers available):

| Service | Free Tier |
|---------|-----------|
| [BetterUptime](https://betteruptime.com) | 10 monitors, 3-min intervals |
| [UptimeRobot](https://uptimerobot.com) | 50 monitors, 5-min intervals |
| [Freshping](https://freshping.io) | 50 monitors, 1-min intervals |

Monitor: `https://api.screenbuddies.app/api/health`

Alert on:
- HTTP status != 200
- Response time > 5s
- SSL certificate expiring

---

## Production Deployment (TBD)

### Options Under Consideration

| Option | Pros | Cons | Cost |
|--------|------|------|------|
| **Laravel Forge + DO** | Easy Laravel deploys, managed | Vendor lock-in | ~$20/mo |
| **Railway** | Git push deploys, free tier | Limited control | $5-20/mo |
| **Render** | Simple, good DX | Slower cold starts | $7-25/mo |
| **AWS EC2** | Full control, scalable | Complex setup | $10-50/mo |
| **Self-managed VPS** | Cheapest, full control | Manual maintenance | $5-10/mo |

### Minimum Production Requirements

- [ ] SSL/TLS (Let's Encrypt)
- [ ] Database backups (daily)
- [ ] Redis persistence
- [ ] Environment secrets management
- [ ] Zero-downtime deploys
- [ ] Horizontal scaling plan

### Decision Criteria

To be decided based on:
1. Team ops experience
2. Budget
3. Scale requirements
4. Time to market

---

## Security Checklist

### Pre-Production

- [ ] `APP_DEBUG=false` in production
- [ ] `APP_ENV=production`
- [ ] Strong `APP_KEY` generated
- [ ] Database credentials secured (not in git)
- [ ] API keys in environment variables
- [ ] HTTPS enforced
- [ ] CORS configured properly
- [ ] Rate limiting enabled
- [ ] SQL injection prevention (Eloquent)
- [ ] XSS prevention (Blade escaping)

### Secrets Management

**Local:** `.env` file (gitignored)

**CI/CD:** GitHub Secrets
- `SENTRY_DSN`
- `TMDB_API_KEY`
- `RAWG_API_KEY`

**Production:** Environment variables via hosting provider

---

## Backup Strategy

### Database

```bash
# Daily backup script
pg_dump -h localhost -U app screenbuddies | gzip > backup_$(date +%Y%m%d).sql.gz

# Upload to S3/storage (example)
aws s3 cp backup_$(date +%Y%m%d).sql.gz s3://screenbuddies-backups/
```

### Retention

| Type | Retention |
|------|-----------|
| Daily | 7 days |
| Weekly | 4 weeks |
| Monthly | 12 months |

### Recovery Testing

- [ ] Test restore monthly
- [ ] Document restore procedure
- [ ] Measure RTO (Recovery Time Objective)

---

## References

- [Laravel Deployment](https://laravel.com/docs/deployment)
- [Sentry Laravel](https://docs.sentry.io/platforms/php/guides/laravel/)
- [Sentry Flutter](https://docs.sentry.io/platforms/flutter/)
- [GitHub Actions](https://docs.github.com/en/actions)
- [PostgreSQL Backup](https://www.postgresql.org/docs/current/backup.html)
