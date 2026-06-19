# 08 — Your Way

## This is a starting point, not a rulebook

upMVC-SaaS is a boilerplate. Everything in it is a suggestion — a proven pattern that works, not a constraint you must follow.

The `Api/*` flat module structure works well. The dual auth system (JWT for API, sessions for web shells) works well. The shared-database multi-tenancy with `tenant_id` FKs works well. But none of it is mandatory.

---

## What you might change

**Module organisation** — `Api/*` is flat. You might prefer grouping by bounded context, or nesting differently. upMVC's auto-discovery works however you organise your `Modules/` directory, as long as each module has `Routes/Routes.php`.

**Multi-tenancy strategy** — the default is shared schema with `tenant_id` filters. For strong data isolation you might prefer one database per tenant, or one schema per tenant. The `TenantMiddleware` gives you the resolved tenant — what you do with that at the database level is yours to decide.

**Authentication** — JWT with refresh token rotation is solid for APIs. But if your product is entirely server-rendered with no external API consumers, session-only auth might be simpler and sufficient. Nothing forces you to use JWT.

**Plans and feature flags** — the JSON feature flag approach is flexible but application-level only. You might enforce limits at the database level, or use a more sophisticated billing integration (Stripe, Paddle). The plans table is a starting point.

**The web shells** — `PlatformAdmin`, `TenantApp`, `TenantShop` are placeholder shells. Replace them entirely with a React/Vue SPA, a separate frontend project, or server-rendered PHP pages — whatever fits your product.

---

## The one thing that is not optional

**PSR-4 compliance.** PHP's autoloader requires namespaces to match directory paths exactly — and on Linux servers, this is case-sensitive. `App\Modules\Api\Auth` must live in `src/Modules/Api/Auth/`. Get this right, everything else is flexible.

For the base framework autoloading rules see the [upMVC documentation](https://github.com/upMVC/upMVC/tree/main/docs).

---

## A real-world example

[crs-upmvc](https://github.com/upMVC/crs-upmvc) is a production car rental SaaS built on this boilerplate. It extended `Api/*` with 21 domain modules: Cars, Reservations, Locations, Pricing, Seasonal, Extras, Payments, Discounts, Clients, and more.

Same patterns, fully extended. No framework changes needed — just more modules.

---

## Summary

Start here. Break what doesn't fit. Keep what works. The goal is shipping your product, not maintaining fidelity to a boilerplate.
