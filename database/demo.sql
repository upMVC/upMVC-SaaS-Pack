-- ============================================================
-- upMVC-SaaS — demo.sql
-- One-command import: schema + demo data
--
-- Creates a fully populated demo environment.
-- DO NOT run on production.
--
-- Usage:
--   mysql -u root -p your_db_name < database/demo.sql
--
-- Test credentials:
--   Platform admin  : admin           / Admin5678!
--   All tenant users: <username>      / Test1234!
--
-- Tenant scenarios:
--   bitsworld   → Pro plan,     active   (full feature access)
--   techforge   → Starter plan, active   (mid-tier)
--   freeuser    → Free plan,    active   (limited features)
--   trialco     → Free plan,    trial    (new signup)
--   darkstar    → Starter plan, suspended
--
-- API quick test:
--   POST /api/auth/login {"username":"admin","password":"Admin5678!"}
--   POST /api/auth/login {"username":"john.owner","password":"Test1234!"}
--   GET  /api/plans
-- ============================================================

-- -----------------------------------------------
-- Schema
-- -----------------------------------------------

CREATE TABLE IF NOT EXISTS `users` (
    `id`        INT AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` INT              NULL     DEFAULT NULL    COMMENT 'NULL = platform admin',
    `name`      VARCHAR(255)     NOT NULL DEFAULT '',
    `email`     VARCHAR(255)     NOT NULL DEFAULT '',
    `username`  VARCHAR(100)     NOT NULL,
    `password`  VARCHAR(255)     NOT NULL                 COMMENT 'bcrypt hash',
    `token`     VARCHAR(64)      NOT NULL DEFAULT '',
    `state`     TINYINT(1)       NOT NULL DEFAULT 0       COMMENT '0=inactive, 1=active',
    `role`      VARCHAR(30)      NOT NULL DEFAULT 'tenant_user'
                                          COMMENT 'platform_admin | tenant_owner | tenant_user',
    `stamp`     TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_username (username),
    KEY idx_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS plans (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100)    NOT NULL,
    price      DECIMAL(10, 2)  NOT NULL DEFAULT 0.00,
    features   JSON            NOT NULL DEFAULT ('{}'),
    limits     JSON            NOT NULL DEFAULT ('{}'),
    created_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tenants (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    slug       VARCHAR(100)    UNIQUE NOT NULL,
    name       VARCHAR(255)    NOT NULL,
    plan_id    INT             NULL DEFAULT NULL,
    status     ENUM('active','suspended','trial') NOT NULL DEFAULT 'trial',
    features   JSON            NOT NULL DEFAULT ('{}'),
    created_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at DATETIME        NULL DEFAULT NULL,

    KEY idx_status (status),
    CONSTRAINT fk_tenants_plan FOREIGN KEY (plan_id) REFERENCES plans (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS refresh_tokens (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT          NOT NULL,
    token_hash  CHAR(64)     NOT NULL  COMMENT 'SHA-256 hex of the raw opaque token — never store raw',
    expires_at  DATETIME     NOT NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    revoked_at  DATETIME     NULL DEFAULT NULL,

    UNIQUE KEY uk_token_hash (token_hash),
    KEY        idx_user_id   (user_id),
    KEY        idx_expires   (expires_at),

    CONSTRAINT fk_rt_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------
-- Wipe existing demo data (safe re-import)
-- DELETE FROM respects FK order; TRUNCATE does not honor FOREIGN_KEY_CHECKS=0 in MySQL 5.7+
-- -----------------------------------------------

DELETE FROM refresh_tokens;
DELETE FROM users;
DELETE FROM tenants;
DELETE FROM plans;

-- -----------------------------------------------
-- Plans
-- features / limits are JSON — extend freely
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
     '{"invoices_per_month":0,"users":0,"api_calls_per_day":0}');
-- 0 = unlimited

-- -----------------------------------------------
-- Tenants  (5 scenarios)
-- -----------------------------------------------

INSERT INTO tenants (id, slug, name, plan_id, status, features) VALUES
    (1, 'bitsworld',  'BitsWorld SRL',       3, 'active',    '{"invoices":true,"efactura":true,"advanced_reports":true,"api_access":true}'),
    (2, 'techforge',  'TechForge Ltd',        2, 'active',    '{"invoices":true,"efactura":true,"advanced_reports":false,"api_access":true}'),
    (3, 'freeuser',   'Free User Co',         1, 'active',    '{"invoices":true,"efactura":false,"advanced_reports":false,"api_access":false}'),
    (4, 'trialco',    'Trial Company',        1, 'trial',     '{"invoices":true,"efactura":false,"advanced_reports":false,"api_access":false}'),
    (5, 'darkstar',   'DarkStar Agency',      2, 'suspended', '{"invoices":true,"efactura":true,"advanced_reports":false,"api_access":true}');

-- -----------------------------------------------
-- Users
--
-- password 'Admin5678!' → $2y$10$WMvYt7KDx5epitl/W8wAtuPmZs7wbOkU1Cix0nkcyYFSJGl5H.KQq
-- password 'Test1234!'  → $2y$10$JR/tLsIt6ZXtCsRdLgVb/OjoxQ7LsVoTrLxrUWJVmzNEwTKqsiRfS
-- -----------------------------------------------

INSERT INTO users (id, tenant_id, name, username, email, password, token, state, role) VALUES

    -- Platform admin (no tenant)
    (1, NULL,
     'Platform Admin', 'admin', 'admin@upmvc.dev',
     '$2y$10$WMvYt7KDx5epitl/W8wAtuPmZs7wbOkU1Cix0nkcyYFSJGl5H.KQq',
     '', 1, 'platform_admin'),

    -- bitsworld (Pro) — owner + two users
    (2, 1, 'John Owner',    'john.owner',    'john@bitsworld.dev',
     '$2y$10$JR/tLsIt6ZXtCsRdLgVb/OjoxQ7LsVoTrLxrUWJVmzNEwTKqsiRfS', '', 1, 'tenant_owner'),

    (3, 1, 'Maria Staff',   'maria.staff',   'maria@bitsworld.dev',
     '$2y$10$JR/tLsIt6ZXtCsRdLgVb/OjoxQ7LsVoTrLxrUWJVmzNEwTKqsiRfS', '', 1, 'tenant_user'),

    (4, 1, 'Ghost Account', 'ghost.inactive','ghost@bitsworld.dev',
     '$2y$10$JR/tLsIt6ZXtCsRdLgVb/OjoxQ7LsVoTrLxrUWJVmzNEwTKqsiRfS',
     'activation-token-abc123', 0, 'tenant_user'),  -- inactive: never clicked activation link

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

    -- VALID — can be used to test POST /api/auth/refresh
    (2,
     'b3c6e4d2f5a1e9c7d4b8f2a6e3c9d1b5f7a4e2c8d6b3f1a9e5c2d8b4f6a1e3c7',
     DATE_ADD(NOW(), INTERVAL 30 DAY), NOW(), NULL),

    -- EXPIRED — expires_at is in the past → should return 401
    (2,
     'a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6e7f8a9b0c1d2e3f4a5b6c7d8e9f0a1b2',
     DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 31 DAY), NULL),

    -- REVOKED — revoked_at is set → theft detection kicks in if replayed
    (2,
     'f0e9d8c7b6a5f4e3d2c1b0a9f8e7d6c5b4a3f2e1d0c9b8a7f6e5d4c3b2a1f0e9',
     DATE_ADD(NOW(), INTERVAL 30 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY), NOW());

-- -----------------------------------------------
-- Quick reference
-- -----------------------------------------------
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
