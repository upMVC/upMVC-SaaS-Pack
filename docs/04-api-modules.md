# 04 — API Modules

## The Api/* pattern

All business logic lives under `src/Modules/Api/Modules/`. Each subdirectory is one domain — a standard upMVC module with Controller, Model, and Routes.

```
src/Modules/Api/Modules/
├── Auth/
│   ├── Controller.php
│   ├── Model.php
│   └── Routes/Routes.php
├── Plans/
├── Tenants/
└── Admin/
```

Modules are auto-discovered by `InitModsImproved`, which scans three fixed glob patterns relative to each registered module path:

| Pattern | Example |
|---|---|
| `*/Routes/Routes.php` | `src/Modules/Home/` |
| `*/Modules/*/Routes/Routes.php` | `src/Modules/Api/Modules/Auth/` |
| `*/Modules/*/Modules/*/Routes/Routes.php` | three levels deep |

The literal `Modules/` segment between levels is **required** — `src/Modules/Api/Bookings/` is never discovered, and fails silently with no error. Get the depth right and no registration is needed; drop the folder in and it works.

---

## Adding your own domain module

Example: adding a `Bookings` module.

**1. Create the folder structure:**
```
src/Modules/Api/Modules/Bookings/
├── Controller.php
├── Model.php
└── Routes/Routes.php
```

**2. Controller — extends SaasApiController:**

```php
<?php
namespace App\Modules\Api\Modules\Bookings;

use BitsHost\UpmvcSaas\Http\SaasApiController;

class Controller extends SaasApiController
{
    public function index(): never
    {
        $bookings = (new Model())->listByTenant($this->tenantId);
        $this->success($bookings);
    }

    public function create(): never
    {
        $body = $this->requireFields(['date', 'client_id', 'service_id']);
        $id = (new Model())->create($this->tenantId, $body);
        $this->success(['id' => $id], 'Booking created', 201);
    }
}
```

**3. Model — extends BaseModel:**

```php
<?php
namespace App\Modules\Api\Modules\Bookings;

use App\Common\Bmvc\BaseModel;

class Model extends BaseModel
{
    public function listByTenant(int $tenantId): array
    {
        $stmt = $this->conn->prepare(
            "SELECT * FROM bookings WHERE tenant_id = :tid ORDER BY date DESC"
        );
        $stmt->execute([':tid' => $tenantId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
```

**4. Routes:**

```php
<?php
namespace App\Modules\Api\Modules\Bookings\Routes;

use App\Modules\Api\Modules\Bookings\Controller;

class Routes
{
    public function routes($router): void
    {
        $router->addRoute('/api/bookings',        Controller::class, 'index',  ['cors', 'jwt'], ['GET']);
        $router->addRoute('/api/bookings/create', Controller::class, 'create', ['cors', 'jwt'], ['POST']);
    }
}
```

That's it. The module is live.

---

## SaasApiController — what you get

| Property / Method | What it provides |
|---|---|
| `$this->user` | Decoded JWT payload — `sub`, `username`, `tenant_id`, `role` |
| `$this->tenant` | Current tenant row when `tenant` middleware is used |
| `$this->tenantId` | Integer current tenant ID shortcut |
| `$this->body()` | Parsed JSON request body |
| `$this->requireFields(['a','b'])` | Returns body array or sends 400 if fields missing |
| `$this->success($data, $msg, $code)` | JSON `{"success":true,"data":...}` |
| `$this->error($msg, $code)` | JSON `{"error":"..."}` and exits |

---

## Tenant isolation pattern

Every model method that reads or writes tenant data must scope by `tenant_id`. This is your responsibility — the framework gives you the tenant ID via `$this->user['tenant_id']`, you use it in your queries.

```php
// Always scope to tenant
"SELECT * FROM bookings WHERE tenant_id = :tid"

// Never expose cross-tenant data
"SELECT * FROM bookings"  // ← wrong
```
