# upMVC SaaS Pack

Installable SaaS module pack for upMVC (`bitshost/upmvc-saas-pack`).

Adds multi-tenant SaaS capabilities to an upMVC application without turning the core framework into a SaaS-only project.

**Current release:** [**v1.1.0 — Harvest**](https://github.com/upMVC/upMVC-SaaS-Pack/releases/tag/v1.1.0) — tenancy v1.0 + Harvest ops kernel (mail, notify primitives, audit, TOTP, Attention, i18n overlays).

## What This Pack Adds

### Tenancy (v1.0)

- API modules for auth, tenants, plans, and platform admin
- Web shell modules for auth, platform admin, tenant app, tenant shop, and home
- Tenant and plan-gate middleware (`tenant`, `feature:*`)
- SaaS API base controller with tenant/user context
- Migration `001_saas_layer.sql` — `plans`, `tenants`, `refresh_tokens`

### Harvest ops kernel

Shared factory libraries under `BitsHost\UpmvcSaas\` (not CMMS domain):

| Piece | Role |
|-------|------|
| `Mail\MailGateway` + `Security\SecretBox` | Platform / plan-gated tenant SMTP, `.env` fallback, `email_log`, sealed passwords |
| `Notify\{NotifyRegistry,NotifyGate,Notify}` | Empty registry (apps register types); pause; instant fire + mutes |
| `Audit\AuditLog` | Append-only trail + impersonator |
| `Security\Totp` | Platform MFA helper |
| `Ops\{Attention,PlatformSettings,SchedulerHeartbeat}` | Attention board, flags, cron heartbeat |
| `I18n\Catalog` | File catalogs + DB overlays |
| Migration `002_saas_ops_layer.sql` | Tables for the above |
| API `PlatformOps`, `I18n` | Thin routes — apps override for domain Attention |

**Deliberately out of pack:** domain notify catalogs, payments/billing, full PlatformAdmin UI tabs, AI translate.

See **[`docs/09-harvest-ops.md`](docs/09-harvest-ops.md)** for wiring, dual-unseal continuity, and the pack-vs-new-pack rule.

## Intended Architecture

```text
bitshost/upmvc
  standalone project + reusable kernel

bitshost/upmvc-saas-pack
  installable SaaS module pack (tenancy + Harvest)

bitshost/upmvc-saas / product apps (e.g. myCMMS-PHP)
  require both; register SaasServiceProvider; own domain modules
```

## Install In An upMVC App

Requires **upMVC ^2.4** (library-mode `Application`, module/migration paths, middleware factories).

```bash
composer require bitshost/upmvc-saas-pack
```

Then register the provider in the host app:

```php
// src/Etc/packages.php
return [
    \BitsHost\UpmvcSaas\SaasServiceProvider::class,
];
```

The provider registers:

- the pack module path
- SaaS protected routes
- `tenant` named middleware
- `feature:*` middleware factory
- migration path metadata (`001` + `002`)

Apply migrations with the host app’s migrate tool (kernel → pack → app order).

## Local Development

Use Composer path repositories from the host app while developing locally:

```json
{
  "repositories": [
    {
      "type": "path",
      "url": "../upMVC",
      "options": { "symlink": true }
    },
    {
      "type": "path",
      "url": "../upMVC-SaaS-Pack",
      "options": { "symlink": true }
    }
  ],
  "require": {
    "bitshost/upmvc": "^2.4",
    "bitshost/upmvc-saas-pack": "dev-master"
  },
  "minimum-stability": "dev",
  "prefer-stable": true
}
```

## Agent / docs

- Human docs: [`docs/`](docs/) — especially [`09-harvest-ops.md`](docs/09-harvest-ops.md)
- Kernel agent JSON (when pack is installed): **upMVC** `docs/agent/upmvc-saas-pack.json` — do not duplicate a full agent pack here
- Short agent pointer in this repo: [`AGENTS.md`](AGENTS.md)

## Notes

This is not a standalone application. Use a starter or product app that `composer require`s the kernel and this pack.
