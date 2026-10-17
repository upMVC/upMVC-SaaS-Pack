# upMVC SaaS Pack — Documentation

Installable Composer library **`bitshost/upmvc-saas-pack`**: multi-tenant SaaS layer for [upMVC](https://github.com/upMVC/upMVC).

This documentation covers what is **specific to the pack** — tenancy, JWT auth flows, plans, feature gating, Harvest ops kernel, and API patterns.

For the base framework (routing, modules, MVC, configuration, middleware, agent pack) refer to **upMVC**:

> **upMVC Docs** → https://github.com/upMVC/upMVC/tree/main/docs  
> **Agent SaaS knowledge** → upMVC `docs/agent/upmvc-saas-pack.json` (updated for Harvest)

---

## Documentation index

| Doc | What it covers |
|-----|---------------|
| [01 — What is this SaaS layer](01-what-is-saas.md) | Concept, why the pack exists |
| [02 — Architecture](02-architecture.md) | Tenancy + Harvest layers, middleware, modules |
| [03 — Getting Started](03-getting-started.md) | Require pack, configure, migrate, first tenant |
| [04 — API Modules](04-api-modules.md) | How Api/* modules work and how to add domain |
| [05 — Auth & JWT](05-auth-jwt.md) | JWT login, refresh, logout, theft detection |
| [06 — Tenants & Plans](06-tenants-plans.md) | Multi-tenancy, plan gating, lifecycle |
| [07 — Platform Admin](07-platform-admin.md) | Admin shell, Api/Admin, impersonation |
| [08 — Your Way](08-your-way.md) | Starting point, not a rulebook |
| [09 — Harvest ops](09-harvest-ops.md) | Mail, notify kernel, audit, TOTP, Attention, i18n, dual-unseal |

---

## Quick orientation

```
vendor/bitshost/upmvc-saas-pack/
├── src/Modules/
│   ├── Api/Modules/
│   │   ├── Auth/, Plans/, Tenants/, Admin/   ← tenancy APIs
│   │   ├── PlatformOps/, I18n/              ← Harvest thin APIs
│   ├── Auth/, PlatformAdmin/, TenantApp/, …
│   └── Mail/                               ← delegates to MailGateway
├── src/Saas/                               ← Harvest libraries (BitsHost\UpmvcSaas\*)
└── database/migrations/
    ├── 001_saas_layer.sql                  ← plans, tenants, refresh_tokens
    └── 002_saas_ops_layer.sql              ← SMTP, email_log, audit, notify, i18n, totp
```

Host app owns domain under `src/Modules/Api/Modules/{Domain}/` and may thin-extend Harvest classes (they are not `final`).
