# 06 — Tenants & Plans

## Tenant registration

```http
POST /api/tenants/register
Content-Type: application/json

{
  "slug": "acme",
  "name": "Acme Corp",
  "username": "acme.admin",
  "email": "admin@acme.com",
  "password": "SecurePass123!",
  "plan_id": 1
}
```

This creates the tenant and its first owner user atomically. The slug must be unique and becomes the tenant identifier in URLs: `/app/acme/admin`.

New tenants start with status `trial`. Activate via the platform admin API.

---

## Tenant lifecycle

```
register → trial → active → suspended (→ soft deleted)
```

Status is controlled by the platform admin via `PATCH /api/admin/tenants/{id}/status`.

---

## Plans

Three plans are seeded by default:

| ID | Name | Price | Features |
|----|------|-------|---------|
| 1 | Free | $0 | Basic features, low limits |
| 2 | Starter | $19 | More features, medium limits |
| 3 | Pro | $49 | All features, unlimited |

Plans store features and limits as JSON:

```json
{
  "features": {"invoices": true, "efactura": true, "advanced_reports": false},
  "limits": {"invoices_per_month": 500, "api_calls_per_day": 5000}
}
```

---

## Feature gating

Route-level gating via `PlanGateMiddleware`:

```php
$router->addRoute('/api/invoices/efactura', Controller::class, 'send', ['cors', 'jwt', 'feature:efactura']);
```

If the tenant's plan does not include `efactura: true`, the middleware returns 403 before the controller is even reached.

Feature flags are resolved from `tenants.features` (JSON), which is populated when a plan is assigned. You control what features each plan includes.

---

## Tenant isolation

Every tenant's data is separated by `tenant_id` in each table. This is a **shared-database, shared-schema** model — all tenants share the same tables, rows are filtered by `tenant_id`.

The `TenantMiddleware` resolves the current tenant from:
- **Path-based:** `/app/{slug}/...` — extracts slug from URL
- **Subdomain-based:** `{slug}.yourdomain.com` — extracts slug from HTTP_HOST

Resolved tenant is stored in `$GLOBALS['current_tenant']` for the duration of the request.

---

## Customising plans

Edit the seed data in `database/migrations/002_saas_layer.sql` or update directly:

```sql
UPDATE plans SET
    name = 'Growth',
    price = 39.00,
    features = '{"invoices":true,"efactura":true,"crm":true}',
    limits = '{"invoices_per_month":2000}'
WHERE id = 2;
```

Or use the platform admin API: `PUT /api/admin/plans/{id}`.

---

## Reading tenant context in a controller

```php
// From JWT (Api/* modules)
$tenantId = (int) ($this->user['tenant_id'] ?? 0);
$role     = $this->user['role'] ?? '';

// From TenantMiddleware (tenant web shell routes)
$tenant   = $GLOBALS['current_tenant'] ?? [];
$features = $tenant['features'] ?? [];
```
