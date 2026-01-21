# ScreenBuddies Installation Guide

This document tracks all installation and initialization commands for the ScreenBuddies project.

**Laravel Version:** 12.x (latest stable)

## Prerequisites

### macOS (Homebrew)

#### 1. Install Homebrew (if not installed)

```bash
/bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)"
```

#### 2. Install PHP 8.3+

```bash
# If not using phpenv
brew install php@8.3

# Homebrew's PHP includes most extensions (pgsql, mbstring, xml, curl, zip)
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

# The extension is usually auto-enabled. If not, add to php.ini:
# Find php.ini location: php --ini
# Add line: extension=redis
```

#### 5. Install PostgreSQL 16

```bash
brew install postgresql@16
brew services start postgresql@16

# Add to PATH (add to ~/.zshrc or ~/.bashrc)
# Apple Silicon Macs:
export PATH="/opt/homebrew/opt/postgresql@16/bin:$PATH"

# Intel Macs:
# export PATH="/usr/local/opt/postgresql@16/bin:$PATH"
```

#### 6. Install Redis 7

```bash
brew install redis
brew services start redis
```

#### 7. Install Node.js (required for Vite asset bundling)

```bash
brew install node
```

#### 8. Install PCOV (for code coverage)

```bash
# Install pcre2 dependency first
brew install pcre2

# Install PCOV via PECL
pecl install pcov

# Verify installation
php -m | grep pcov
```

> **Note:** PCOV is faster than Xdebug for code coverage.
>
> **Troubleshooting PCOV installation:**
> - If `pecl install pcov` fails with `pcre2.h: No such file or directory`:
>   - Verify pcre2 is installed: `brew list pcre2`
>   - Check headers exist: `ls /opt/homebrew/opt/pcre2/include/` (Apple Silicon) or `/usr/local/opt/pcre2/include/` (Intel)
>   - Try reinstalling: `brew reinstall pcre2`
> - **Alternative:** Use Xdebug instead: `pecl install xdebug`

---

## Verify Prerequisites

Run these commands to verify all prerequisites are installed:

```bash
# PHP version (should be 8.3+)
php -v

# PHP extensions (should include: pgsql, redis, mbstring, xml, curl, zip, pcov)
php -m | grep -E 'pgsql|redis|mbstring|xml|curl|zip|pcov'

# Composer version (should be 2.x)
composer --version

# PostgreSQL status
pg_isready

# Redis status
redis-cli ping

# Node.js version
node -v
```

---

## Database Setup

### Create PostgreSQL User (if needed)

If your macOS user doesn't exist in PostgreSQL:

```bash
# Create a superuser with your username
createuser -s $(whoami)
```

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
# From the project root directory
composer create-project laravel/laravel backend
```

### Configure Environment

```bash
# Copy environment template
cp backend/.env.example backend/.env

# Generate application key
cd backend
php artisan key:generate
```

Edit `backend/.env` and update database credentials:

```env
APP_NAME=ScreenBuddies
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=screenbuddies
DB_USERNAME=your_postgres_username
DB_PASSWORD=

REDIS_HOST=127.0.0.1
REDIS_PORT=6379

CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
```

> **Note:** Replace `your_postgres_username` with your PostgreSQL username (typically your macOS username).

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
# Or add to path: export PATH="$HOME/.local/bin:$PATH"
```

### PHP Redis extension not loading

Check php.ini location and ensure `extension=redis` is added:

```bash
php --ini
# Edit the loaded php.ini file and add: extension=redis
```

### PostgreSQL connection refused

Ensure PostgreSQL is running:

```bash
brew services start postgresql@16
```

### PostgreSQL "role does not exist" error

Create your user in PostgreSQL:

```bash
createuser -s $(whoami)
```

### Redis connection refused

Ensure Redis is running:

```bash
brew services start redis
```

### PHP library not loaded error after brew upgrade

Reinstall PHP to relink libraries:

```bash
brew reinstall php
```
