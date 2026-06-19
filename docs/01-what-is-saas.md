# 01 — What is upMVC-SaaS?

## The short version

**upMVC-SaaS** is a layer on top of upMVC that solves the infrastructure problems every SaaS product has to solve before writing a single line of business logic:

- Who is logged in? (JWT authentication)
- Which tenant does this request belong to? (tenant isolation)
- What features can this tenant use? (plan gating)
- How do we manage tenants, plans, and users as a platform operator? (platform admin)

Once these are solved at the infrastructure level, you focus entirely on your domain.

---

## The problem it solves

Building a SaaS from scratch means spending weeks — sometimes months — on infrastructure before you touch the actual product. Auth, multi-tenancy, billing tiers, admin dashboards. Every SaaS needs them. None of it is your competitive advantage.

upMVC-SaaS gives you that infrastructure as a clean starting point, built on the same modular upMVC philosophy: simple, no magic, full control.

---

## Where it sits in the stack

```
Your product (cars, invoices, bookings, whatever)
        ↓
   upMVC-SaaS   ← tenant isolation, JWT, plans, platform admin
        ↓
     upMVC      ← routing, modules, MVC, middleware, config
        ↓
      PHP 8.1+
```

Your product fills in the `Api/*` modules. Everything below is already there.

---

## What it is NOT

- It is not a framework — upMVC is the framework
- It is not opinionated about your domain — bring your own tables, your own business logic
- It is not mandatory to follow the exact structure — the `Api/*` pattern is a recommendation, not a rule
- It is not a hosted service — you own and run your own code

See [08 — Your Way](08-your-way.md) for how to adapt it to your own approach.

---

## The lineage

```
upMVC → upMVC-SaaS → your product
```

A real-world example of a product built on this stack:
[crs-upmvc](https://github.com/upMVC/crs-upmvc) — a multi-tenant car rental SaaS with 21 API modules, dynamic pricing, reservations, payments, and extras. Same pattern, fully extended.
