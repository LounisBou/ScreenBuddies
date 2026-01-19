# ScreenBuddies - API Endpoints

## Overview

RESTful JSON API. All endpoints prefixed with `/api/v1`.

---

## Common Headers

### Request Headers

| Header | Required | Description |
|--------|----------|-------------|
| Accept | Yes | `application/json` |
| Content-Type | Yes (POST/PUT) | `application/json` |
| Authorization | Conditional | `Bearer {access_token}` |
| Accept-Language | No | `en` or `fr` (default: `en`) |

### Response Format

**Success:**
```json
{
  "data": { ... }
}
```

**Success with pagination:**
```json
{
  "data": [ ... ],
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 20,
    "total": 97
  }
}
```

**Error:**
```json
{
  "error": {
    "code": "ERROR_CODE",
    "message": "Human readable message",
    "details": { ... }
  }
}
```

---

## Authentication

### POST /auth/register

Create new account.

**Request:**
```json
{
  "email": "user@example.com",
  "password": "securePassword123",
  "password_confirmation": "securePassword123",
  "display_name": "John Doe",
  "locale": "en"
}
```

**Response (201):**
```json
{
  "data": {
    "user": {
      "id": "uuid",
      "email": "user@example.com",
      "display_name": "John Doe",
      "avatar_url": null,
      "email_verified": false,
      "locale": "en",
      "created_at": "2024-01-15T10:30:00Z"
    },
    "tokens": {
      "access_token": "eyJ...",
      "refresh_token": "eyJ...",
      "expires_in": 900
    }
  }
}
```

---

### POST /auth/login

Authenticate user.

**Request:**
```json
{
  "email": "user@example.com",
  "password": "securePassword123"
}
```

**Response (200):**
```json
{
  "data": {
    "user": { ... },
    "tokens": {
      "access_token": "eyJ...",
      "refresh_token": "eyJ...",
      "expires_in": 900
    }
  }
}
```

**Errors:**
- `401 INVALID_CREDENTIALS` - Wrong email/password
- `403 ACCOUNT_BANNED` - User is banned

---

### POST /auth/refresh

Get new access token.

**Request:**
```json
{
  "refresh_token": "eyJ..."
}
```

**Response (200):**
```json
{
  "data": {
    "access_token": "eyJ...",
    "refresh_token": "eyJ...",
    "expires_in": 900
  }
}
```

---

### POST /auth/logout

**Auth:** Required

Invalidate tokens.

**Response (204):** No content

---

### POST /auth/forgot-password

Request password reset email.

**Request:**
```json
{
  "email": "user@example.com"
}
```

**Response (200):**
```json
{
  "data": {
    "message": "If this email exists, a reset link has been sent."
  }
}
```

---

### POST /auth/reset-password

Set new password.

**Request:**
```json
{
  "token": "reset-token-from-email",
  "password": "newSecurePassword123",
  "password_confirmation": "newSecurePassword123"
}
```

**Response (200):**
```json
{
  "data": {
    "message": "Password has been reset successfully."
  }
}
```

---

### POST /auth/verify-email

Verify email address.

**Request:**
```json
{
  "token": "verification-token-from-email"
}
```

**Response (200):**
```json
{
  "data": {
    "message": "Email verified successfully."
  }
}
```

---

### POST /auth/resend-verification

**Auth:** Required

Resend verification email.

**Response (200):**
```json
{
  "data": {
    "message": "Verification email sent."
  }
}
```

---

## User

### GET /me

**Auth:** Required

Get current user profile.

**Response (200):**
```json
{
  "data": {
    "id": "uuid",
    "email": "user@example.com",
    "display_name": "John Doe",
    "avatar_url": "https://...",
    "email_verified": true,
    "locale": "en",
    "notif_email": true,
    "notif_push": true,
    "created_at": "2024-01-15T10:30:00Z"
  }
}
```

---

### PUT /me

**Auth:** Required

Update current user profile.

**Request:**
```json
{
  "display_name": "John Smith",
  "locale": "fr",
  "notif_email": false,
  "notif_push": true
}
```

**Response (200):** Updated user object

---

### PUT /me/email

**Auth:** Required (verified)

Update email address. Requires re-verification.

**Request:**
```json
{
  "email": "newemail@example.com",
  "password": "currentPassword"
}
```

**Response (200):**
```json
{
  "data": {
    "message": "Verification email sent to new address."
  }
}
```

---

### PUT /me/password

**Auth:** Required

Change password.

**Request:**
```json
{
  "current_password": "oldPassword",
  "password": "newPassword123",
  "password_confirmation": "newPassword123"
}
```

**Response (200):**
```json
{
  "data": {
    "message": "Password updated successfully."
  }
}
```

---

### PUT /me/avatar

**Auth:** Required

Upload avatar image. Multipart form data.

**Request:** `multipart/form-data` with `avatar` file field

**Response (200):**
```json
{
  "data": {
    "avatar_url": "https://storage.../avatar.jpg"
  }
}
```

---

### DELETE /me

**Auth:** Required

Delete account permanently.

**Request:**
```json
{
  "password": "currentPassword"
}
```

**Response (204):** No content

---

## Friendships

### GET /friends

**Auth:** Required (verified)

List accepted friendships.

**Response (200):**
```json
{
  "data": [
    {
      "id": "friendship-uuid",
      "friend": {
        "id": "user-uuid",
        "display_name": "Jane Doe",
        "avatar_url": "https://..."
      },
      "created_at": "2024-01-15T10:30:00Z"
    }
  ]
}
```

---

### GET /friends/requests

**Auth:** Required (verified)

List pending friend requests received.

**Response (200):**
```json
{
  "data": [
    {
      "id": "friendship-uuid",
      "requester": {
        "id": "user-uuid",
        "display_name": "Bob Smith",
        "avatar_url": "https://..."
      },
      "created_at": "2024-01-15T10:30:00Z"
    }
  ]
}
```

---

### GET /friends/sent

**Auth:** Required (verified)

List pending friend requests sent.

**Response (200):** Same format as requests

---

### POST /friends

**Auth:** Required (verified)

Send friend request.

**Request:**
```json
{
  "user_id": "target-user-uuid"
}
```

**Response (201):**
```json
{
  "data": {
    "id": "friendship-uuid",
    "status": "pending",
    "addressee": { ... },
    "created_at": "2024-01-15T10:30:00Z"
  }
}
```

---

### PUT /friends/{id}/accept

**Auth:** Required (verified)

Accept friend request.

**Response (200):**
```json
{
  "data": {
    "id": "friendship-uuid",
    "status": "accepted",
    "friend": { ... }
  }
}
```

---

### PUT /friends/{id}/decline

**Auth:** Required (verified)

Decline friend request.

**Response (204):** No content

---

### DELETE /friends/{id}

**Auth:** Required (verified)

Remove friend or cancel request.

**Response (204):** No content

---

## Elections

### GET /elections

**Auth:** Required

List user's elections (as maestro or voter).

**Query params:**
- `status` - Filter: `voting`, `ended`, `archived`
- `role` - Filter: `maestro`, `voter`
- `page` - Page number

**Response (200):**
```json
{
  "data": [
    {
      "id": "election-uuid",
      "uuid": "public-uuid",
      "title": "Movie Night",
      "media_type": {
        "code": "movie",
        "label": "Movie"
      },
      "status": "voting",
      "is_maestro": true,
      "election_date": "2024-02-01T20:00:00Z",
      "deadline": "2024-02-01T18:00:00Z",
      "winner_count": 2,
      "candidate_count": 15,
      "voter_count": 5,
      "my_progress": {
        "completed": 45,
        "total": 105,
        "percentage": 42.8
      },
      "created_at": "2024-01-15T10:30:00Z"
    }
  ],
  "meta": { ... }
}
```

---

### GET /elections/{uuid}

**Auth:** Required (must be maestro or voter)

Get election details.

**Response (200):**
```json
{
  "data": {
    "id": "election-uuid",
    "uuid": "public-uuid",
    "title": "Movie Night",
    "description": "Let's pick some movies!",
    "media_type": {
      "code": "movie",
      "label": "Movie"
    },
    "maestro": {
      "id": "user-uuid",
      "display_name": "John Doe",
      "avatar_url": "https://..."
    },
    "status": "voting",
    "election_date": "2024-02-01T20:00:00Z",
    "deadline": "2024-02-01T18:00:00Z",
    "campaign_end": null,
    "allow_suggestions": false,
    "auto_approve": false,
    "winner_count": 2,
    "is_maestro": true,
    "candidates": [ ... ],
    "voters": [ ... ],
    "results": null,
    "created_at": "2024-01-15T10:30:00Z"
  }
}
```

---

### POST /elections

**Auth:** Required (verified)

Create new election.

**Request:**
```json
{
  "title": "Movie Night",
  "description": "Let's pick some movies!",
  "media_type_code": "movie",
  "winner_count": 2,
  "election_date": "2024-02-01T20:00:00Z",
  "deadline": "2024-02-01T18:00:00Z",
  "campaign_end": null,
  "allow_suggestions": false,
  "auto_approve": false,
  "candidates": [
    {
      "external_id": "tmdb:12345",
      "title": "The Matrix",
      "poster_url": "https://...",
      "year": 1999,
      "metadata": { "genre": "Sci-Fi", "rating": 8.7 }
    },
    { ... }
  ]
}
```

**Validation:**
- 2-30 candidates
- winner_count: 1-5, less than candidate count
- deadline < election_date
- campaign_end < deadline (if set)

**Response (201):** Created election object

---

### PUT /elections/{uuid}/close

**Auth:** Required (maestro only)

Close voting early.

**Response (200):** Updated election with results

---

### DELETE /elections/{uuid}

**Auth:** Required (admin only)

Delete election.

**Response (204):** No content

---

## Candidates

### GET /elections/{uuid}/candidates

**Auth:** Required (voter/maestro)

List election candidates.

**Response (200):**
```json
{
  "data": [
    {
      "id": "candidate-uuid",
      "external_id": "tmdb:12345",
      "title": "The Matrix",
      "poster_url": "https://...",
      "year": 1999,
      "metadata": {
        "genre": "Sci-Fi",
        "rating": 8.7,
        "runtime": 136
      },
      "suggested_by": null,
      "is_approved": true
    }
  ]
}
```

---

### POST /elections/{uuid}/candidates

**Auth:** Required (voter, suggestions enabled, campaign phase)

Suggest a candidate.

**Request:**
```json
{
  "external_id": "tmdb:67890",
  "title": "Inception",
  "poster_url": "https://...",
  "year": 2010,
  "metadata": { ... }
}
```

**Response (201):** Created candidate (is_approved depends on auto_approve)

---

### PUT /elections/{uuid}/candidates/{id}/approve

**Auth:** Required (maestro, manual approval)

Approve suggested candidate.

**Response (200):** Updated candidate

---

### DELETE /elections/{uuid}/candidates/{id}

**Auth:** Required (maestro, suggestion not approved yet)

Reject suggested candidate.

**Response (204):** No content

---

## Voters

### GET /elections/{uuid}/voters

**Auth:** Required (maestro or voter)

List election voters.

**Response (200):**
```json
{
  "data": [
    {
      "id": "voter-uuid",
      "user": {
        "id": "user-uuid",
        "display_name": "Jane Doe",
        "avatar_url": "https://..."
      },
      "progress": {
        "completed": 45,
        "total": 105,
        "percentage": 42.8
      },
      "joined_at": "2024-01-16T14:00:00Z"
    }
  ]
}
```

---

## Invitations

### POST /elections/{uuid}/invitations

**Auth:** Required (maestro)

Invite users by email or friend ID.

**Request:**
```json
{
  "emails": ["friend1@example.com", "friend2@example.com"],
  "friend_ids": ["friend-user-uuid"]
}
```

**Response (201):**
```json
{
  "data": {
    "invited": 3,
    "magic_link": "https://app.screenbuddies.com/join/abc123..."
  }
}
```

---

### GET /elections/join/{token}

Get election info from magic link (no auth required).

**Response (200):**
```json
{
  "data": {
    "election": {
      "uuid": "election-uuid",
      "title": "Movie Night",
      "media_type": { ... },
      "maestro": {
        "display_name": "John Doe"
      },
      "status": "voting",
      "candidate_count": 15,
      "voter_count": 4
    },
    "requires_auth": true
  }
}
```

---

### POST /elections/join/{token}

**Auth:** Required

Join election via magic link.

**Request:**
```json
{
  "add_friend": true
}
```

**Response (200):**
```json
{
  "data": {
    "election": { ... },
    "friendship_created": true
  }
}
```

---

## Duels

### GET /elections/{uuid}/duels/next

**Auth:** Required (voter)

Get next duel for voting.

**Response (200):**
```json
{
  "data": {
    "duel_id": "duel-uuid",
    "candidate_a": {
      "id": "candidate-uuid",
      "title": "The Matrix",
      "poster_url": "https://...",
      "year": 1999,
      "metadata": { ... }
    },
    "candidate_b": {
      "id": "candidate-uuid",
      "title": "Inception",
      "poster_url": "https://...",
      "year": 2010,
      "metadata": { ... }
    },
    "progress": {
      "completed": 45,
      "total": 105,
      "percentage": 42.8
    }
  }
}
```

**Response when complete (200):**
```json
{
  "data": null,
  "meta": {
    "complete": true,
    "message": "You have completed all duels."
  }
}
```

---

### POST /elections/{uuid}/duels/{id}/vote

**Auth:** Required (voter, voting phase)

Cast vote in duel.

**Request:**
```json
{
  "winner_id": "candidate-uuid"
}
```

**Validation:**
- winner_id must be candidate_a or candidate_b
- Duel not already voted
- Election in voting status

**Response (200):**
```json
{
  "data": {
    "voted": true,
    "next_duel": { ... } | null
  }
}
```

---

### GET /elections/{uuid}/duels/history

**Auth:** Required (voter)

Get voter's past duels.

**Response (200):**
```json
{
  "data": [
    {
      "id": "duel-uuid",
      "candidate_a": { ... },
      "candidate_b": { ... },
      "winner_id": "candidate-uuid",
      "voted_at": "2024-01-16T15:30:00Z"
    }
  ]
}
```

---

## Results

### GET /elections/{uuid}/results

**Auth:** Required (voter/maestro, election ended)

Get election results.

**Response (200):**
```json
{
  "data": {
    "winners": [
      {
        "rank": 1,
        "candidate": {
          "id": "candidate-uuid",
          "title": "The Matrix",
          "poster_url": "https://...",
          "year": 1999
        },
        "stats": {
          "wins": 42,
          "losses": 8,
          "win_rate": 84.0
        }
      },
      {
        "rank": 2,
        "candidate": { ... },
        "stats": { ... }
      }
    ],
    "full_ranking": [
      { "rank": 1, "candidate": { ... }, "stats": { ... } },
      { "rank": 2, "candidate": { ... }, "stats": { ... } },
      ...
    ],
    "total_duels": 525,
    "voter_participation": [
      {
        "voter": { "display_name": "John" },
        "duels_completed": 105,
        "percentage": 100
      }
    ]
  }
}
```

---

## Media Search

### GET /media/search

**Auth:** Required

Search external APIs for media.

**Query params:**
- `type` - Required: `movie`, `tvshow`, `videogame`, `boardgame`
- `query` - Required: Search term
- `page` - Optional: Page number (default: 1)

**Response (200):**
```json
{
  "data": [
    {
      "external_id": "tmdb:12345",
      "title": "The Matrix",
      "poster_url": "https://image.tmdb.org/...",
      "year": 1999,
      "metadata": {
        "genre": "Science Fiction, Action",
        "rating": 8.7,
        "overview": "A computer hacker learns..."
      }
    }
  ],
  "meta": {
    "current_page": 1,
    "total_pages": 5,
    "total_results": 97
  }
}
```

---

### GET /media/{type}/{external_id}

**Auth:** Required

Get detailed media info.

**Response (200):**
```json
{
  "data": {
    "external_id": "tmdb:12345",
    "title": "The Matrix",
    "poster_url": "https://...",
    "backdrop_url": "https://...",
    "year": 1999,
    "metadata": {
      "genre": "Science Fiction, Action",
      "rating": 8.7,
      "runtime": 136,
      "overview": "A computer hacker learns...",
      "director": "Lana Wachowski, Lilly Wachowski",
      "cast": ["Keanu Reeves", "Laurence Fishburne"]
    }
  }
}
```

---

## Media Types

### GET /media-types

**Auth:** Required

List available media types.

**Response (200):**
```json
{
  "data": [
    {
      "code": "movie",
      "label": "Movie"
    },
    {
      "code": "tvshow",
      "label": "TV Show"
    },
    {
      "code": "videogame",
      "label": "Video Game"
    },
    {
      "code": "boardgame",
      "label": "Board Game"
    },
    {
      "code": "theater",
      "label": "Theater"
    }
  ]
}
```

---

## Admin Endpoints

### GET /admin/users

**Auth:** Required (admin)

List all users.

**Query params:**
- `search` - Search by email or display_name
- `status` - Filter: `active`, `banned`, `unverified`
- `page`

**Response (200):** Paginated user list

---

### PUT /admin/users/{id}/ban

**Auth:** Required (admin)

Ban user.

**Response (200):** Updated user

---

### PUT /admin/users/{id}/unban

**Auth:** Required (admin)

Unban user.

**Response (200):** Updated user

---

### DELETE /admin/users/{id}

**Auth:** Required (admin)

Delete user account.

**Response (204):** No content

---

### GET /admin/elections

**Auth:** Required (admin)

List all elections.

**Query params:**
- `search` - Search by title
- `status` - Filter by status
- `page`

**Response (200):** Paginated election list

---

### DELETE /admin/elections/{uuid}

**Auth:** Required (admin)

Delete election.

**Response (204):** No content

---

## Push Token Registration

### POST /devices

**Auth:** Required

Register device for push notifications.

**Request:**
```json
{
  "token": "fcm-device-token",
  "platform": "android"
}
```

**Response (201):**
```json
{
  "data": {
    "registered": true
  }
}
```

---

### DELETE /devices/{token}

**Auth:** Required

Unregister device.

**Response (204):** No content
