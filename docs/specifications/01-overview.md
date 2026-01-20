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

| Type | Data Source |
|------|-------------|
| Movie | TMDB API |
| TV Show | TMDB API |
| Video Game | RAWG API |
| Board Game | BoardGameGeek API |
| Theater | TBD (scraping/API) |

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
| Duel choice | Binary only (no skip, no tie) |

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

| Platform | Supported |
|----------|-----------|
| iOS | Yes |
| Android | Yes |
| Web | Yes |
| Desktop | No (future) |

---

## Technical Stack

| Component | Technology |
|-----------|------------|
| Backend | Laravel (PHP) |
| Frontend | Flutter (Dart) |
| Authentication | JWT with refresh tokens |
| Languages | English, French (i18n ready) |
| Connectivity | Online only |
