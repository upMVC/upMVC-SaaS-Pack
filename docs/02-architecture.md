# 02 — Architecture

## Pack layers (tenancy + Harvest)

The pack is consumed by a host upMVC app. Inside the pack, think in three layers:

### 0. Harvest libraries (`BitsHost\UpmvcSaas\*`)
Shared ops kernel under `src/Saas/`: `MailGateway`, `SecretBox`, `Notify*`, `AuditLog`, `Totp`, `Ops\*`, `I18n\Catalog`. Not domain-specific. Apps register notify types and may thin-extend these classes. Details: [09 — Harvest ops](09-harvest-ops.md).

### 1. API layer (`Api/*` modules)
Stateless, JWT-authenticated, JSON responses. Tenancy APIs (Auth, Plans, Tenants, Admin) plus thin Harvest APIs (`PlatformOps`, `I18n`). Domain business logic belongs in the **host app**, not this pack.

### 2. Web shell layer (session-based)
PHP-rendered HTML shells for pages that need to exist as web pages (`/platform-admin`, `/app/{slug}/admin`). These shells do minimal work — session guard, render the HTML page — and the page itself calls the API via JavaScript.

---

## Request flow

```
HTTP Request
    ↓
public/index.php
    ↓
Start.php → bootstrap (config, error handler, session)
    ↓
Router → middleware pipeline
    ├── CorsMiddleware       (all API routes)
    ├── JwtAuthMiddleware    (routes marked ['jwt'])
    ├── TenantMiddleware     (routes marked ['tenant'])
    └── PlanGateMiddleware   (routes marked ['feature:x'])
    ↓
Controller → Model → JSON response
```

---

## Module structure

```
src/Modules/
├── Api/Modules/
│   ├── Auth/           POST /api/auth/login|refresh|logout
│   ├── Plans/          GET  /api/plans, /api/plans/{id}
│   ├── Tenants/        POST /api/tenants/register
│   │                   GET|PATCH /api/tenants/{id}
│   ├── Admin/          GET  /api/admin/tenants|dashboard|metrics
│   │                   PATCH /api/admin/tenants/{id}/status|plan
│   │                   POST /api/admin/impersonate
│   │                   PUT  /api/admin/plans/{id}
│   ├── PlatformOps/    Harvest — settings, Attention, SMTP, MFA helpers
│   └── I18n/           Harvest — catalog + overlays
├── Auth/               /auth — session login/logout (web)
├── PlatformAdmin/      /platform-admin — web shell
├── TenantApp/          /app/{slug}/admin — tenant web shell
├── TenantShop/         /app/{slug} — public tenant page
├── Home/               / — landing page
└── Mail/               thin shell → BitsHost\UpmvcSaas\Mail\MailGateway

src/Saas/               Harvest libraries (Mail, Notify, Audit, Security, Ops, I18n, Http, Middleware)
database/migrations/    001_saas_layer.sql + 002_saas_ops_layer.sql
```

---

## Middleware pipeline

Route middleware is declared in the Routes file per route:

```php
$router->addRoute('/api/auth/login', Controller::class, 'login', ['cors']);
$router->addParamRoute('/api/tenants/{id:int}', Controller::class, 'show', ['cors', 'jwt']);
$router->addRoute('/app/{slug}/admin', Controller::class, 'admin', ['cors', 'tenant']);
$router->addRoute('/api/feature', Controller::class, 'action', ['cors', 'jwt', 'feature:efactura']);
```

| Key | Middleware | What it does |
|-----|-----------|-------------|
| `cors` | CorsMiddleware | Sets CORS headers, handles preflight |
| `jwt` | JwtAuthMiddleware | Validates Bearer token, populates `$GLOBALS['current_user']` |
| `tenant` | TenantMiddleware | Resolves tenant from slug/subdomain, populates `$GLOBALS['current_tenant']` |
| `feature:x` | PlanGateMiddleware | Checks tenant has feature `x` in their plan |

---

## Database schema

```
users           id, tenant_id, username, password, name, email, role, state
tenants         id, slug, name, plan_id, status, features (JSON), deleted_at
plans           id, name, price, features (JSON), limits (JSON)
refresh_tokens  id, user_id, token_hash, expires_at, revoked_at
```

Roles: `platform_admin` | `tenant_owner` | `tenant_user`

Tenant status: `trial` | `active` | `suspended`

---

## Dual authentication

| System | Used for | How |
|--------|---------|-----|
| JWT | All `Api/*` routes | `Authorization: Bearer <token>` header |
| Session | Web shells (`/auth`, `/platform-admin`) | `$_SESSION['logged']`, `$_SESSION['role']` |

Both use the same `users` table and bcrypt passwords. A user can authenticate via either system independently.
