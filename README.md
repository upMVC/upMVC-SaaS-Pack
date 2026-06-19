# upMVC SaaS Pack

Installable SaaS module pack for upMVC.

This package adds multi-tenant SaaS capabilities to an upMVC application without turning the core framework into a SaaS-only project.

## What This Pack Adds

- API modules for auth, tenants, plans, and platform admin
- Web shell modules for auth, platform admin, tenant app, tenant shop, and home
- Tenant and plan-gate middleware
- SaaS API base controller with tenant/user context
- Demo schema and seed data

## Intended Architecture

```text
bitshost/upmvc
  standalone project + reusable kernel

bitshost/upmvc-saas-pack
  installable SaaS module pack

bitshost/upmvc-saas
  ready starter app requiring both
```

## Install In An upMVC App

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
- migration path metadata

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
    "bitshost/upmvc": "*",
    "bitshost/upmvc-saas-pack": "*"
  }
}
```

## Notes

This is not a standalone application. Use `upMVC-SaaS` as the starter app when you want a ready-to-run SaaS project.
