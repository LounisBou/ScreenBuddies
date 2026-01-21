# ScreenBuddies

ScreenBuddies helps groups of friends decide what to watch or play together. Instead of endless debates, users vote through pairwise comparisons (duels), and the system finds the options everyone agrees on using the Condorcet voting method.

## Tech Stack

| Component | Technology |
|-----------|------------|
| Backend | Laravel 11 (PHP 8.3+) |
| Frontend | Flutter 3.x (Dart) |
| Database | PostgreSQL |
| Authentication | Laravel Sanctum (JWT-style tokens) |
| Platforms | iOS, Android, Web |
| Languages | English, French (i18n ready) |

## External APIs

- **TMDB** - Movies & TV Shows
- **RAWG** - Video Games
- **BoardGameGeek** - Board Games

---

## Documentation

### Specifications

Detailed technical specifications for the rewrite:

1. [Overview](docs/specifications/01-overview.md) - Vision, terminology, election lifecycle, rules
2. [Data Model](docs/specifications/02-data-model.md) - Complete MCD with 8 entities
3. [Backend Architecture](docs/specifications/03-backend-architecture.md) - Laravel structure, services, jobs
4. [Frontend Architecture](docs/specifications/04-frontend-architecture.md) - Flutter structure, providers, screens
5. [API Endpoints](docs/specifications/05-api-endpoints.md) - Complete REST API contracts

### Implementation Plans

Step-by-step implementation guides:

#### Backend (Phases 1-4)

1. [Phase 1 - Backend Foundation](docs/plans/phase-1-backend-foundation.md) - Laravel setup, JWT, migrations, models
2. [Phase 2 - Auth & Users](docs/plans/phase-2-auth-and-users.md) - Auth endpoints, profile, friendships
3. [Phase 3 - Election System](docs/plans/phase-3-election-system.md) - Elections, candidates, invitations, media search
4. [Phase 4 - Voting & Results](docs/plans/phase-4-voting-and-results.md) - Duels, Condorcet algorithm, results

#### Frontend (Phases 5-6)

5. [Phase 5 - Flutter Foundation](docs/plans/phase-5-flutter-foundation.md) - Flutter setup, Riverpod, GoRouter, auth screens
6. [Phase 6 - Flutter Election Features](docs/plans/phase-6-flutter-election-features.md) - Elections, voting, duel screen

---

## Key Concepts

| Term | Definition |
|------|------------|
| **Maestro** | User who creates an election |
| **Election** | A voting session to decide on content |
| **Candidate** | An item being voted on (movie, game, etc.) |
| **Voter** | A user participating in an election |
| **Duel** | Two candidates presented for comparison |
| **Campaign** | Optional suggestion phase before voting |

## Election Lifecycle

```
CREATED → CAMPAIGN (optional) → VOTING → ENDED → ARCHIVED
```

---

## Project Structure

```
ScreenBuddies/
├── docs/
│   ├── specifications/     # Technical specifications
│   └── plans/              # Implementation plans by phase
├── ScreenBuddiesBackoffice/ # Laravel backend (to be rewritten)
└── ScreenBuddiesFlutterApp/ # Flutter frontend (to be rewritten)
```

## License

Private project.
