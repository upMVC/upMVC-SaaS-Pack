# 02 — Architecture

## The two layers

upMVC-SaaS has two distinct layers that coexist:

### 1. API layer (`Api/*` modules)
Stateless, JWT-authenticated, JSON responses. This is where all business logic lives. Consumed by frontends, mobile apps, or other services.

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
├── Api/
│   ├── Auth/           POST /api/auth/login|refresh|logout
│   ├── Plans/          GET  /api/plans, /api/plans/{id}
│   ├── Tenants/        POST /api/tenants/register
│   │                   GET|PATCH /api/tenants/{id}
│   └── Admin/          GET  /api/admin/tenants|dashboard|metrics
│                       PATCH /api/admin/tenants/{id}/status|plan
│                       POST /api/admin/impersonate
│                       PUT  /api/admin/plans/{id}
├── Auth/               /auth — session login/logout (web)
├── PlatformAdmin/      /platform-admin — web shell
├── TenantApp/          /app/{slug}/admin — tenant web shell
├── TenantShop/         /app/{slug} — public tenant page
├── Home/               / — landing page
└── Mail/               email service (not a route module)
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
