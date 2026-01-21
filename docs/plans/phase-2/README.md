# Phase 2: Authentication & User Management - Sub-Plans

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement these plans task-by-task.

**Goal:** Implement complete authentication system with Sanctum, user profile, and friendships.

**Estimated Tasks:** 9 sub-phases, ~60 granular tasks total

---

## Sub-Phase Overview

| Sub-Phase | Description | Key Deliverables |
|-----------|-------------|------------------|
| [2.1](./2.1-api-error-handling.md) | API Error Handling | ApiException, validation errors |
| [2.2](./2.2-registration.md) | User Registration | POST /auth/register |
| [2.3](./2.3-login.md) | User Login | POST /auth/login |
| [2.4](./2.4-token-management.md) | Token Management | POST /auth/refresh, /auth/logout |
| [2.5](./2.5-user-profile.md) | User Profile | GET/PUT /me |
| [2.6](./2.6-password-management.md) | Password Change | PUT /me/password |
| [2.7](./2.7-friendships.md) | Friendships | CRUD /friends |
| [2.8](./2.8-email-verification.md) | Email Verification | Verification emails |
| [2.9](./2.9-finalization.md) | Finalization | Full test suite |

---

## Execution Order

Execute sub-phases in order (2.1 → 2.2 → ... → 2.9). Each sub-phase depends on the previous one being complete.

---

## Prerequisites Before Starting

- [ ] Phase 1 complete
- [ ] All models created
- [ ] Sanctum configured
- [ ] User factory created

---

## API Endpoints Created

After Phase 2 completion:

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | /api/v1/auth/register | No | Register new user |
| POST | /api/v1/auth/login | No | Login user |
| POST | /api/v1/auth/refresh | No | Refresh tokens |
| POST | /api/v1/auth/logout | Yes | Logout user |
| GET | /api/v1/auth/verify-email/{id}/{hash} | No | Verify email |
| POST | /api/v1/auth/resend-verification | Yes | Resend verification |
| GET | /api/v1/me | Yes | Get profile |
| PUT | /api/v1/me | Yes | Update profile |
| PUT | /api/v1/me/password | Yes | Change password |
| GET | /api/v1/friends | Yes+V | List friends |
| GET | /api/v1/friends/requests | Yes+V | List requests |
| POST | /api/v1/friends | Yes+V | Send request |
| PUT | /api/v1/friends/{id}/accept | Yes+V | Accept request |
| PUT | /api/v1/friends/{id}/decline | Yes+V | Decline request |
| DELETE | /api/v1/friends/{id} | Yes+V | Remove friend |

*Yes+V = Requires auth + email verified*

---

## Error Response Format

All errors follow this format:
```json
{
  "error": {
    "code": "ERROR_CODE",
    "message": "Human readable message",
    "details": {...}  // Optional
  }
}
```

---

## Phase 2 Completion Criteria

- [ ] API exception handling configured
- [ ] Register/Login/Logout working
- [ ] Token refresh working
- [ ] User profile CRUD
- [ ] Password change
- [ ] Friendship system complete
- [ ] Email verification working
- [ ] EnsureEmailVerified middleware
- [ ] All feature tests passing
