# Phase 3: Election System - Sub-Plans

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement these plans task-by-task.

**Goal:** Implement election creation, candidate management, media search, and join system.

**Detailed Implementation:** See `docs/plans/phase-3-election-system.md` for complete code.

---

## Sub-Phase Overview

| Sub-Phase | Tasks | Description |
|-----------|-------|-------------|
| 3.1 | Config & Circuit Breaker | election.php, media.php, Ganesha setup |
| 3.2 | Media DTOs & Interface | MediaItem, PaginatedResults, MediaProviderInterface |
| 3.3 | TMDB Provider | Movie and TV show search via TMDB API |
| 3.4 | RAWG Provider | Video game search via RAWG API |
| 3.5 | MediaSearchService | Unified media search facade |
| 3.6 | Media Search Controller | API endpoints for media search |
| 3.7 | ElectionService | Election business logic |
| 3.8 | Election Controller | Election CRUD endpoints |
| 3.9 | Join Election System | Invite links and join flow |
| 3.10 | Finalization | Full test suite |

---

## Key Files Created

```
backend/
├── config/
│   ├── election.php
│   ├── media.php
│   └── ganesha.php
├── app/
│   ├── Services/
│   │   ├── CircuitBreaker/
│   │   │   └── GaneshaService.php
│   │   ├── Media/
│   │   │   ├── Contracts/MediaProviderInterface.php
│   │   │   ├── DTOs/MediaItem.php
│   │   │   ├── DTOs/PaginatedResults.php
│   │   │   ├── Providers/TmdbProvider.php
│   │   │   ├── Providers/RawgProvider.php
│   │   │   └── MediaSearchService.php
│   │   └── Election/
│   │       └── ElectionService.php
│   └── Http/
│       ├── Controllers/Api/V1/
│       │   ├── MediaSearchController.php
│       │   └── ElectionController.php
│       ├── Requests/Election/
│       │   └── CreateElectionRequest.php
│       └── Resources/
│           ├── ElectionResource.php
│           ├── ElectionDetailResource.php
│           ├── CandidateResource.php
│           └── VoterResource.php
└── database/factories/
    └── ElectionFactory.php
```

---

## API Endpoints Created

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | /api/v1/media/search | Search media by type |
| GET | /api/v1/media/{type}/{id} | Get media details |
| GET | /api/v1/elections | List user's elections |
| POST | /api/v1/elections | Create election |
| GET | /api/v1/elections/{uuid} | Get election details |
| PUT | /api/v1/elections/{uuid}/close | Close election |
| GET | /api/v1/elections/{uuid}/invite-link | Get invite link |
| GET | /api/v1/elections/join/{token} | Preview election (public) |
| POST | /api/v1/elections/join/{token} | Join election |

---

## Phase 3 Completion Criteria

- [ ] Config files created
- [ ] Circuit breaker (Ganesha) configured
- [ ] TMDB provider with caching
- [ ] RAWG provider with caching
- [ ] MediaSearchService facade
- [ ] ElectionService with validation
- [ ] Election CRUD working
- [ ] Join system with invite tokens
- [ ] All tests passing
