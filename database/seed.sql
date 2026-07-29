-- ============================================================
-- seed.sql — demo data for the upMVC SaaS pack
--
-- Import AFTER the schema:
--   mysql -u root -p your_db < vendor/bitshost/upmvc/database/schema.sql          -- users
--   mysql -u root -p your_db < vendor/bitshost/upmvc-saas-pack/database/schema.sql -- saas layer
--   mysql -u root -p your_db < vendor/bitshost/upmvc-saas-pack/database/seed.sql   -- this file
--
-- Or let migrations build the schema and import only this file:
--   php vendor/bitshost/upmvc/src/Tools/migrate.php
--   mysql -u root -p your_db < .../database/seed.sql
--
-- Data only — no CREATE TABLE. Structure lives in migrations/001_saas_layer.sql.
--
-- Re-importable: removes only ITS OWN demo rows, matched by username and
-- slug. It will not touch users or tenants you created yourself.
--
-- Demo credentials. Never load this into production.
-- ============================================================

-- -----------------------------------------------
-- Remove previous demo rows (FK-safe order)
-- -----------------------------------------------

DELETE FROM refresh_tokens
 WHERE user_id IN (
     SELECT id FROM (
         SELECT id FROM users
          WHERE username IN ('admin','john.owner','maria.staff','ghost.inactive',
                             'tech.admin','free.owner','trial.owner','dark.owner')
     ) AS doomed
 );

DELETE FROM users
 WHERE username IN ('admin','john.owner','maria.staff','ghost.inactive',
                    'tech.admin','free.owner','trial.owner','dark.owner');

DELETE FROM tenants
 WHERE slug IN ('bitsworld','techforge','freeuser','trialco','darkstar');

-- -----------------------------------------------
-- Plans
--
-- The migration seeds three minimal starter plans. These richer demo
-- definitions overwrite them so the feature-gating scenarios below work.
-- features / limits are JSON — extend freely. 0 = unlimited.
-- -----------------------------------------------

INSERT INTO plans (id, name, price, features, limits) VALUES
    (1, 'Free',    0.00,
     '{"invoices":true,"efactura":false,"advanced_reports":false,"api_access":false}',
     '{"invoices_per_month":20,"users":1,"api_calls_per_day":0}'),

    (2, 'Starter', 19.00,
     '{"invoices":true,"efactura":true,"advanced_reports":false,"api_access":true}',
     '{"invoices_per_month":500,"users":5,"api_calls_per_day":5000}'),

    (3, 'Pro',     49.00,
     '{"invoices":true,"efactura":true,"advanced_reports":true,"api_access":true}',
     '{"invoices_per_month":0,"users":0,"api_calls_per_day":0}')
ON DUPLICATE KEY UPDATE
    name = VALUES(name), price = VALUES(price),
    features = VALUES(features), limits = VALUES(limits);

-- -----------------------------------------------
-- Tenants — five scenarios
-- -----------------------------------------------

INSERT INTO tenants (id, slug, name, plan_id, status, features) VALUES
    (1, 'bitsworld',  'BitsWorld SRL',   3, 'active',    '{"invoices":true,"efactura":true,"advanced_reports":true,"api_access":true}'),
    (2, 'techforge',  'TechForge Ltd',   2, 'active',    '{"invoices":true,"efactura":true,"advanced_reports":false,"api_access":true}'),
    (3, 'freeuser',   'Free User Co',    1, 'active',    '{"invoices":true,"efactura":false,"advanced_reports":false,"api_access":false}'),
    (4, 'trialco',    'Trial Company',   1, 'trial',     '{"invoices":true,"efactura":false,"advanced_reports":false,"api_access":false}'),
    (5, 'darkstar',   'DarkStar Agency', 2, 'suspended', '{"invoices":true,"efactura":true,"advanced_reports":false,"api_access":true}');

-- -----------------------------------------------
-- Users
--
-- password 'Admin5678!' → $2y$10$WMvYt7KDx5epitl/W8wAtuPmZs7wbOkU1Cix0nkcyYFSJGl5H.KQq
-- password 'Test1234!'  → $2y$10$JR/tLsIt6ZXtCsRdLgVb/OjoxQ7LsVoTrLxrUWJVmzNEwTKqsiRfS
-- -----------------------------------------------

INSERT INTO users (id, tenant_id, name, username, email, password, token, state, role) VALUES

    -- Platform admin (no tenant)
    (1, NULL, 'Platform Admin', 'admin', 'admin@upmvc.dev',
     '$2y$10$WMvYt7KDx5epitl/W8wAtuPmZs7wbOkU1Cix0nkcyYFSJGl5H.KQq', '', 1, 'platform_admin'),

    -- bitsworld (Pro) — owner + two users
    (2, 1, 'John Owner',    'john.owner',    'john@bitsworld.dev',
     '$2y$10$JR/tLsIt6ZXtCsRdLgVb/OjoxQ7LsVoTrLxrUWJVmzNEwTKqsiRfS', '', 1, 'tenant_owner'),

    (3, 1, 'Maria Staff',   'maria.staff',   'maria@bitsworld.dev',
     '$2y$10$JR/tLsIt6ZXtCsRdLgVb/OjoxQ7LsVoTrLxrUWJVmzNEwTKqsiRfS', '', 1, 'tenant_user'),

    -- inactive: never clicked the activation link
    (4, 1, 'Ghost Account', 'ghost.inactive','ghost@bitsworld.dev',
     '$2y$10$JR/tLsIt6ZXtCsRdLgVb/OjoxQ7LsVoTrLxrUWJVmzNEwTKqsiRfS',
     'activation-token-abc123', 0, 'tenant_user'),

    -- techforge (Starter) — owner
    (5, 2, 'Tech Admin',    'tech.admin',    'admin@techforge.dev',
     '$2y$10$JR/tLsIt6ZXtCsRdLgVb/OjoxQ7LsVoTrLxrUWJVmzNEwTKqsiRfS', '', 1, 'tenant_owner'),

    -- freeuser (Free) — owner
    (6, 3, 'Free Owner',    'free.owner',    'owner@freeuser.dev',
     '$2y$10$JR/tLsIt6ZXtCsRdLgVb/OjoxQ7LsVoTrLxrUWJVmzNEwTKqsiRfS', '', 1, 'tenant_owner'),

    -- trialco (trial) — owner
    (7, 4, 'Trial Owner',   'trial.owner',   'owner@trialco.dev',
     '$2y$10$JR/tLsIt6ZXtCsRdLgVb/OjoxQ7LsVoTrLxrUWJVmzNEwTKqsiRfS', '', 1, 'tenant_owner'),

    -- darkstar (suspended) — owner
    (8, 5, 'Dark Owner',    'dark.owner',    'owner@darkstar.dev',
     '$2y$10$JR/tLsIt6ZXtCsRdLgVb/OjoxQ7LsVoTrLxrUWJVmzNEwTKqsiRfS', '', 1, 'tenant_owner');

-- -----------------------------------------------
-- Refresh tokens — three states for Auth module testing
-- -----------------------------------------------

INSERT INTO refresh_tokens (user_id, token_hash, expires_at, created_at, revoked_at) VALUES

    -- VALID — use to test POST /api/auth/refresh
    (2, 'b3c6e4d2f5a1e9c7d4b8f2a6e3c9d1b5f7a4e2c8d6b3f1a9e5c2d8b4f6a1e3c7',
     DATE_ADD(NOW(), INTERVAL 30 DAY), NOW(), NULL),

    -- EXPIRED — expires_at in the past → 401
    (2, 'a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6e7f8a9b0c1d2e3f4a5b6c7d8e9f0a1b2',
     DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 31 DAY), NULL),

    -- REVOKED — revoked_at set → theft detection triggers on replay
    (2, 'f0e9d8c7b6a5f4e3d2c1b0a9f8e7d6c5b4a3f2e1d0c9b8a7f6e5d4c3b2a1f0e9',
     DATE_ADD(NOW(), INTERVAL 30 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY), NOW());

-- ============================================================
-- Quick reference
--
-- Login (POST /api/auth/login):
--   admin          / Admin5678!  → platform_admin JWT (no tenant)
--   john.owner     / Test1234!   → tenant_owner  JWT (bitsworld, Pro)
--   maria.staff    / Test1234!   → tenant_user   JWT (bitsworld, Pro)
--   ghost.inactive / Test1234!   → 403 account inactive
--   tech.admin     / Test1234!   → tenant_owner  JWT (techforge, Starter)
--   free.owner     / Test1234!   → tenant_owner  JWT (freeuser, Free)
--
-- TenantMiddleware paths:
--   /app/bitsworld/... → tenant 1, active, Pro
--   /app/techforge/... → tenant 2, active, Starter
--   /app/freeuser/...  → tenant 3, active, Free
--   /app/trialco/...   → 403 trial not yet active
--   /app/darkstar/...  → 403 suspended
--   /app/nobody/...    → 404 not found
--
-- PlanGateMiddleware:
--   feature:efactura         + bitsworld  → PASS
--   feature:efactura         + freeuser   → 403
--   feature:advanced_reports + techforge  → 403 (Starter)
--   feature:advanced_reports + bitsworld  → PASS (Pro)
-- ============================================================
