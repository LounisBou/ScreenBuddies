# ScreenBuddies - Project Overview

## Vision

ScreenBuddies helps groups of friends decide what to watch or play together. Instead of endless debates, users vote through pairwise comparisons, and the system finds the options everyone agrees on.

---

## Terminology

| Term | Definition |
|------|------------|
| **Maestro** | User who creates an election |
| **Election** | A voting session to decide on content |
| **Candidate** | An item being voted on (movie, game, etc.) |
| **Voter** | A user participating in an election |
| **Duel** | Two candidates presented to a voter for comparison |
| **Campaign** | Optional suggestion phase before voting begins |
| **K** | Number of winners the election will select |

---

## Election Types

All types share identical voting behavior. Only the data source differs.

| Type | Data Source | Status |
|------|-------------|--------|
| Movie | TMDB API | ✅ Ready |
| TV Show | TMDB API | ✅ Ready |
| Video Game | RAWG API | ✅ Ready |
| Board Game | BoardGameGeek API | ⚠️ Placeholder |
| Theater | TBD | ⚠️ No API identified |

> **Note:** Board Game and Theater are placeholders for future implementation. See `docs/future-ideas.md` for details.

---

## Core User Story

> Friends plan a movie night but can't agree on what to watch. They create a ScreenBuddies election, add movie candidates from TMDB, and invite everyone. Each voter sees random pairs of movies and picks their favorite. When voting ends, the app reveals the top K movies everyone agreed on.

---

## Election Lifecycle

```
┌─────────────┐
│   CREATED   │ Maestro sets up election, adds candidates
└──────┬──────┘
       │
       ▼ (if campaign enabled)
┌─────────────┐
│  CAMPAIGN   │ Voters suggest candidates until campaign end date
└──────┬──────┘
       │
       ▼
┌─────────────┐
│   VOTING    │ Voters compare pairs in duels
└──────┬──────┘
       │ (deadline reached / graph complete / maestro closes)
       ▼
┌─────────────┐
│    ENDED    │ Results revealed (winners + stats)
└──────┬──────┘
       │ (24h after election date)
       ▼
┌─────────────┐
│  ARCHIVED   │ Read-only, accessible to validated users
└─────────────┘
```

---

## Key Dates

| Date | Purpose |
|------|---------|
| **Campaign End** | (Optional) When suggestion phase closes |
| **Deadline** | Latest time voting can continue |
| **Election Date** | When the actual event occurs (movie night, etc.) |
| **Archive Date** | Election date + 24 hours |

---

## Election Rules

| Rule | Value |
|------|-------|
| Minimum candidates | 2 |
| Maximum candidates | 30 |
| Winners (K) | 1-5, must be < candidate count |
| Duel choice | Choose winner or skip (no tie) |

---

## Account Levels

| Level | Capabilities |
|-------|--------------|
| **Unvalidated** | Vote on elections (via invite link) |
| **Validated** | Create elections, access archived elections, manage friends |
| **Admin** | Manage users (ban/delete), moderate elections |

---

## Voting Algorithm

Uses **Condorcet method with incomplete duels**:

- Build directed graph: edge A→B means majority prefers A over B
- Works with partial data (not all pairs need voting)
- Finds top K winners by consensus
- Mathematical details defined separately

---

## Platform Targets

| Platform | Priority | Notes |
|----------|----------|-------|
| iOS | Primary | Mobile first |
| Android | Primary | Mobile first |
| Web | Fallback | Desktop users via Flutter Web |
| Desktop | No | Not planned |

> **Strategy:** Mobile first (iOS/Android), Web as fallback for desktop users. Single Flutter codebase for all platforms. No SEO requirements.

---

## Technical Stack

| Component | Technology |
|-----------|------------|
| Backend | Laravel (PHP) |
| Frontend | Flutter (Dart) |
| Authentication | Laravel Sanctum (API tokens) |
| Circuit Breaker | ackintosh/ganesha (for external APIs) |
| Local Storage | Hive (offline cache) |
| Languages | English, French (i18n ready) |
| Connectivity | Online required for actions, read-only offline mode |

> **Offline Support:** Users can view cached elections, details, and results when offline. Voting and creation require online connectivity.
