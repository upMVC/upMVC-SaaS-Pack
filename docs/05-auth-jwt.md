# 05 — Auth & JWT

## Overview

Authentication is stateless JWT — no sessions for API consumers. The session system exists separately for PHP-rendered web pages only.

---

## Endpoints

| Method | Endpoint | Auth required |
|--------|---------|--------------|
| POST | `/api/auth/login` | No |
| POST | `/api/auth/refresh` | No |
| POST | `/api/auth/logout` | JWT Bearer |

---

## Login

```http
POST /api/auth/login
Content-Type: application/json

{"username": "john.owner", "password": "Test1234!"}
```

Response:
```json
{
  "success": true,
  "data": {
    "access_token": "eyJ...",
    "refresh_token": "a3f9...",
    "token_type": "Bearer",
    "expires_in": 3600,
    "user": {
      "id": 5,
      "username": "john.owner",
      "name": "John Owner",
      "tenant_id": 2,
      "role": "tenant_owner"
    }
  }
}
```

---

## Using the access token

Include in every authenticated request:

```http
Authorization: Bearer eyJ...
```

The `JwtAuthMiddleware` validates the token and populates `$GLOBALS['current_user']` with the decoded payload. Pack API controllers access it via `$this->user` from `SaasApiController`.

---

## Refresh token rotation

Access tokens expire (default: 1 hour). Use the refresh token to get a new pair without re-entering credentials.

```http
POST /api/auth/refresh
Content-Type: application/json

{"refresh_token": "a3f9..."}
```

**Token theft detection:** If a refresh token that has already been used (revoked) is presented again, the system immediately revokes ALL tokens for that user and returns 401. This signals a possible token theft.

Refresh tokens are stored as SHA-256 hashes — the raw token is never persisted.

---

## Logout

```http
POST /api/auth/logout
Authorization: Bearer eyJ...
Content-Type: application/json

{"refresh_token": "a3f9..."}
```

Omit `refresh_token` from the body to revoke all sessions for the user (logout everywhere).

---

## JWT configuration (.env)

```env
JWT_SECRET=your-strong-random-secret
JWT_ACCESS_TTL=3600        # access token lifetime in seconds (default: 1h)
JWT_REFRESH_TTL=2592000    # refresh token lifetime in seconds (default: 30d)
```

---

## Token payload

```json
{
  "sub": 5,
  "username": "john.owner",
  "tenant_id": 2,
  "role": "tenant_owner",
  "iat": 1700000000,
  "exp": 1700003600
}
```

---

## Roles

| Role | Access |
|------|--------|
| `platform_admin` | Full access to `Api/Admin` endpoints. No `tenant_id`. |
| `tenant_owner` | Full access within their tenant. |
| `tenant_user` | Limited access within their tenant (your rules). |
