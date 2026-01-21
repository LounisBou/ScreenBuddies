# Phase 4: Voting & Results - Sub-Plans

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement these plans task-by-task.

**Goal:** Implement duel voting system, Condorcet ranking algorithm (Ranked Pairs), and scheduled commands.

**Detailed Implementation:** See `docs/plans/phase-4-voting-and-results.md` for complete code.

---

## Sub-Phase Overview

| Sub-Phase | Tasks | Description |
|-----------|-------|-------------|
| 4.1 | DuelGeneratorService | Generate pairs, track progress, record votes in JSON |
| 4.2 | Voting Controller | Vote endpoints: next duel, cast vote, history |
| 4.3 | CondorcetService | Ranked Pairs algorithm with confidence-weighting |
| 4.4 | Results Controller | Results endpoint with rankings and stats |
| 4.5 | Scheduled Commands | Close expired elections, archive old ones |
| 4.6 | Finalization | Full test suite |

---

## Key Architecture

**Compact Storage:**
- Votes stored as JSON blob in `Voter.votes` field (not separate Duel rows)
- Format: `{"1_2": 1, "1_3": 3, "2_3": null, ...}`
- Key = `{smaller_id}_{larger_id}`, Value = winner's ID or null (skip)

**Algorithm:**
- Ranked Pairs (Tideman) with confidence-weighting
- Statistical reliability check using 95% confidence interval
- Laplace smoothing for edge strength calculation

---

## Key Files Created

```
backend/
├── app/
│   ├── Services/Election/
│   │   ├── DuelGeneratorService.php
│   │   └── CondorcetService.php
│   ├── Console/Commands/
│   │   ├── CloseExpiredElections.php
│   │   └── ArchiveOldElections.php
│   └── Http/
│       ├── Controllers/Api/V1/
│       │   ├── VotingController.php
│       │   └── ResultsController.php
│       ├── Requests/Voting/
│       │   └── CastVoteRequest.php
│       └── Resources/
│           └── NextDuelResource.php
└── routes/
    └── console.php (scheduler)
```

---

## API Endpoints Created

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | /api/v1/elections/{uuid}/vote/next | Get next duel |
| POST | /api/v1/elections/{uuid}/vote | Cast vote |
| GET | /api/v1/elections/{uuid}/vote/history | Get vote history |
| GET | /api/v1/elections/{uuid}/results | Get results (ended only) |

---

## Phase 4 Completion Criteria

- [ ] DuelGeneratorService with JSON vote storage
- [ ] Voting endpoints (next, vote, history)
- [ ] CondorcetService with Ranked Pairs
- [ ] Confidence-weighted edge strength
- [ ] Results endpoint
- [ ] Scheduled commands
- [ ] All tests passing
