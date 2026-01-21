# ScreenBuddies Installation Guide

This document tracks all installation and initialization commands for the ScreenBuddies project.

## Prerequisites

### macOS (Homebrew)

#### 1. Install Homebrew (if not installed)

```bash
/bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)"
```

#### 2. Install PHP 8.3+ (if not using phpenv)

```bash
brew install php@8.3
```

#### 3. Install Composer

```bash
# Option A: Via Homebrew (may conflict with phpenv)
brew install composer

# Option B: Manual installation to user directory (recommended with phpenv)
cd /tmp
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php
mkdir -p ~/.local/bin
mv composer.phar ~/.local/bin/composer
chmod +x ~/.local/bin/composer
php -r "unlink('composer-setup.php');"

# Add to PATH (add to ~/.zshrc or ~/.bashrc)
export PATH="$HOME/.local/bin:$PATH"
```

#### 4. Install PHP Redis Extension

```bash
# Via PECL
pecl install redis

# Then add to php.ini (find location with: php --ini)
# Add line: extension=redis
```

#### 5. Install PostgreSQL 16

```bash
brew install postgresql@16
brew services start postgresql@16

# Add to PATH (add to ~/.zshrc or ~/.bashrc)
export PATH="/opt/homebrew/opt/postgresql@16/bin:$PATH"
```

#### 6. Install Redis 7

```bash
brew install redis
brew services start redis
```

---

## Verify Prerequisites

Run these commands to verify all prerequisites are installed:

```bash
# PHP version (should be 8.3+)
php -v

# PHP extensions (should include: pgsql, redis, mbstring, xml, curl, zip)
php -m | grep -E 'pgsql|redis|mbstring|xml|curl|zip'

# Composer version (should be 2.x)
composer --version

# PostgreSQL status
pg_isready

# Redis status
redis-cli ping
```

---

## Database Setup

### Create PostgreSQL Database

```bash
createdb screenbuddies
```

### Verify Database

```bash
psql -l | grep screenbuddies
```

---

## Laravel Project Initialization

### Create Laravel Project

```bash
cd /Users/lounis/dev/ScreenBuddies
composer create-project laravel/laravel backend
```

### Configure Environment

Edit `backend/.env`:

```env
APP_NAME=ScreenBuddies
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=screenbuddies
DB_USERNAME=lounis
DB_PASSWORD=

REDIS_HOST=127.0.0.1
REDIS_PORT=6379

CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
```

### Run Migrations

```bash
cd backend
php artisan migrate
```

### Start Development Server

```bash
php artisan serve
```

Visit: http://localhost:8000

---

## Troubleshooting

### Composer not found

If using phpenv, composer may not be in the shims. Install globally:

```bash
brew install composer
# Or add to path: export PATH="$HOME/.composer/vendor/bin:$PATH"
```

### PHP Redis extension not loading

Check php.ini location and ensure `extension=redis` is added:

```bash
php --ini
# Edit the loaded php.ini file
```

### PostgreSQL connection refused

Ensure PostgreSQL is running:

```bash
brew services start postgresql@16
```

### Redis connection refused

Ensure Redis is running:

```bash
brew services start redis
```
