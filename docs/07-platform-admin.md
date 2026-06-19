# 07 — Platform Admin

## What it is

The platform admin is for the SaaS operator — you — to manage all tenants, plans, and the platform itself. It has two parts:

1. **`Api/Admin`** — the actual API, JWT-protected, `platform_admin` role required
2. **`PlatformAdmin`** — a thin PHP web shell that renders a page; the page calls the API via JavaScript

---

## Api/Admin endpoints

All require `Authorization: Bearer <platform_admin_token>`.

| Method | Endpoint | Action |
|--------|---------|--------|
| GET | `/api/admin/tenants` | List all tenants (paginated) |
| GET | `/api/admin/dashboard` | Stats: tenant counts by status, user count, plans |
| GET | `/api/admin/metrics` | Tenant growth over last 30 days |
| PATCH | `/api/admin/tenants/{id}/status` | Set tenant status (active/trial/suspended) |
| PATCH | `/api/admin/tenants/{id}/plan` | Assign a plan to a tenant |
| PUT | `/api/admin/plans/{id}` | Update plan name, price, features, limits |
| POST | `/api/admin/impersonate` | Get a JWT token scoped to a tenant owner |

---

## Impersonation

The impersonate endpoint lets a platform admin log in as a tenant owner for debugging or support:

```http
POST /api/admin/impersonate
Authorization: Bearer <platform_admin_token>
Content-Type: application/json

{"tenant_id": 5}
```

Returns an access token with `"impersonated": true` in the payload. Use it as a regular Bearer token to act as that tenant's owner. The token expires normally (no extended lifetime).

---

## The web shell

`/platform-admin` is a session-protected PHP page. It:
1. Checks `$_SESSION['logged']` and `$_SESSION['role'] === 'platform_admin'`
2. Renders an HTML shell
3. The shell loads a JWT token from `sessionStorage` and calls the API via `fetch()`

To log in as platform admin: go to `/auth`, enter your `platform_admin` credentials. After login, visit `/platform-admin`.

> The web shell is a starting point. Replace or extend the JavaScript to build the admin UI your product needs.

---

## Adding platform admin features

Add methods to `src/Modules/Api/Admin/Controller.php` and `Model.php`, register routes in `src/Modules/Api/Admin/Routes/Routes.php`.

Example — suspend all trial tenants older than 30 days:

```php
// Controller
public function expireTrials(): never
{
    $count = (new Model())->expireOldTrials(30);
    $this->success(['expired' => $count], "$count trial tenants suspended");
}

// Model
public function expireOldTrials(int $days): int
{
    $stmt = $this->conn->prepare(
        "UPDATE tenants SET status = 'suspended'
         WHERE status = 'trial'
         AND created_at < DATE_SUB(NOW(), INTERVAL :days DAY)"
    );
    $stmt->execute([':days' => $days]);
    return $stmt->rowCount();
}

// Routes
$router->addRoute('/api/admin/trials/expire', Controller::class, 'expireTrials', ['cors', 'jwt']);
```
