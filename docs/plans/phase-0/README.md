# Phase 0: Infrastructure Setup - Sub-Plans

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement these plans task-by-task.

**Goal:** Set up complete development infrastructure before feature development.

**Estimated Tasks:** 8 sub-phases, ~50 granular tasks total

---

## Sub-Phase Overview

| Sub-Phase | Description | Key Deliverables |
|-----------|-------------|------------------|
| [0.1](./0.1-laravel-initialization.md) | Laravel Project Initialization | Laravel 11, PostgreSQL, Redis config |
| [0.2](./0.2-development-tools.md) | Development Tools Setup | PHPStan, PHP CS Fixer, Pest |
| [0.3](./0.3-ci-cd-pipeline.md) | CI/CD Pipeline | GitHub Actions workflow |
| [0.4](./0.4-sentry-monitoring.md) | Sentry Error Monitoring | Error tracking, performance sampling |
| [0.5](./0.5-health-check.md) | Health Check Endpoint | GET /api/health |
| [0.6](./0.6-telescope-debugging.md) | Laravel Telescope | Local debugging dashboard |
| [0.7](./0.7-structured-logging.md) | Structured Logging | JSON log format support |
| [0.8](./0.8-gitignore-finalization.md) | Gitignore & Finalization | Cleanup, verification |

---

## Execution Order

Execute sub-phases in order (0.1 → 0.2 → ... → 0.8). Each sub-phase depends on the previous one being complete.

---

## Prerequisites Before Starting

- [ ] PHP 8.3+ installed
- [ ] Composer 2.x installed
- [ ] PostgreSQL 16 running
- [ ] Redis 7 running
- [ ] Git configured
- [ ] GitHub repository created
- [ ] Sentry account created (free tier)

---

## Quick Commands Reference

After Phase 0 completion, these commands will be available:

```bash
# Development
cd backend
php artisan serve          # Start dev server
php artisan test           # Run tests
composer analyse           # Static analysis
composer format            # Fix code style
composer format:check      # Check code style

# Health check
curl http://localhost:8000/api/health

# Telescope (local only)
# Visit http://localhost:8000/telescope
```

---

## Phase 0 Completion Criteria

All items must be checked before proceeding to Phase 1:

- [ ] Laravel 11 project in `backend/`
- [ ] PostgreSQL database `screenbuddies` created
- [ ] Redis configured for cache/queue/session
- [ ] PHPStan level 5 passing
- [ ] PHP CS Fixer check passing
- [ ] All Pest tests passing
- [ ] GitHub Actions CI green
- [ ] Sentry DSN configured
- [ ] `/api/health` returning 200
- [ ] Telescope accessible at `/telescope`
- [ ] JSON logging working
- [ ] All sensitive files in .gitignore
