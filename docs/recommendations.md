# Architecture & Technology Recommendations

**Date:** 2026-01-20
**Status:** Under Review

This document contains recommendations for improving the ScreenBuddies specifications and technology choices. Each recommendation should be reviewed and either accepted, rejected, or modified.

---

## Project Context

| Parameter | Value |
|-----------|-------|
| **Target Scale** | 100,000 users |
| **Platform** | iOS, Android, (Web TBD) |
| **Backend** | Laravel 11 / PHP 8.3 |
| **Database** | PostgreSQL |

---

## Review Process

**MANDATORY: Always start by explaining is detail the recommandation**
**MANDATORY: After each recommendation is accepted or modified:**

1. **Check all docs for collateral damage** - Search for affected terms/patterns
2. **Update all specification files** (01-overview.md through 05-api-endpoints.md)
3. **Update all plan files** (phase-1 through phase-6+)
4. **Update this document** with decision and notes
5. **Verify consistency** across all documentation
6. **Verify consistency AGAIN** Make a second pass of verification across all documentation

**Do NOT proceed to next recommendation until ALL collateral damages are fixed.**

---

## High Priority Recommendations

### R1: Switch from tymon/jwt-auth to Laravel Sanctum

**Current State:**
Using `tymon/jwt-auth` for JWT authentication with 15-min access tokens and 7-day refresh tokens.

**Problem:**
- `tymon/jwt-auth` is community-maintained, last major release was years ago
- Complex setup compared to official solutions
- 7-day refresh token is too long if compromised

**Recommendation:**
Switch to **Laravel Sanctum** with token-based authentication (not SPA mode).

**Benefits:**
- Official Laravel package, actively maintained
- Simpler API (no JWT complexity)
- Built-in token abilities/scopes
- Easy token revocation

**Trade-offs:**
- Tokens stored in database (requires DB lookup per request)
- Not truly stateless like JWT

**Implementation Impact:**
- Affects: Phase 1, Phase 2, Phase 5 (Flutter auth)
- Effort: Medium (auth rewrite)

**Decision:** [x] Accept  [ ] Reject  [ ] Modify

**Notes:**
- Accepted on 2026-01-20
- Scale target: 100,000 users - Sanctum DB lookups are fine at this scale
- Future optimization: Redis cache for token validation if scaling issues arise
- Simpler implementation and official Laravel support outweigh stateless benefits

---

### R2: Commit to PostgreSQL (Remove MySQL Option)

**Current State:**
Specs say "MySQL or PostgreSQL" without committing to one.

**Problem:**
- JSON query syntax differs between databases
- Index types differ (MySQL can't do GIN indexes on JSON)
- Migration and query code may not be portable
- Testing both is double the work

**Recommendation:**
Commit to **PostgreSQL** exclusively.

**Benefits:**
- Superior `jsonb` type with efficient indexing
- GIN indexes for JSON field queries
- Better CTEs and window functions (useful for Condorcet)
- More powerful aggregation functions

**Trade-offs:**
- Slightly more complex local setup than MySQL
- Some hosting providers default to MySQL

**Implementation Impact:**
- Affects: All phases with database work
- Effort: Low (just commit to the choice)

**Decision:** [x] Accept  [ ] Reject  [ ] Modify

**Notes:**
- Accepted on 2026-01-20
- PostgreSQL provides better JSON support with `jsonb` type
- GIN indexes will help with vote aggregation queries
- Better CTEs for Condorcet ranking calculations

---

### R3: Add "Skip" Option to Duels

**Current State:**
Binary duel choice only - no skip, no tie, no abstain.

**Problem:**
- User may not know either candidate (e.g., two obscure movies)
- Forcing a choice adds noise to the ranking data
- Frustrating UX when user genuinely can't decide

**Recommendation:**
Add a **"Skip" button** to the duel screen.

**Implementation:**
- Store skipped duels as `null` in JSON: `{"1_2": null, "1_3": 3}`
- Don't count skipped pairs toward rankings
- Track skip count separately for analytics
- No limit on skips (trust users)

**Benefits:**
- Cleaner ranking data (only informed choices)
- Better UX for users
- Can analyze which candidates are frequently skipped

**Trade-offs:**
- Users might skip too much (accepted risk - no limit)
- Slightly more complex UI
- Algorithm must handle pairs with fewer votes

**Implementation Impact:**
- Affects: Phase 4 (voting), Phase 6 (Flutter duel screen)
- Effort: Low-Medium

**Decision:** [x] Accept  [ ] Reject  [ ] Modify

**Notes:**
- Accepted on 2026-01-20
- No skip limit - trust users to make informed decisions
- Skipped duels stored as `null` in votes JSON
- Algorithm ignores skipped pairs in ranking calculations

---

### R4: Add Circuit Breaker for External APIs

**Current State:**
External APIs (TMDB, RAWG, BGG) are called directly with caching but no failure handling.

**Problem:**
- If TMDB is down, every search request will timeout
- Users see errors and can't proceed
- Cascading failures possible under load

**Recommendation:**
Implement **circuit breaker pattern** for all external API calls.

**Implementation:**
- Use a package like `ackintosh/ganesha` or build simple state machine
- States: Closed (normal) → Open (failing) → Half-Open (testing)
- When open: return cached results or graceful error immediately
- Add health check endpoint that reports circuit states

**Benefits:**
- Fast failure instead of timeouts
- Graceful degradation (show cached content)
- Prevents cascading failures
- Visibility into API health

**Trade-offs:**
- Additional complexity
- Need to tune thresholds (failure count, timeout duration)

**Implementation Impact:**
- Affects: Phase 3 (media providers)
- Effort: Medium

**Decision:** [x] Accept  [ ] Reject  [ ] Modify

**Notes:**
- Accepted on 2026-01-20
- Implementation: **ackintosh/ganesha** package
- Guzzle Middleware for transparent integration with TMDB/RAWG HTTP calls
- Storage: Redis (already planned for token caching)
- States: Closed (normal) → Open (failing) → Half-Open (testing)
- When open: return cached results or graceful error immediately
- Full documentation: `docs/circuit-breaker.md`

---

### R5: Define Infrastructure and DevOps Plan

**Current State:**
No mention of deployment, CI/CD, monitoring, or error tracking in specifications.

**Problem:**
- No clear path to production
- No error visibility in production
- No automated testing pipeline
- No backup/recovery strategy

**Recommendation:**
Add **Phase 0: Infrastructure Setup** before Phase 1, covering:

1. **Local Development:**
   - Docker Compose for Laravel + PostgreSQL + Redis
   - Consistent dev environment across team

2. **CI/CD Pipeline:**
   - GitHub Actions for automated testing
   - Lint, type check, test on every PR
   - Auto-deploy to staging on merge to main

3. **Error Monitoring:**
   - Sentry for backend exceptions
   - Sentry for Flutter crashes
   - Slack/email alerts for critical errors

4. **Logging:**
   - Structured JSON logging
   - Laravel Telescope for local debugging
   - Log aggregation for production (even simple CloudWatch/Papertrail)

5. **Health Checks:**
   - `/health` endpoint checking: DB, Redis, external APIs
   - Uptime monitoring (e.g., BetterUptime, UptimeRobot)

**Benefits:**
- Professional deployment pipeline
- Catch errors before users report them
- Reproducible environments
- Audit trail for debugging

**Trade-offs:**
- Initial setup time
- Monthly costs for monitoring services

**Implementation Impact:**
- New Phase 0 before all other phases
- Effort: Medium-High initially, but saves time long-term

**Decision:** [ ] Accept  [ ] Reject  [x] Modify

**Notes:**
- Accepted (modified) on 2026-01-20
- **Local Development:** Native PHP/PostgreSQL/Redis (NO Docker)
- **Hosting:** TBD (keep flexible for now)
- **Sentry:** Free tier to start (5K errors/month)
- **Phase 0** created as separate phase before Phase 1
- Full documentation: `docs/infrastructure.md`

---

## Medium Priority Recommendations

### R6: Reconsider Flutter Web Strategy

**Current State:**
Flutter targets iOS, Android, and Web.

**Problem:**
- Flutter Web bundle is 2-4MB (slow first load)
- No SEO (canvas-based rendering)
- Invite links shared on desktop lead to heavy web app
- Web support in Flutter is still maturing

**Recommendation:**
Choose one of these strategies:

**Option A: Mobile Only (Recommended for MVP)**
- Drop Flutter Web target
- Create simple landing page for web visitors: "Download the app"
- Invite link preview shows election title/description, then prompts app download

**Option B: Hybrid (If web is critical)**
- Keep Flutter for iOS/Android
- Build lightweight Next.js/React web app for:
  - Landing page
  - Join election preview
  - View results (read-only)
  - Share functionality
- Full voting experience mobile-only

**Benefits:**
- Option A: Faster development, better mobile UX focus
- Option B: Good web experience without Flutter limitations

**Trade-offs:**
- Option A: No web voting (users must install app)
- Option B: Two codebases to maintain

**Implementation Impact:**
- Affects: Phase 5, Phase 6, all Flutter phases
- Effort: Low (Option A) or High (Option B)

**Decision:** [ ] Accept Option A  [ ] Accept Option B  [x] Keep Flutter Web  [ ] Modify

**Notes:**
- Accepted (Keep Flutter Web) on 2026-01-21
- **Mobile first:** iOS and Android are primary platforms
- **Web as fallback:** Flutter Web for desktop users
- **No SEO requirements:** Canvas rendering is acceptable
- **Single codebase:** Flutter handles all 3 platforms
- Bundle size (2-4MB) acceptable for desktop fallback use case

---

### R7: Replace Fixed MIN_PAIR_DUELS with Confidence Interval Rule

**Current State:**
`MIN_PAIR_DUELS = 5` is a constant. Pairs with fewer than 5 votes are ignored in rankings.

**Problem:**
- Fixed threshold ignores **margin strength** — a 10-2 result is highly reliable, a 6-5 result is not
- With 3 voters, no pair will ever reach 5 votes
- Small elections (friends group) may not produce results
- One-size-fits-all approach is statistically naive

**Recommendation:**
Replace the fixed threshold with a **Confidence Interval Crossing Rule**:

A pair (i,j) is **reliable** if and only if the confidence interval for the true win probability **excludes 0.5**.

**Statistical Model:**
- Each duel is a Bernoulli trial with unknown true probability P(i,j)
- We observe: w_ij wins, w_ji losses, n = w_ij + w_ji total
- Observed win rate: p̂ = w_ij / n

**Reliability Formula (Normal Approximation):**

```
LCB = p̂ - z × √(p̂(1-p̂)/n)
UCB = p̂ + z × √(p̂(1-p̂)/n)
```

Where:
- `z = 1.96` for 95% confidence (recommended)
- `z = 1.28` for 80% confidence (more permissive)

**Reliability Condition:**
```
Pair is reliable ⟺ LCB > 0.5 OR UCB < 0.5
```

**How It Works:**

| Observed Result | n | p̂ | LCB (95%) | Reliable? |
|-----------------|---|-----|-----------|-----------|
| 8-2 (strong) | 10 | 0.80 | 0.55 | ✅ Yes (LCB > 0.5) |
| 6-4 (moderate) | 10 | 0.60 | 0.30 | ❌ No (crosses 0.5) |
| 15-10 (moderate, more data) | 25 | 0.60 | 0.41 | ❌ No |
| 18-7 (strong) | 25 | 0.72 | 0.54 | ✅ Yes |
| 3-0 (strong, few) | 3 | 1.00 | 0.29 | ❌ No (need more data) |

**Benefits:**
- **Adapts automatically**: Strong margins need fewer observations
- **Statistically sound**: Based on confidence interval theory
- **Works for all election sizes**: No arbitrary voter-count thresholds
- **Self-calibrating**: Close races naturally require more data

**Trade-offs:**
- Slightly more complex calculation
- Need to choose confidence level (95% recommended)
- Very close races may never become reliable (acceptable)

**Implementation Impact:**
- Affects: Phase 4 (CondorcetService)
- Effort: Low-Medium (formula change + documentation)
- Full details: `docs/condorcet-implementation.md`

**Decision:** [x] Accept  [ ] Reject  [ ] Modify

**Notes:**
- Accepted on 2026-01-21
- **DO NOT** use fixed MIN_PAIR_DUELS; use confidence interval rule
- Default confidence level: 95% (z = 1.96)
- Full implementation details in `docs/condorcet-implementation.md`

---

### R8: Remove or Fully Implement BGG and Theater

**Current State:**
- BGG (BoardGameGeek) provider is "placeholder"
- Theater media type is "TBD (scraping/API)"

**Problem:**
- Half-implemented features confuse users
- BGG API is XML-based, unofficial, and slow
- Theater has no viable API source
- Maintenance burden for rarely-used features

**Recommendation:**
For MVP, **reduce media types to 3:**
- Movie (TMDB)
- TV Show (TMDB)
- Video Game (RAWG)

Remove Board Game and Theater from:
- MediaType seeder
- Media provider interface
- UI media type selector

**Add back later** when there's user demand and a viable implementation path.

**Benefits:**
- Cleaner codebase
- No broken/incomplete features
- Focus on what works well

**Trade-offs:**
- Less feature variety at launch
- Some users may want board games

**Implementation Impact:**
- Affects: Phase 1 (seeder), Phase 3 (providers), Phase 7 (creation UI)
- Effort: Low (removal is easy)

**Decision:** [ ] Accept  [ ] Reject  [x] Defer

**Notes:**
- Deferred on 2026-01-21
- Keep BGG and Theater as placeholders for now
- Added to `docs/future-ideas.md` for future consideration
- Will revisit when there's user demand or better API options

---

### R9: Add Error Monitoring (Sentry)

**Current State:**
No error monitoring mentioned in specifications.

**Problem:**
- Production errors go unnoticed until users complain
- No stack traces or context for debugging
- Flutter crashes are invisible

**Recommendation:**
Add **Sentry** integration for both backend and frontend:

**Backend (Laravel):**
```bash
composer require sentry/sentry-laravel
```

**Frontend (Flutter):**
```yaml
dependencies:
  sentry_flutter: ^7.0.0
```

**Configuration:**
- Capture unhandled exceptions automatically
- Add user context (user ID, email) for debugging
- Set up alerts for error spikes
- Filter out known/expected errors

**Benefits:**
- Immediate visibility into production issues
- Full stack traces with context
- Release tracking (which version introduced bug)
- Performance monitoring included

**Trade-offs:**
- Sentry costs money at scale (free tier is generous though)
- Small performance overhead
- Privacy consideration (user data in error reports)

**Implementation Impact:**
- Affects: Phase 1 (backend), Phase 5 (Flutter)
- Effort: Low

**Decision:** [ ] Accept  [ ] Reject  [ ] Modify

**Notes:**
_To be filled during review_

---

## Low Priority Recommendations

### R10: Add Basic Offline Support

**Current State:**
Explicitly "Online only (no offline support)".

**Problem:**
- Mobile apps often have spotty connectivity
- User opens app on subway, sees error
- Election data they were just viewing disappears

**Recommendation:**
Add **read-only offline support** for:
- Current user's elections list
- Election details (candidates, current results)
- User's own vote history

**Implementation:**
- Cache API responses locally (Hive or SQLite)
- Show cached data when offline with "Offline" indicator
- Disable voting/creation when offline
- Sync when connection returns

**Benefits:**
- Better UX in low-connectivity situations
- App feels faster (show cache, then refresh)
- Users can review their elections anytime

**Trade-offs:**
- Additional complexity
- Cache invalidation challenges
- Storage usage on device

**Implementation Impact:**
- Affects: Phase 5, Phase 6 (Flutter data layer)
- Effort: Medium-High

**Decision:** [ ] Accept  [ ] Reject  [ ] Defer

**Notes:**
_To be filled during review_

---

### R11: Shorten Refresh Token Lifetime

**Current State:**
Refresh token valid for 7 days.

**Problem:**
- If refresh token is compromised, attacker has 7 days of access
- No way to detect stolen tokens
- Long-lived tokens are security risk

**Recommendation:**
Reduce refresh token lifetime to **24-48 hours** with **sliding window**:
- Each successful refresh extends the window
- Active users stay logged in indefinitely
- Inactive users (or stolen tokens) expire quickly

**Alternative:** If using Sanctum (R1), tokens can be revoked explicitly and have configurable expiration.

**Benefits:**
- Smaller attack window for stolen tokens
- Encourages regular token rotation
- Better security posture

**Trade-offs:**
- Users inactive for 48h need to re-login
- Slightly more token refresh traffic

**Implementation Impact:**
- Affects: Phase 2 (auth config)
- Effort: Low

**Decision:** [ ] Accept  [ ] Reject  [ ] Modify

**Notes:**
_To be filled during review_

---

### R12: Add JSON Schema Validation for Votes

**Current State:**
Voter.votes is a JSON blob with no schema enforcement.

**Problem:**
- Invalid JSON structure could break ranking algorithm
- No validation that keys follow `{smaller}_{larger}` format
- No validation that values are valid candidate IDs

**Recommendation:**
Add **validation in Laravel model** accessor/mutator:

```php
// In Voter model
public function setVotesAttribute(array $votes): void
{
    foreach ($votes as $key => $winnerId) {
        // Validate key format
        if (!preg_match('/^\d+_\d+$/', $key)) {
            throw new InvalidArgumentException("Invalid vote key: $key");
        }

        // Validate smaller_larger ordering
        [$a, $b] = explode('_', $key);
        if ((int)$a >= (int)$b) {
            throw new InvalidArgumentException("Vote key must be smaller_larger: $key");
        }

        // Validate winner is one of the pair (or null for skip)
        if ($winnerId !== null && $winnerId != $a && $winnerId != $b) {
            throw new InvalidArgumentException("Winner must be one of the candidates: $winnerId");
        }
    }

    $this->attributes['votes'] = json_encode($votes);
}
```

**Benefits:**
- Data integrity guaranteed
- Bugs caught early (at write time)
- Clearer error messages

**Trade-offs:**
- Slight performance overhead on vote recording
- Need to update if vote format changes

**Implementation Impact:**
- Affects: Phase 1 (Voter model), Phase 4 (voting)
- Effort: Low

**Decision:** [ ] Accept  [ ] Reject  [ ] Modify

**Notes:**
_To be filled during review_

---

## Summary Table

| ID | Recommendation | Priority | Effort | Status |
|----|----------------|----------|--------|--------|
| R1 | Switch to Laravel Sanctum | High | Medium | Accepted |
| R2 | Commit to PostgreSQL | High | Low | Accepted |
| R3 | Add Skip option to duels | High | Low-Med | Accepted |
| R4 | Add circuit breaker for APIs | High | Medium | Accepted |
| R5 | Define infrastructure plan | High | Med-High | Modified |
| R6 | Reconsider Flutter Web | Medium | Varies | Keep Flutter Web |
| R7 | Confidence interval reliability rule | Medium | Low-Med | Accepted |
| R8 | Remove BGG and Theater | Medium | Low | Deferred |
| R9 | Add Sentry error monitoring | Medium | Low | Pending |
| R10 | Add offline support | Low | Med-High | Pending |
| R11 | Shorten refresh token | Low | Low | Pending |
| R12 | Add JSON schema validation | Low | Low | Pending |

---

## Review Log

| Date | Recommendation | Decision | Notes |
|------|----------------|----------|-------|
| 2026-01-20 | R1: Switch to Sanctum | **Accepted** | Redis cache for future scaling if needed |
| 2026-01-20 | R2: Commit to PostgreSQL | **Accepted** | Better JSON/jsonb support, GIN indexes |
| 2026-01-20 | R3: Add Skip to Duels | **Accepted** | No skip limit, store as null in JSON |
| 2026-01-20 | R4: Circuit Breaker | **Accepted** | Using ackintosh/ganesha with Guzzle Middleware |
| 2026-01-20 | R5: Infrastructure | **Modified** | No Docker, Sentry free tier, Phase 0 created |
| 2026-01-21 | R6: Flutter Web | **Keep Flutter Web** | Mobile first, web fallback, no SEO, single codebase |
| 2026-01-21 | R7: Confidence Interval Rule | **Accepted** | Replace fixed MIN_PAIR_DUELS with statistical reliability |
| 2026-01-21 | R8: BGG and Theater | **Deferred** | Keep as placeholders, added to future-ideas.md |

