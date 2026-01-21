# ScreenBuddies - Future Ideas & Evolution

**Last Updated:** 2026-01-21

This document tracks potential features, improvements, and ideas for future versions of ScreenBuddies. Items here are **not committed** — they represent possibilities to explore when there's user demand or better implementation paths.

---

## How to Use This Document

- **Add ideas** as they come up during development or user feedback
- **Prioritize** based on user demand and implementation feasibility
- **Graduate items** to `recommendations.md` when ready for formal review
- **Remove items** that are no longer relevant

---

## Media Types

### Board Game Support (BGG)

**Status:** Placeholder in codebase, not fully implemented

**Challenge:**
- BoardGameGeek API is unofficial, XML-based, and slow
- No JSON API available
- Rate limiting and reliability concerns
- Requires XML parsing infrastructure

**Potential Solutions:**
1. Wait for official BGG API (if ever released)
2. Build XML adapter with aggressive caching
3. Use community-maintained BGG API wrappers
4. Consider alternative board game databases

**User Demand:** Unknown — monitor feature requests

**References:**
- [BGG XML API Documentation](https://boardgamegeek.com/wiki/page/BGG_XML_API2)
- Deferred from R8 (2026-01-21)

---

### Theater / Live Events Support

**Status:** No implementation, no API identified

**Challenge:**
- No global theater/plays database exists (unlike TMDB for movies)
- Theater listings are regional and fragmented
- Web scraping is fragile and legally questionable
- Data freshness is critical (showtimes change frequently)

**Potential Solutions:**
1. Partner with regional theater APIs (if they exist)
2. Manual entry by Maestro (no search, just text input)
3. Integration with ticketing platforms (Ticketmaster, etc.)
4. Focus on specific regions first

**User Demand:** Unknown — monitor feature requests

**References:**
- Deferred from R8 (2026-01-21)

---

## Offline Support

### Read-Only Offline Mode

**Status:** ✅ **ACCEPTED** (R10 - implemented in Phase 5/6)

> This feature has been moved from future ideas to the implementation plan.
> See `docs/specifications/04-frontend-architecture.md` for full details.

**Summary:**
- Hive for local caching
- Cache elections list, details, candidates, results
- Offline banner UI when disconnected
- Voting/creation disabled offline

---

## Authentication Enhancements

### Shorter Refresh Token Lifetime

**Status:** Deferred (R11 - 2026-01-21)

> Keeping 7-day refresh token for now. Can be shortened in the future if needed.

**Concept:**
- Reduce to 24-48 hours with sliding window
- Active users stay logged in (each refresh extends window)
- Inactive users or stolen tokens expire quickly

**Benefits:**
- Smaller attack window for compromised tokens
- Better security posture

**Trade-offs:**
- Users inactive for 48h need to re-login

**References:**
- Deferred from R11 (2026-01-21)

---

## Data Validation

### JSON Schema Validation for Votes

**Status:** Not implemented

**Concept:**
- Validate `Voter.votes` JSON structure in model mutator
- Ensure keys follow `{smaller}_{larger}` format
- Validate winner IDs are valid candidates
- Catch data corruption early

**Benefits:**
- Data integrity guaranteed
- Bugs caught at write time
- Clearer error messages

**References:**
- See R12 in `recommendations.md`

---

## Social Features

### Friend System Enhancements

**Ideas:**
- Friend suggestions based on shared elections
- Friend activity feed
- "Invite friends" from contacts
- Friend groups for quick election creation

---

### Election Templates

**Ideas:**
- Save election settings as templates
- Share templates with friends
- Pre-built templates ("Movie Night", "Game Night", etc.)

---

## Analytics & Insights

### Personal Voting History

**Ideas:**
- "Your taste profile" based on voting patterns
- Recommendations based on past votes
- "You and [friend] agree X% of the time"
- Genre preferences visualization

---

### Election Statistics

**Ideas:**
- Most controversial candidates (close votes)
- Voting participation rates
- Time-to-decision metrics
- "Kingmaker" identification (voters who decided close races)

---

## Platform Expansion

### Desktop Apps

**Status:** Not planned

**Concept:**
- Native macOS/Windows apps via Flutter
- Better keyboard navigation
- System tray notifications

**Trade-offs:**
- Additional platforms to maintain
- Limited user demand expected

---

### Smart TV Apps

**Status:** Not planned

**Concept:**
- Display results on TV during movie night
- Simple remote-friendly interface
- "What did we decide?" quick view

---

## Integration Ideas

### Calendar Integration

**Ideas:**
- Add election date to calendar
- Reminder notifications
- "Movie night in 2 hours — have you voted?"

---

### Streaming Service Integration

**Ideas:**
- Show which streaming services have the winner
- Filter candidates by available services
- Deep links to streaming apps

---

## Notes

- Items in this document are **not prioritized** or **committed**
- Move items to `recommendations.md` for formal review process
- Delete items that are no longer relevant
- Add user feedback and feature requests here for tracking
