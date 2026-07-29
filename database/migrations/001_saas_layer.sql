-- ============================================================
-- upMVC SaaS Pack — Migration 001 — SaaS layer
--
-- Creates: plans, tenants, refresh_tokens
-- Seeds:   3 starter plans (Free / Starter / Pro)
--
-- DEPENDS ON: the kernel `users` table (upMVC 001_base_schema.sql).
-- refresh_tokens declares a FOREIGN KEY to users(id), so kernel
-- migrations must run before pack migrations.
-- ============================================================

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
    token_hash  CHAR(64)     NOT NULL  COMMENT 'SHA-256 hex of the raw opaque token',
    expires_at  DATETIME     NOT NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    revoked_at  DATETIME     NULL DEFAULT NULL,

    UNIQUE KEY uk_token_hash (token_hash),
    KEY        idx_user_id   (user_id),
    KEY        idx_expires   (expires_at),

    CONSTRAINT fk_rt_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed starter plans (skip if already exist)
INSERT IGNORE INTO plans (id, name, price, features, limits) VALUES
    (1, 'Free',    0.00,
     '{"invoices":true,"efactura":false,"advanced_reports":false}',
     '{"invoices_per_month":20,"api_calls_per_day":100}'),
    (2, 'Starter', 19.00,
     '{"invoices":true,"efactura":true,"advanced_reports":false}',
     '{"invoices_per_month":500,"api_calls_per_day":5000}'),
    (3, 'Pro',     49.00,
     '{"invoices":true,"efactura":true,"advanced_reports":true}',
     '{"invoices_per_month":0,"api_calls_per_day":0}');
