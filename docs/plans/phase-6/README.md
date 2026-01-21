# Phase 6: Flutter Election Features - Sub-Plans

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement these plans task-by-task.

**Goal:** Implement election list, creation flow, detail view, voting/duel screen, and results display.

**Architecture:** Feature-based organization with providers per feature. Reusable widgets for elections and candidates. Multi-step creation flow.

**Tech Stack:** Flutter 3.x, Riverpod 2.x, GoRouter, Dio, Hive, cached_network_image

**Offline Support:** Election list, details, and results support read-only offline mode. Voting requires online connectivity.

**Prerequisites:** Phase 5 complete (Flutter foundation with auth)

---

## Sub-Phase Structure

| Sub-Phase | Description | Tasks |
|-----------|-------------|-------|
| 6.1 | Data Models | Task 1: Election, Candidate, MediaType, Duel models |
| 6.2 | Elections Provider | Task 2: List elections, create election |
| 6.3 | Media Search Provider | Task 3: Search for candidates |
| 6.4 | Voting Provider | Task 4: Duel loading and vote casting |
| 6.5 | Election Widgets | Tasks 5, 5.5: Card, badge, offline components |
| 6.6 | Home Screen Update | Task 6: Display elections list |
| 6.7 | Duel Screen | Task 7: Voting UI with duel cards |
| 6.8 | Finalization | Tasks 8-9: Router updates, integration test |

---

## Models Overview

```
Election
├── uuid
├── title
├── description?
├── mediaType
├── status (draft|campaign|voting|ended|archived)
├── electionDate
├── deadline
├── winnerCount
└── voterCount

ElectionDetail extends Election
├── maestro
├── candidates[]
├── voters[]
├── allowSuggestions
└── autoApprove

Candidate
├── id
├── externalId
├── title
├── posterUrl?
├── year?
├── metadata?
└── isApproved

Duel
├── candidateA
├── candidateB
└── progress
    ├── completed
    ├── total
    └── percentage
```

---

## API Endpoints Used

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | /elections | List user's elections |
| POST | /elections | Create new election |
| GET | /elections/{uuid} | Get election details |
| GET | /elections/{uuid}/vote/next | Get next duel pair |
| POST | /elections/{uuid}/vote | Cast vote |
| GET | /elections/{uuid}/results | Get results |
| GET | /media/search | Search media (TMDB, RAWG) |

---

## Providers Structure

```
electionsProvider (StateNotifier)
├── ElectionsState
│   ├── elections: List<Election>
│   ├── isLoading: bool
│   └── error: String?
└── Methods
    ├── loadElections()
    └── createElection(...)

electionDetailProvider (FutureProvider.family<ElectionDetail, String>)

mediaSearchProvider (StateNotifier)
├── MediaSearchState
│   ├── results: List<MediaItem>
│   ├── isLoading
│   ├── hasMore
│   └── currentPage
└── Methods
    ├── search(type, query)
    ├── loadMore()
    └── clear()

votingProvider (StateNotifier.family<VotingState, String>)
├── VotingState
│   ├── currentDuel: Duel?
│   ├── isComplete: bool
│   ├── isLoading: bool
│   └── isVoting: bool
└── Methods
    ├── loadNextDuel()
    ├── vote(winnerId)
    └── skip()
```

---

## Screen Flow

```
┌─────────────────────────────────────────────────┐
│                 Home Screen                      │
│              (Elections List)                    │
└─────────────────┬───────────────────────────────┘
                  │
        ┌─────────┼─────────┐
        │         │         │
        ▼         ▼         ▼
┌───────────┐ ┌───────────┐ ┌───────────────┐
│  Create   │ │ Election  │ │  Join via     │
│ Election  │ │  Detail   │ │  Invite Link  │
└───────────┘ └─────┬─────┘ └───────────────┘
                    │
          ┌─────────┼─────────┐
          │         │         │
          ▼         ▼         ▼
    ┌──────────┐ ┌──────┐ ┌─────────┐
    │ Vote     │ │Results│ │ Share   │
    │(Duel)    │ │Screen │ │ Invite  │
    └──────────┘ └──────┘ └─────────┘
```

---

## Execution Order

Execute sub-phases in order:

1. **6.1-data-models.md** - Create all data models
2. **6.2-elections-provider.md** - Elections state management
3. **6.3-media-search-provider.md** - Media search for candidates
4. **6.4-voting-provider.md** - Voting state management
5. **6.5-election-widgets.md** - Reusable widgets
6. **6.6-home-screen-update.md** - Elections list display
7. **6.7-duel-screen.md** - Voting UI
8. **6.8-finalization.md** - Router and integration

---

## Remaining Work (Future)

These features are scaffolded but need full implementation in future phases:

1. **Election Creation Flow** - Multi-step wizard with media search
2. **Election Detail Screen** - Full info, candidates, voters, actions
3. **Results Screen** - Winners, rankings, statistics
4. **Join Election Flow** - Deep links, invite sharing
5. **Push Notifications** - FCM setup and handling

---

## Reference

Main plan: `docs/plans/phase-6-flutter-election-features.md`
