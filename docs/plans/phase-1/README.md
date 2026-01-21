# Phase 1: Backend Foundation - Sub-Plans

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement these plans task-by-task.

**Goal:** Create database schema, models, and authentication infrastructure.

**Estimated Tasks:** 9 sub-phases, ~45 granular tasks total

---

## Sub-Phase Overview

| Sub-Phase | Description | Key Deliverables |
|-----------|-------------|------------------|
| [1.1](./1.1-sanctum-pest-setup.md) | Sanctum & Pest Setup | Token auth, testing framework |
| [1.2](./1.2-user-models.md) | User Models | User, UserPreference |
| [1.3](./1.3-media-type-model.md) | MediaType Model | Media categorization |
| [1.4](./1.4-friendship-model.md) | Friendship Model | Social relationships |
| [1.5](./1.5-election-model.md) | Election Model | Core election entity |
| [1.6](./1.6-candidate-model.md) | Candidate Model | Election items |
| [1.7](./1.7-voter-model.md) | Voter Model | Vote storage (JSON blob) |
| [1.8](./1.8-user-relationships.md) | User Relationships | Complete User model |
| [1.9](./1.9-seeders-finalization.md) | Seeders & Finalization | MediaType seeder, verification |

---

## Execution Order

Execute sub-phases in order (1.1 → 1.2 → ... → 1.9). Each sub-phase depends on the previous one being complete.

---

## Prerequisites Before Starting

- [ ] Phase 0 complete
- [ ] Laravel project initialized in `backend/`
- [ ] PostgreSQL database `screenbuddies` exists
- [ ] Redis running
- [ ] Pest testing framework installed

---

## Database Schema Overview

After Phase 1 completion:

```
users
├── user_preferences (1:1)
├── elections (1:N as maestro)
├── voters (1:N as participant)
├── friendships (1:N as requester/addressee)
└── candidates (1:N as suggester)

media_types
└── elections (1:N)

elections
├── candidates (1:N)
└── voters (1:N)
    └── votes (JSON blob)
```

---

## Key Architecture Decisions

1. **Vote Storage**: Votes stored as JSON in `voters.votes` field, not separate Duel table
   - Format: `{"1_3": 1, "2_5": null}` (smallerId_largerId: winnerId|null)
   - More scalable for elections with many candidates

2. **Token Expiration**:
   - Access token: 15 minutes
   - Refresh token: 7 days

3. **Media Types**:
   - Active: movie (TMDB), tvshow (TMDB), videogame (RAWG)
   - Placeholder: boardgame (BGG), theater (custom)

---

## Quick Verification Commands

After Phase 1 completion:

```bash
# Run all tests
cd backend && php artisan test

# Check database tables
psql screenbuddies -c '\dt'

# Check media types
php artisan tinker --execute="App\Models\MediaType::pluck('code')"

# Fresh migration with seed
php artisan migrate:fresh --seed
```

---

## Phase 1 Completion Criteria

All items must be checked before proceeding to Phase 2:

- [ ] Laravel Sanctum configured (15min access, 7day refresh)
- [ ] 7 models created (User, UserPreference, MediaType, Friendship, Election, Candidate, Voter)
- [ ] 2 enums created (ElectionStatus, FriendshipStatus)
- [ ] All relationships configured
- [ ] Vote JSON validation in Voter model
- [ ] MediaType seeder with 5 types
- [ ] All unit tests passing
- [ ] Static analysis passing
- [ ] Code style check passing
