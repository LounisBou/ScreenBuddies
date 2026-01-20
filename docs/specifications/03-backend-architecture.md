# ScreenBuddies - Backend Architecture

## Overview

RESTful API built with Laravel, using modern PHP practices and Laravel conventions.

---

## Technology Stack

| Component | Technology | Version |
|-----------|------------|---------|
| Framework | Laravel | 11.x |
| PHP | PHP | 8.3+ |
| Database | MySQL / PostgreSQL | Latest |
| Cache | Redis | Latest |
| Queue | Redis / Database | - |
| Auth | JWT (tymon/jwt-auth) | Latest |
| API Docs | Scribe or Scramble | Latest |
| Testing | PHPUnit + Pest | Latest |

---

## Directory Structure

```
app/
├── Console/
│   └── Commands/
│       ├── ArchiveElections.php      # Scheduled: archive old elections
│       └── SendDeadlineReminders.php # Scheduled: notify before deadline
│
├── Exceptions/
│   └── Handler.php                   # Global exception handling
│
├── Http/
│   ├── Controllers/
│   │   └── Api/
│   │       └── V1/
│   │           ├── AuthController.php
│   │           ├── UserController.php
│   │           ├── FriendshipController.php
│   │           ├── ElectionController.php
│   │           ├── CandidateController.php
│   │           ├── VoterController.php
│   │           ├── VotingController.php      # Handles duel voting (stored in Voter.votes JSON)
│   │           ├── MediaSearchController.php
│   │           └── Admin/
│   │               ├── UserController.php
│   │               └── ElectionController.php
│   │
│   ├── Middleware/
│   │   ├── JwtAuthenticate.php
│   │   ├── EnsureEmailVerified.php
│   │   ├── EnsureAdmin.php
│   │   └── SetLocale.php
│   │
│   ├── Requests/
│   │   ├── Auth/
│   │   │   ├── RegisterRequest.php
│   │   │   ├── LoginRequest.php
│   │   │   └── ResetPasswordRequest.php
│   │   ├── Election/
│   │   │   └── CreateElectionRequest.php
│   │   ├── Candidate/
│   │   │   └── SuggestCandidateRequest.php
│   │   └── Voting/
│   │       └── CastVoteRequest.php
│   │
│   └── Resources/
│       ├── UserResource.php
│       ├── ElectionResource.php
│       ├── ElectionDetailResource.php
│       ├── CandidateResource.php
│       ├── VoterResource.php
│       ├── NextDuelResource.php          # Response for next duel to vote on
│       └── FriendshipResource.php
│
├── Models/
│   ├── User.php
│   ├── UserPreference.php
│   ├── Friendship.php
│   ├── MediaType.php
│   ├── Election.php
│   ├── Candidate.php
│   └── Voter.php              # Contains votes JSON for compact duel storage
│
├── Notifications/
│   ├── EmailVerification.php
│   ├── PasswordReset.php
│   ├── DeadlineReminder.php
│   ├── ElectionEnded.php
│   └── FriendshipRequest.php
│
├── Policies/
│   ├── ElectionPolicy.php
│   ├── CandidatePolicy.php
│   └── VoterPolicy.php
│
├── Providers/
│   ├── AppServiceProvider.php
│   ├── AuthServiceProvider.php
│   └── EventServiceProvider.php
│
└── Services/
    ├── Auth/
    │   ├── JwtService.php
    │   └── PasswordResetService.php
    │
    ├── Election/
    │   ├── ElectionService.php
    │   ├── DuelGeneratorService.php
    │   └── CondorcetService.php       # Ranking algorithm
    │
    ├── Media/
    │   ├── MediaSearchService.php     # Facade for all providers
    │   ├── Contracts/
    │   │   └── MediaProviderInterface.php
    │   └── Providers/
    │       ├── TmdbProvider.php       # Movies & TV Shows
    │       ├── RawgProvider.php       # Video Games
    │       └── BggProvider.php        # Board Games
    │
    └── Notification/
        ├── EmailService.php
        └── PushService.php

config/
├── jwt.php
├── media.php                          # API keys, rate limits
└── election.php                       # Business rules (limits, etc.)

database/
├── migrations/
├── factories/
└── seeders/
    └── MediaTypeSeeder.php

routes/
├── api.php                            # API v1 routes
└── console.php                        # Scheduled commands

tests/
├── Feature/
│   ├── Auth/
│   ├── Election/
│   ├── Voting/
│   └── Admin/
└── Unit/
    ├── Services/
    └── Models/
```

---

## Authentication Flow

### JWT Token Strategy

```
┌─────────┐     ┌─────────┐     ┌─────────┐
│  Login  │────►│  JWT    │────►│ Refresh │
│         │     │ Access  │     │  Token  │
└─────────┘     │ (15min) │     │ (7days) │
                └─────────┘     └─────────┘
                     │               │
                     ▼               ▼
              ┌─────────────────────────┐
              │    API Requests with    │
              │   Authorization Header  │
              └─────────────────────────┘
```

| Token | Lifetime | Purpose |
|-------|----------|---------|
| Access Token | 15 minutes | API authentication |
| Refresh Token | 7 days | Get new access token |

### Auth Endpoints

| Endpoint | Auth | Description |
|----------|------|-------------|
| POST /auth/register | No | Create account |
| POST /auth/login | No | Get tokens |
| POST /auth/refresh | Refresh | Get new access token |
| POST /auth/logout | Access | Invalidate tokens |
| POST /auth/forgot-password | No | Request reset email |
| POST /auth/reset-password | No | Set new password |
| POST /auth/verify-email | No | Confirm email (token in URL) |
| POST /auth/resend-verification | Access | Resend verification email |

---

## Authorization

### Policies

**ElectionPolicy:**
- `view`: Voter in election OR Maestro
- `update`: Never (election locked after creation)
- `delete`: Admin only
- `close`: Maestro only
- `getInviteLink`: Maestro only

**CandidatePolicy:**
- `suggest`: Voter, election allows suggestions, campaign phase active
- `approve`: Maestro, manual approval enabled

**VoterPolicy:**
- `vote`: Voter in election, voting phase active

---

## Service Layer

### MediaSearchService

Unified interface for searching media across providers.

```php
interface MediaProviderInterface
{
    public function search(string $query, int $page = 1): PaginatedResults;
    public function getById(string $externalId): MediaItem;
    public function getType(): string; // movie, tvshow, videogame, boardgame
}
```

Each provider implements this interface, normalizing responses to:

```php
class MediaItem
{
    public string $externalId;
    public string $title;
    public ?string $posterUrl;
    public ?int $year;
    public array $metadata; // Provider-specific data
}
```

### CondorcetService

Calculates rankings from duel results using Ranked Pairs algorithm.
Aggregates votes from all `Voter.votes` JSON blobs in the election.

```php
class CondorcetService
{
    /**
     * Build preference graph by aggregating all voters' JSON votes.
     * @return array<int, array<int, int>> [candidateA][candidateB] = win count
     */
    public function buildPreferenceGraph(Election $election): array;

    /**
     * Calculate rankings using Ranked Pairs with confidence-weighting.
     * See docs/condorcet-implementation.md for algorithm details.
     * @return Collection<Candidate> Ordered by preference
     */
    public function calculateRankings(Election $election): Collection;

    /**
     * Get top K winners
     */
    public function getWinners(Election $election): Collection;

    /**
     * Compute pairwise stats from all voters
     * @return array{wins_ij: int, wins_ji: int, total: int}[]
     */
    public function computePairwiseStats(Election $election): array;
}
```

### DuelGeneratorService

Generates optimal duels for voters. Reads completed pairs from `Voter.votes` JSON.

```php
class DuelGeneratorService
{
    /**
     * Get next duel for voter using active selection algorithm.
     * Checks Voter.votes JSON to find unvoted pairs.
     * Prioritizes pairs near the top-k boundary (see Condorcet doc).
     * @return ?array{candidate_a: Candidate, candidate_b: Candidate}
     */
    public function getNextDuel(Voter $voter): ?array;

    /**
     * Check if voter has completed all possible duels
     * (or stopping condition reached per Condorcet algorithm)
     */
    public function isComplete(Voter $voter): bool;

    /**
     * Get progress stats for voter
     * @return array{completed: int, total: int, percentage: float}
     */
    public function getProgress(Voter $voter): array;

    /**
     * Record a vote in the voter's JSON blob
     * @param int $candidateA Smaller candidate ID
     * @param int $candidateB Larger candidate ID
     * @param int $winnerId The chosen winner
     */
    public function recordVote(Voter $voter, int $candidateA, int $candidateB, int $winnerId): void;
}
```

---

## Background Jobs

### Scheduled Commands

| Command | Schedule | Description |
|---------|----------|-------------|
| `election:archive` | Hourly | Archive elections 24h after election_date |
| `election:close-expired` | Every minute | Close elections past deadline |
| `election:send-reminders` | Hourly | Send deadline reminders (24h, 1h before) |

### Queue Jobs

| Job | Queue | Description |
|-----|-------|-------------|
| `SendDeadlineReminder` | emails | Send reminder notification |
| `SendElectionResults` | emails | Send results when election ends |
| `ProcessPushNotification` | notifications | Send push via FCM |

---

## Caching Strategy

| Data | TTL | Key Pattern |
|------|-----|-------------|
| Media search results | 1 hour | `media:{type}:search:{query}:{page}` |
| Media item details | 24 hours | `media:{type}:item:{externalId}` |
| Election rankings | 5 minutes | `election:{id}:rankings` |
| User profile | 15 minutes | `user:{id}:profile` |

---

## Error Handling

### Standard Error Response

```json
{
    "error": {
        "code": "VALIDATION_ERROR",
        "message": "The given data was invalid.",
        "details": {
            "email": ["The email field is required."]
        }
    }
}
```

### Error Codes

| Code | HTTP | Description |
|------|------|-------------|
| VALIDATION_ERROR | 422 | Request validation failed |
| UNAUTHORIZED | 401 | Missing or invalid token |
| FORBIDDEN | 403 | Action not allowed |
| NOT_FOUND | 404 | Resource not found |
| ELECTION_CLOSED | 400 | Election not in voting phase |
| PAIR_ALREADY_VOTED | 400 | This pair already voted on |
| CANDIDATE_LIMIT_REACHED | 400 | Max 30 candidates |
| EMAIL_NOT_VERIFIED | 403 | Action requires verified email |

---

## Rate Limiting

| Endpoint Group | Limit | Window |
|----------------|-------|--------|
| Auth (login, register) | 10 | 1 minute |
| Media search | 30 | 1 minute |
| General API | 60 | 1 minute |
| Admin endpoints | 120 | 1 minute |

---

## Environment Configuration

```env
# Application
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.screenbuddies.app

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=screenbuddies
DB_USERNAME=app
DB_PASSWORD=secret

# JWT
JWT_SECRET=your-secret-key
JWT_TTL=15
JWT_REFRESH_TTL=10080

# Redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379

# Mail
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailgun.org

# External APIs
TMDB_API_KEY=your-key
RAWG_API_KEY=your-key
BGG_API_URL=https://boardgamegeek.com/xmlapi2

# Push Notifications
FCM_SERVER_KEY=your-key
```

---

## Testing Strategy

### Unit Tests
- Models: relationships, scopes, accessors
- Services: business logic in isolation
- Policies: authorization rules

### Feature Tests
- Full HTTP request/response cycle
- Authentication flows
- Election lifecycle (create → vote → results)
- Edge cases (deadline, limits)

### Test Coverage Goals
- Services: 90%+
- Controllers: 80%+
- Models: 70%+
