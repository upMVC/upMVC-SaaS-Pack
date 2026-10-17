# Changelog

All notable changes to `bitshost/upmvc-saas-pack`.

## [Unreleased]

_(empty — next changes go here)_

## [1.1.0] — Harvest: shared SaaS ops kernel (2026-10-17)

Release: https://github.com/upMVC/upMVC-SaaS-Pack/releases/tag/v1.1.0

Lifted from the `myCMMS-PHP` probe into pack libraries so every SaaS product gets shared factory pieces without CMMS domain coupling. See `docs/09-harvest-ops.md`.

#### Added
- **`database/migrations/002_saas_ops_layer.sql`** — `platform_settings`, `smtp_settings`, `email_log`, `audit_log`, `notification_*`, `i18n_overlays`, optional `users.totp_*`.
- **`BitsHost\UpmvcSaas\Mail\MailGateway`** + **`Security\SecretBox`** — platform SMTP, plan-gated tenant SMTP, `.env` fallback, send log.
- **`BitsHost\UpmvcSaas\Notify\{NotifyRegistry,NotifyGate,Notify}`** — empty type registry (apps register), global pause, instant fire with mutes.
- **`BitsHost\UpmvcSaas\Audit\AuditLog`** — append-only audit with impersonator support.
- **`BitsHost\UpmvcSaas\Security\Totp`** — RFC 6238 helper for platform MFA.
- **`BitsHost\UpmvcSaas\Ops\{Attention,PlatformSettings,SchedulerHeartbeat}`** — generic Attention + flags + cron heartbeat.
- **`BitsHost\UpmvcSaas\I18n\Catalog`** — file catalogs + dotted-key DB overlays.
- **`Api/Modules/PlatformOps`**, **`Api/Modules/I18n`** — thin overridable API surfaces.
- **`public/lang/{en,ro}.json`** — minimal shell i18n seed.
- **`tests/Unit/HarvestKernelTest.php`** — Totp, registry, audit actors, catalog overlays, migration guard.
- **`docs/09-harvest-ops.md`** — wiring guide and pack vs new-pack rule.

#### Changed
- **`App\Modules\Mail\MailController`** — delegates to `MailGateway` (legacy helpers kept, deprecated).
- **Harvest kernel classes** (`AuditLog`, `Totp`, `SecretBox`, `MailGateway`, `NotifyGate`, `Notify`) — dropped `final` so consuming apps can thin-extend without import churn.
- **`Security\SecretBox` / `Security\Totp`** — dual-unseal read bridge: `seal()` writes with pack peppers only; `unseal()` also accepts myCMMS legacy peppers (`|smtp-password-v1`, `mycmms-totp|`) so path installs do not brick existing SMTP/MFA rows. See `docs/09-harvest-ops.md`.
- **`.gitignore`** — ignore local `vault/` (agent handoff notes; never commit contents).
- **`tests/Unit/HarvestKernelTest.php`** — dual-unseal coverage for legacy SMTP and TOTP peppers.

#### Deliberately out of pack
- Domain notify types, payments/billing, full PlatformAdmin UI tabs, AI translate.

---

## v1.0.0 "Tenancy" - Multi-tenant SaaS modules for upMVC (2026-07-29)

First release. Requires **upMVC ^2.4**, which introduced the library-mode
kernel (`App\Etc\Application`, module and migration paths, middleware
factories) this pack registers against.

Ships nine modules, two middlewares and a service provider: JWT auth with
refresh tokens, tenant registration and resolution, plans with feature gating,
a platform admin API, and web shells for the admin, tenant app and public
tenant page.

### Database
- **`database/migrations/001_saas_layer.sql`** — New. Creates `plans`, `tenants`, `refresh_tokens` and seeds three starter plans. Moved out of the upMVC kernel, where these tenant tables did not belong — the kernel owns `users` only.
  - Previously `database/migrations/` contained nothing but `.gitkeep`, while `SaasServiceProvider` already registered it as a migration path. Installing the pack therefore created **no tables at all**.
  - Verified: applying the kernel's `001_base_schema.sql` followed by this file to an empty database reproduces a known-good install exactly — 49/49 schema metadata lines, zero differences.
- **`database/schema.sql`** — New, **generated** from the migration. The SaaS layer only; requires the kernel's `users` table first, since `refresh_tokens` declares a foreign key to it.
- **`database/seed.sql`** — New. Demo data only, no `CREATE TABLE`: 5 tenants covering active / trial / suspended, 8 users, 3 plans, and refresh tokens in valid / expired / revoked states.
  - Deletes are **scoped by username and slug**. The previous `demo.sql` ran `DELETE FROM users`, `tenants`, `plans` and `refresh_tokens` unscoped, destroying any real data on re-import.
  - Plans are upserted, resolving a drift where `demo.sql` seeded `api_access` and a `users` limit that the migration did not.
- **`database/README.md`** — New. Mirrors the kernel's `database/` layout so both read identically, and explains why a schema snapshot and migrations both exist.
- **Removed** `database/demo.sql` — split into `schema.sql` (structure, generated) and `seed.sql` (data). It re-declared all four tables, making structure a second source of truth that could drift from the migration.

### Testing
- **`phpunit.xml`**, **`tests/bootstrap.php`** — First test configuration for the pack. 22 tests, 49 assertions, no database required.
- **`tests/Unit/SaasServiceProviderTest.php`** — Asserts the provider registers module and migration paths that **exist on disk**, that the migration directory actually contains `.sql` files, that protected routes are registered, and that `boot()` binds the `tenant` middleware and the `feature` factory. The migration assertion is a direct regression guard for the empty-directory bug above.
- **`tests/Unit/PackStructureTest.php`** — Replays the kernel's three module-discovery globs against what the pack ships, failing any module at a depth the kernel does not scan. Module discovery is filesystem-driven and silent: a module at the wrong depth registers no routes and reports no error. Also asserts every `Routes` class matches its PSR-4 namespace, that migrations are non-empty and create the expected tables, and that `seed.sql` contains no unscoped deletes.
- **`tests/Unit/TenantMiddlewareTest.php`** — 7 cases on `resolveSlug()`: `/app/{slug}` paths, subdomains, casing, query strings, and path-beats-subdomain precedence.
- **`tests/Unit/PlanGateMiddlewareTest.php`** — Feature gating with `features` as both an array and a raw JSON string.
  - Only pass paths are covered: every failure path in both middlewares ends in `exit()`, which would terminate the test runner. Worth recording rather than working around — middleware that exits cannot be composed or tested for its refusals.

### Static Analysis
- **`phpstan.neon`** — First PHPStan configuration, level 5 over `src` and `tests`, bootstrapping only the autoloader so it runs without a database. Ignores only the kernel's runtime constants (`BASE_URL`, `SITEPATH`).

### Fixed
- **`src/Modules/PlatformAdmin/Controller.php`** — Impersonation called `JwtService::verify()`, a method that does not exist. `JwtService` issues only; verification lived in a private `JwtAuthMiddleware::verifyJwt()` and was unreachable. Submitting an impersonation token therefore fataled with *"Call to undefined method"*. Now calls `JwtAuthMiddleware::verifyToken()`, made public in upMVC for exactly this case.
  - The bug survived because the surrounding guards (`platform_admin` session, POST, non-empty token) meant no ordinary page load reached the line — a plain `GET /platform-admin` returns 302 and never touches it. Found by the pack's first PHPStan run.

### Dependencies
- **`composer.json`** — Requires `bitshost/upmvc: ^2.4`. The pack depends on the library-mode kernel (`App\Etc\Application`, `addModulePath`, `addMigrationPath`, `addProtectedRoutes`, `Router::registerMiddlewareFactory`), none of which existed in v2.1.0 — the highest release at the time. Installing the pack therefore produced a kernel that could not run it. Resolved by upMVC v2.4.0.
  - Verified with a clean install from Packagist alone: `rm -rf vendor composer.lock && composer install` resolves `bitshost/upmvc v2.4.0` from the public repository, all required APIs are present, and both PHPStan and the test suite pass against it.

### Documentation
- **`docs/02-architecture.md`**, **`04-api-modules.md`**, **`07-platform-admin.md`**, **`08-your-way.md`** — Corrected the module path from `src/Modules/Api/{Domain}/` to `src/Modules/Api/Modules/{Domain}/`. The documented layout is never discovered by the kernel, so following it produced routes that silently did not exist. `04-api-modules.md` now documents the three glob patterns and states that the literal `Modules/` segment is required.
- **`docs/03-getting-started.md`** — Install instructions rewritten around migrations, with the plain-SQL route as an alternative.
