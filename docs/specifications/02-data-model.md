# ScreenBuddies - Data Model (MCD)

## Entity Relationship Diagram

```
┌─────────────────┐       ┌─────────────────┐
│      USER       │       │   MEDIA_TYPE    │
├─────────────────┤       ├─────────────────┤
│ id (PK)         │       │ id (PK)         │
│ email           │       │ code            │
│ password_hash   │       │ label           │
│ display_name    │       │ api_source(ENUM)│
│ avatar_url      │       │ is_active       │
│ email_verified  │       │ created_at      │
│ is_admin        │       │ updated_at      │
│ is_banned       │       └────────┬────────┘
│ created_at      │                │
│ updated_at      │                │
└────────┬────────┘                │
         │                         │
         ▼ 1                       │
┌─────────────────┐                │
│ USER_PREFERENCE │                │
├─────────────────┤                │
│ id (PK)         │                │
│ user_id (FK)    │                │
│ locale          │                │
│ notif_email     │                │
│ notif_push      │                │
│ created_at      │                │
│ updated_at      │                │
└─────────────────┘                │
         │                         │
         │ 1                       │ 1
         │                         │
         ▼ N                       ▼ N
┌─────────────────┐       ┌─────────────────┐
│   FRIENDSHIP    │       │    ELECTION     │
├─────────────────┤       ├─────────────────┤
│ id (PK)         │       │ id (PK)         │
│ requester_id(FK)│       │ uuid            │
│ addressee_id(FK)│       │ invite_token    │
│ status          │       │ title           │
│ created_at      │       │ description     │
│ updated_at      │       │ media_type_id   │◄──┘
└─────────────────┘       │ maestro_id (FK) │◄── USER
                          │ winner_count (K)│
                          │ election_date   │
                          │ deadline        │
                          │ campaign_end    │
                          │ allow_suggest   │
                          │ auto_approve    │
                          │ status          │
                          │ created_at      │
                          │ updated_at      │
                          └────────┬────────┘
                                   │
              ┌────────────────────┴────────────────────┐
              │                                         │
              ▼ N                                       ▼ N
┌─────────────────┐                         ┌─────────────────┐
│    CANDIDATE    │                         │     VOTER       │
├─────────────────┤                         ├─────────────────┤
│ id (PK)         │                         │ id (PK)         │
│ election_id(FK) │                         │ election_id(FK) │
│ external_id     │                         │ user_id (FK)    │
│ title           │                         │ votes (JSON)    │
│ poster_url      │                         │ duel_count      │
│ year            │                         │ joined_at       │
│ metadata (JSON) │                         │ completed       │
│ suggested_by(FK)│                         │ created_at      │
│ is_approved     │                         │ updated_at      │
│ created_at      │                         └─────────────────┘
│ updated_at      │
└─────────────────┘
```

---

## Entities Detail

### USER

Registered application user.

| Field | Type | Constraints | Description |
|-------|------|-------------|-------------|
| id | BIGINT | PK, AUTO | Primary key |
| email | VARCHAR(255) | UNIQUE, NOT NULL | Login email |
| password_hash | VARCHAR(255) | NOT NULL | Bcrypt hash |
| display_name | VARCHAR(100) | NULL | Public name |
| avatar_url | VARCHAR(500) | NULL | Profile picture URL |
| email_verified_at | TIMESTAMP | NULL | Email validation timestamp |
| is_admin | BOOLEAN | DEFAULT FALSE | Admin flag |
| is_banned | BOOLEAN | DEFAULT FALSE | Ban status |
| created_at | TIMESTAMP | NOT NULL | Creation date |
| updated_at | TIMESTAMP | NOT NULL | Last update |

---

### USER_PREFERENCE

User preferences and notification settings.

| Field | Type | Constraints | Description |
|-------|------|-------------|-------------|
| id | BIGINT | PK, AUTO | Primary key |
| user_id | BIGINT | FK → USER, UNIQUE | Owner user |
| locale | VARCHAR(5) | DEFAULT 'en' | Preferred language |
| notif_email | BOOLEAN | DEFAULT TRUE | Email notifications |
| notif_push | BOOLEAN | DEFAULT TRUE | Push notifications |
| created_at | TIMESTAMP | NOT NULL | Creation date |
| updated_at | TIMESTAMP | NOT NULL | Last update |

---

### FRIENDSHIP

Bidirectional friendship between users. Both must accept.

| Field | Type | Constraints | Description |
|-------|------|-------------|-------------|
| id | BIGINT | PK, AUTO | Primary key |
| requester_id | BIGINT | FK → USER | User who sent request |
| addressee_id | BIGINT | FK → USER | User who received request |
| status | ENUM | NOT NULL | pending, accepted, declined |
| created_at | TIMESTAMP | NOT NULL | Request date |
| updated_at | TIMESTAMP | NOT NULL | Status change date |

**Unique constraint:** (requester_id, addressee_id)

---

### MEDIA_TYPE

Types of content that can be voted on.

| Field | Type | Constraints | Description |
|-------|------|-------------|-------------|
| id | BIGINT | PK, AUTO | Primary key |
| code | VARCHAR(50) | UNIQUE, NOT NULL | movie, tvshow, videogame, boardgame, theater |
| label | VARCHAR(100) | NOT NULL | Display name (i18n key) |
| api_source | ENUM | NOT NULL | tmdb, rawg, bgg, custom |
| is_active | BOOLEAN | DEFAULT TRUE | Available for elections |
| created_at | TIMESTAMP | NOT NULL | Creation date |
| updated_at | TIMESTAMP | NOT NULL | Last update |

---

### ELECTION

A voting session created by a Maestro.

| Field | Type | Constraints | Description |
|-------|------|-------------|-------------|
| id | BIGINT | PK, AUTO | Primary key |
| uuid | UUID | UNIQUE, NOT NULL | Public identifier |
| invite_token | VARCHAR(64) | UNIQUE, NOT NULL | Shareable join code |
| title | VARCHAR(255) | NOT NULL | Election name |
| description | TEXT | NULL | Optional description |
| media_type_id | BIGINT | FK → MEDIA_TYPE | Type of content |
| maestro_id | BIGINT | FK → USER | Creator |
| winner_count | TINYINT | NOT NULL, 1-5 | K (number of winners) |
| election_date | DATETIME | NOT NULL | Event date |
| deadline | DATETIME | NOT NULL | Voting deadline |
| campaign_end | DATETIME | NULL | Suggestion phase end (if enabled) |
| allow_suggestions | BOOLEAN | DEFAULT FALSE | Voters can suggest |
| auto_approve | BOOLEAN | DEFAULT FALSE | Auto-approve suggestions |
| status | ENUM | NOT NULL | draft, campaign, voting, ended, archived |
| created_at | TIMESTAMP | NOT NULL | Creation date |
| updated_at | TIMESTAMP | NOT NULL | Last update |

**Validation rules:**
- campaign_end < deadline < election_date
- winner_count < candidate count

---

### CANDIDATE

An item in an election (movie, game, etc.).

| Field | Type | Constraints | Description |
|-------|------|-------------|-------------|
| id | BIGINT | PK, AUTO | Primary key |
| election_id | BIGINT | FK → ELECTION | Parent election |
| external_id | VARCHAR(100) | NOT NULL | ID from external API |
| title | VARCHAR(255) | NOT NULL | Display title |
| poster_url | VARCHAR(500) | NULL | Image URL |
| year | SMALLINT | NULL | Release year |
| metadata | JSON | NULL | Additional data (genre, rating, etc.) |
| suggested_by | BIGINT | FK → USER, NULL | Voter who suggested (if suggestion) |
| is_approved | BOOLEAN | DEFAULT TRUE | Approved by Maestro |
| created_at | TIMESTAMP | NOT NULL | Added date |
| updated_at | TIMESTAMP | NOT NULL | Last update |

**Unique constraint:** (election_id, external_id)

---

### VOTER

A user participating in an election. Stores all duel votes as compact JSON.

| Field | Type | Constraints | Description |
|-------|------|-------------|-------------|
| id | BIGINT | PK, AUTO | Primary key |
| election_id | BIGINT | FK → ELECTION | Election |
| user_id | BIGINT | FK → USER | Participating user |
| votes | JSON | DEFAULT '{}' | Compact duel results (see below) |
| duel_count | SMALLINT | DEFAULT 0 | Number of duels completed |
| joined_at | TIMESTAMP | NOT NULL | When user joined |
| completed | BOOLEAN | DEFAULT FALSE | Finished all available duels |
| created_at | TIMESTAMP | NOT NULL | Creation date |
| updated_at | TIMESTAMP | NOT NULL | Last update |

**Unique constraint:** (election_id, user_id)

**Votes JSON Structure:**

Stores duel results as key-value pairs where key is `{smaller_id}_{larger_id}` and value is the winner's candidate ID or `null` for skipped duels.

```json
{
  "1_2": 1,      // Candidate 1 vs 2 → winner is 1
  "1_3": 3,      // Candidate 1 vs 3 → winner is 3
  "2_3": null,   // Candidate 2 vs 3 → skipped (user didn't know either)
  "3_4": 4,      // Candidate 3 vs 4 → winner is 4
  ...
}
```

**Key format rules:**
- Always use `{smaller_id}_{larger_id}` to normalize pair order
- Value is winner's candidate ID, or `null` if skipped
- Missing key = duel not yet presented
- Skipped duels (`null`) are ignored in ranking calculations

**Scale benefit:** Instead of 435 rows per voter (for 30 candidates), stores 1 row with ~4KB JSON.

**Aggregation for Condorcet:**
```sql
-- Extract pairwise stats across all voters in an election
SELECT
  voter.votes->>'$.1_2' as winner_1_2
FROM voters
WHERE election_id = ?
```

---

## Relationships Summary

| Relationship | Type | Description |
|--------------|------|-------------|
| USER → USER_PREFERENCE | 1:1 | User has one preference record |
| USER → ELECTION | 1:N | User creates many elections (as Maestro) |
| USER → FRIENDSHIP | N:N | Users can be friends |
| USER → VOTER | 1:N | User participates in many elections |
| ELECTION → CANDIDATE | 1:N | Election has 2-30 candidates |
| ELECTION → VOTER | 1:N | Election has many voters (duels stored in votes JSON) |
| MEDIA_TYPE → ELECTION | 1:N | Media type used by many elections |

---

## Indexes

```sql
-- Performance indexes
CREATE INDEX idx_election_status ON election(status);
CREATE INDEX idx_election_deadline ON election(deadline);
CREATE INDEX idx_election_maestro ON election(maestro_id);
CREATE UNIQUE INDEX idx_election_invite_token ON election(invite_token);
CREATE INDEX idx_voter_election ON voter(election_id);
CREATE INDEX idx_voter_user ON voter(user_id);
CREATE INDEX idx_candidate_election ON candidate(election_id);
CREATE INDEX idx_friendship_users ON friendship(requester_id, addressee_id);
CREATE UNIQUE INDEX idx_user_preference_user ON user_preference(user_id);
```

---

## Status Enums

### Election Status

| Status | Description |
|--------|-------------|
| draft | Being created (not used for now, elections are created complete) |
| campaign | Suggestion phase active |
| voting | Voting in progress |
| ended | Voting finished, results visible |
| archived | 24h after election date |

### Friendship Status

| Status | Description |
|--------|-------------|
| pending | Awaiting acceptance |
| accepted | Both users are friends |
| declined | Request was rejected |

### API Source (Media Type)

| Value | Description |
|-------|-------------|
| tmdb | The Movie Database (movies, TV shows) |
| rawg | RAWG API (video games) |
| bgg | BoardGameGeek (board games) |
| custom | Manual/custom entries |
