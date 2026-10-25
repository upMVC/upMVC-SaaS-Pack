# 03 — Getting Started

This package is an installable SaaS pack for an upMVC application. It is not a standalone app by itself.

## Requirements

- PHP 8.3+
- MySQL 5.7+ / MariaDB 10.3+
- Composer
- An upMVC host application

## 1. Install The Pack

From your upMVC application:

```bash
composer require bitshost/upmvc-saas-pack
```

For local development before package publishing, use Composer path repositories in the host app:

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

## 2. Register The Provider

Add the SaaS provider to the host app:

```php
// src/Etc/packages.php
return [
    \BitsHost\UpmvcSaas\SaasServiceProvider::class,
];
```

The provider registers the pack module path, SaaS middleware, feature-gate middleware factory, protected routes, and migration path metadata.

## 3. Configure Environment

The host application owns `.env`. Minimum SaaS keys:

```env
DB_HOST=localhost
DB_PORT=3306
DB_NAME=my_saas_db
DB_USER=root
DB_PASS=

JWT_SECRET=your-random-secret-minimum-32-chars
JWT_ACCESS_TTL=3600
JWT_REFRESH_TTL=2592000
```

## 4. Load Schema / Demo Data

Run the migrations — the pack registers its own migration folder from its
service provider, so the runner picks it up automatically:

```bash
php vendor/bitshost/upmvc/src/Tools/migrate.php
```

Then load the demo tenants, users and plans:

```bash
mysql -u root -p my_saas_db < vendor/bitshost/upmvc-saas-pack/database/seed.sql
```

Prefer plain SQL with no tooling? Import the two schema files instead —
kernel first, because `refresh_tokens` has a foreign key to `users`:

```bash
mysql -u root -p my_saas_db < vendor/bitshost/upmvc/database/schema.sql
mysql -u root -p my_saas_db < vendor/bitshost/upmvc-saas-pack/database/schema.sql
mysql -u root -p my_saas_db < vendor/bitshost/upmvc-saas-pack/database/seed.sql
```

See `database/README.md` in the pack for the full file map and demo logins.

## 5. Test The Pack Routes

```bash
curl http://localhost/api/plans

curl -X POST http://localhost/api/tenants/register \
  -H "Content-Type: application/json" \
  -d '{"slug":"acme","name":"Acme Corp","username":"acme.admin","email":"admin@acme.com","password":"Test1234!"}'

curl -X POST http://localhost/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"acme.admin","password":"Test1234!"}'
```

## Starter App

Use `upMVC-SaaS` when you want a ready-to-run app that already requires `bitshost/upmvc` and this pack.
