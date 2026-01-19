# ScreenBuddies - Data Model (MCD)

## Entity Relationship Diagram

```
┌─────────────────┐       ┌─────────────────┐
│      USER       │       │   MEDIA_TYPE    │
├─────────────────┤       ├─────────────────┤
│ id (PK)         │       │ id (PK)         │
│ email           │       │ code            │
│ password_hash   │       │ label_en        │
│ display_name    │       │ label_fr        │
│ avatar_url      │       │ api_source      │
│ email_verified  │       │ is_active       │
│ is_admin        │       │ created_at      │
│ is_banned       │       │ updated_at      │
│ locale          │       └────────┬────────┘
│ notif_email     │                │
│ notif_push      │                │
│ created_at      │                │
│ updated_at      │                │
└────────┬────────┘                │
         │                         │
         │ 1                       │ 1
         │                         │
         ▼ N                       ▼ N
┌─────────────────┐       ┌─────────────────┐
│   FRIENDSHIP    │       │    ELECTION     │
├─────────────────┤       ├─────────────────┤
│ id (PK)         │       │ id (PK)         │
│ requester_id(FK)│       │ uuid            │
│ addressee_id(FK)│       │ title           │
│ status          │       │ description     │
│ created_at      │       │ media_type_id   │◄──┘
│ updated_at      │       │ maestro_id (FK) │◄── USER
└─────────────────┘       │ winner_count (K)│
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
              ┌────────────────────┼────────────────────┐
              │                    │                    │
              ▼ N                  ▼ N                  ▼ N
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│    CANDIDATE    │    │     VOTER       │    │   INVITATION    │
├─────────────────┤    ├─────────────────┤    ├─────────────────┤
│ id (PK)         │    │ id (PK)         │    │ id (PK)         │
│ election_id(FK) │    │ election_id(FK) │    │ election_id(FK) │
│ external_id     │    │ user_id (FK)    │    │ email           │
│ title           │    │ joined_at       │    │ token           │
│ poster_url      │    │ completed       │    │ sent_at         │
│ year            │    │ created_at      │    │ accepted_at     │
│ metadata (JSON) │    │ updated_at      │    │ created_at      │
│ suggested_by(FK)│    └────────┬────────┘    └─────────────────┘
│ is_approved     │             │
│ created_at      │             │
│ updated_at      │             │
└────────┬────────┘             │
         │                      │
         │                      │
         └──────────┬───────────┘
                    │
                    ▼ N
          ┌─────────────────┐
          │      DUEL       │
          ├─────────────────┤
          │ id (PK)         │
          │ election_id(FK) │
          │ voter_id (FK)   │
          │ candidate_a(FK) │
          │ candidate_b(FK) │
          │ winner_id (FK)  │
          │ voted_at        │
          │ created_at      │
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
| label_en | VARCHAR(100) | NOT NULL | English display name |
| label_fr | VARCHAR(100) | NOT NULL | French display name |
| api_source | VARCHAR(50) | NOT NULL | tmdb, rawg, bgg, custom |
| is_active | BOOLEAN | DEFAULT TRUE | Available for elections |
| created_at | TIMESTAMP | NOT NULL | Creation date |
| updated_at | TIMESTAMP | NOT NULL | Last update |

---

### ELECTION

A voting session created by a Maestro.

| Field | Type | Constraints | Description |
|-------|------|-------------|-------------|
| id | BIGINT | PK, AUTO | Primary key |
| uuid | UUID | UNIQUE, NOT NULL | Public identifier for magic links |
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

A user participating in an election.

| Field | Type | Constraints | Description |
|-------|------|-------------|-------------|
| id | BIGINT | PK, AUTO | Primary key |
| election_id | BIGINT | FK → ELECTION | Election |
| user_id | BIGINT | FK → USER | Participating user |
| joined_at | TIMESTAMP | NOT NULL | When user joined |
| completed | BOOLEAN | DEFAULT FALSE | Finished all available duels |
| created_at | TIMESTAMP | NOT NULL | Creation date |
| updated_at | TIMESTAMP | NOT NULL | Last update |

**Unique constraint:** (election_id, user_id)

---

### INVITATION

Email invitation to join an election.

| Field | Type | Constraints | Description |
|-------|------|-------------|-------------|
| id | BIGINT | PK, AUTO | Primary key |
| election_id | BIGINT | FK → ELECTION | Target election |
| email | VARCHAR(255) | NOT NULL | Invitee email |
| token | VARCHAR(64) | UNIQUE, NOT NULL | Magic link token |
| sent_at | TIMESTAMP | NOT NULL | Email sent date |
| accepted_at | TIMESTAMP | NULL | When invitation was used |
| created_at | TIMESTAMP | NOT NULL | Creation date |

**Unique constraint:** (election_id, email)

---

### DUEL

A pairwise comparison vote by a voter.

| Field | Type | Constraints | Description |
|-------|------|-------------|-------------|
| id | BIGINT | PK, AUTO | Primary key |
| election_id | BIGINT | FK → ELECTION | Election |
| voter_id | BIGINT | FK → VOTER | Voter who made choice |
| candidate_a_id | BIGINT | FK → CANDIDATE | First option |
| candidate_b_id | BIGINT | FK → CANDIDATE | Second option |
| winner_id | BIGINT | FK → CANDIDATE | Chosen candidate |
| voted_at | TIMESTAMP | NOT NULL | When vote was cast |
| created_at | TIMESTAMP | NOT NULL | Creation date |

**Constraints:**
- winner_id IN (candidate_a_id, candidate_b_id)
- candidate_a_id < candidate_b_id (normalize order)
- Unique: (voter_id, candidate_a_id, candidate_b_id)

---

## Relationships Summary

| Relationship | Type | Description |
|--------------|------|-------------|
| USER → ELECTION | 1:N | User creates many elections (as Maestro) |
| USER → FRIENDSHIP | N:N | Users can be friends |
| USER → VOTER | 1:N | User participates in many elections |
| ELECTION → CANDIDATE | 1:N | Election has 2-30 candidates |
| ELECTION → VOTER | 1:N | Election has many voters |
| ELECTION → INVITATION | 1:N | Election has many invitations |
| VOTER → DUEL | 1:N | Voter makes many duel choices |
| CANDIDATE → DUEL | 1:N | Candidate appears in many duels |
| MEDIA_TYPE → ELECTION | 1:N | Media type used by many elections |

---

## Indexes

```sql
-- Performance indexes
CREATE INDEX idx_election_status ON election(status);
CREATE INDEX idx_election_deadline ON election(deadline);
CREATE INDEX idx_election_maestro ON election(maestro_id);
CREATE INDEX idx_voter_election ON voter(election_id);
CREATE INDEX idx_voter_user ON voter(user_id);
CREATE INDEX idx_duel_voter ON duel(voter_id);
CREATE INDEX idx_duel_election ON duel(election_id);
CREATE INDEX idx_candidate_election ON candidate(election_id);
CREATE INDEX idx_friendship_users ON friendship(requester_id, addressee_id);
CREATE INDEX idx_invitation_token ON invitation(token);
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
