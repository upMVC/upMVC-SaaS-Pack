# upMVC-SaaS Documentation

**upMVC-SaaS** is a multi-tenant SaaS boilerplate built on top of the upMVC PHP framework.

This documentation covers only what is **specific to the SaaS layer** — multi-tenancy, JWT authentication, plans, feature gating, and the API architecture.

For the base framework (routing, modules, MVC, configuration, middleware) refer to the **upMVC documentation**:

> **upMVC Docs** → https://github.com/upMVC/upMVC/tree/main/docs

---

## SaaS Documentation Index

| Doc | What it covers |
|-----|---------------|
| [01 — What is upMVC-SaaS](01-what-is-saas.md) | The concept, why this layer exists, what problem it solves |
| [02 — Architecture](02-architecture.md) | Api/* structure, middleware pipeline, the two auth systems |
| [03 — Getting Started](03-getting-started.md) | Fork, configure, run migrations, create first tenant |
| [04 — API Modules](04-api-modules.md) | How Api/* modules work and how to add your own domain |
| [05 — Auth & JWT](05-auth-jwt.md) | JWT login, refresh token rotation, logout, token theft detection |
| [06 — Tenants & Plans](06-tenants-plans.md) | Multi-tenancy, plan gating, feature flags, tenant lifecycle |
| [07 — Platform Admin](07-platform-admin.md) | The admin shell, Api/Admin endpoints, impersonation |
| [08 — Your Way](08-your-way.md) | This is a starting point, not a rulebook |

---

## Quick orientation

```
src/Modules/
├── Api/                ← the backbone — your business logic lives here
│   ├── Auth/           JWT login / refresh / logout
│   ├── Plans/          public plan listing
│   ├── Tenants/        tenant registration + CRUD
│   └── Admin/          platform admin API
├── Auth/               session-based web login (for the PHP shell pages)
├── PlatformAdmin/      thin web shell → calls Api/Admin via JS
├── TenantApp/          tenant web application shell
├── TenantShop/         tenant storefront shell
├── Home/               public landing page
└── Mail/               email service (PHPMailer via Composer)
```
