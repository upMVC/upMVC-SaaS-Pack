# Database — SaaS pack

Same layout as the upMVC kernel's `database/` folder, so the two read
identically.

| File | Hand-written? | What |
|---|---|---|
| `migrations/001_saas_layer.sql` | yes | `plans`, `tenants`, `refresh_tokens` — the source of truth |
| `schema.sql` | **no — generated** | full SaaS-layer structure |
| `seed.sql` | yes | demo tenants, users, plans, refresh tokens |

**Never edit `schema.sql` by hand** — it's regenerated on every migrate.
Change the schema by adding a migration.

## Requires the kernel first

This pack owns only the SaaS layer. The `users` table belongs to upMVC, and
`refresh_tokens` declares a foreign key to it — so the kernel's schema must
exist first. Migrations run **kernel → packages → app** for exactly this reason.

## Option A — migrations (recommended)

Nothing to do. `SaasServiceProvider::register()` registers this folder:

```php
$app->addMigrationPath($packRoot . '/database/migrations');
```

so the runner finds it automatically once the pack is installed:

```bash
php vendor/bitshost/upmvc/src/Tools/migrate.php --status
php vendor/bitshost/upmvc/src/Tools/migrate.php
```

Then optionally load the demo data:

```bash
mysql -u root -p your_db < vendor/bitshost/upmvc-saas-pack/database/seed.sql
```

## Option B — plain SQL, no tooling

```bash
mysql -u root -p your_db < vendor/bitshost/upmvc/database/schema.sql           # users
mysql -u root -p your_db < vendor/bitshost/upmvc-saas-pack/database/schema.sql # saas layer
mysql -u root -p your_db < vendor/bitshost/upmvc-saas-pack/database/seed.sql   # demo data
```

Then mark the current migrations as applied, so future pack upgrades apply
cleanly instead of replaying from the beginning:

```bash
php vendor/bitshost/upmvc/src/Tools/migrate.php --baseline
```

## Why both a schema and migrations?

`schema.sql` serves **new** installs — one import and you're current.
Migrations serve **existing** installs: when a later pack version adds a table
or a column, importing the new `schema.sql` does nothing for a database that
already exists (`CREATE TABLE IF NOT EXISTS` skips it, and there's no `ALTER`).
The migration carries that diff, which a snapshot cannot express.

Migrations are the source of truth; `schema.sql` is regenerated from them.
Once released, a migration is **never edited** — its filename is already
recorded in other people's databases, so a change would be silently skipped
there. Fix forward with a new migration instead.

## Demo logins (from `seed.sql`)

| User | Password | Role | Tenant |
|---|---|---|---|
| `admin` | `Admin5678!` | `platform_admin` | — |
| `john.owner` | `Test1234!` | `tenant_owner` | bitsworld (Pro) |
| `maria.staff` | `Test1234!` | `tenant_user` | bitsworld (Pro) |
| `tech.admin` | `Test1234!` | `tenant_owner` | techforge (Starter) |
| `free.owner` | `Test1234!` | `tenant_owner` | freeuser (Free) |
| `ghost.inactive` | `Test1234!` | `tenant_user` | bitsworld — **inactive**, returns 403 |

Five tenants cover every middleware path: active, trial, suspended, and
plan-gated features. The full scenario list is at the bottom of `seed.sql`.

`seed.sql` is re-importable — it removes only its own demo rows, matched by
username and slug, so it won't touch data you created. Demo credentials
only; never load it into production.
