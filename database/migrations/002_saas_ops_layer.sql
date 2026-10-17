-- ============================================================
-- upMVC SaaS Pack — Migration 002 — Ops layer (Harvest)
--
-- Platform settings, SMTP gateway + email log, audit trail,
-- notification kill-switches / mutes / settings, i18n overlays,
-- optional TOTP columns on users.
--
-- Domain notification *types* stay in the app (NotifyRegistry).
-- ============================================================

SET @db := DATABASE();

CREATE TABLE IF NOT EXISTS `platform_settings` (
  `setting_key` varchar(64) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `platform_settings` (`setting_key`, `setting_value`) VALUES
  ('signup_enabled', '1'),
  ('maintenance_mode', '0'),
  ('scheduler_heartbeat', ''),
  ('default_locale', 'en'),
  ('announcement_json', '');

CREATE TABLE IF NOT EXISTS `smtp_settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tenant_id` int DEFAULT NULL,
  `host` varchar(253) NOT NULL,
  `port` int NOT NULL DEFAULT 587,
  `secure` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = implicit TLS (465)',
  `username` varchar(254) DEFAULT NULL,
  `password_enc` text DEFAULT NULL,
  `from_email` varchar(254) NOT NULL,
  `from_name` varchar(120) DEFAULT NULL,
  `reply_to` varchar(254) DEFAULT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `last_test_at` datetime DEFAULT NULL,
  `last_test_ok` tinyint(1) DEFAULT NULL,
  `last_error` varchar(500) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_smtp_tenant` (`tenant_id`),
  CONSTRAINT `fk_smtp_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET @q := (
  SELECT IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='smtp_settings' AND COLUMN_NAME='platform_slot'),
    'SELECT 1',
    'ALTER TABLE smtp_settings ADD COLUMN platform_slot TINYINT GENERATED ALWAYS AS (IF(tenant_id IS NULL, 1, NULL)) STORED'
  )
);
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @q := (
  SELECT IF(
    EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='smtp_settings' AND INDEX_NAME='uk_smtp_platform'),
    'SELECT 1',
    'ALTER TABLE smtp_settings ADD UNIQUE KEY uk_smtp_platform (platform_slot)'
  )
);
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

CREATE TABLE IF NOT EXISTS `email_log` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `tenant_id` int DEFAULT NULL,
  `to_email` varchar(254) NOT NULL,
  `subject` varchar(300) NOT NULL,
  `template` varchar(80) NOT NULL DEFAULT 'generic',
  `transport` enum('tenant','platform','none') NOT NULL DEFAULT 'none',
  `status` enum('sent','failed','skipped') NOT NULL,
  `error` varchar(500) DEFAULT NULL,
  `type` varchar(64) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_email_log_at` (`created_at`),
  KEY `idx_email_log_tenant` (`tenant_id`, `created_at`),
  KEY `idx_email_log_status` (`status`, `created_at`),
  CONSTRAINT `fk_email_log_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `audit_log` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `tenant_id` int DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `impersonator_id` int DEFAULT NULL,
  `entity` varchar(64) NOT NULL,
  `entity_id` int DEFAULT NULL,
  `action` varchar(64) NOT NULL,
  `details` json DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_audit_tenant_at` (`tenant_id`, `created_at`),
  KEY `idx_audit_action` (`action`, `created_at`),
  KEY `idx_audit_impersonator` (`impersonator_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `notification_global` (
  `type` varchar(64) NOT NULL,
  `paused` tinyint(1) NOT NULL DEFAULT 0,
  `reason` varchar(500) DEFAULT NULL,
  `updated_by` int DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `notification_mutes` (
  `user_id` int NOT NULL,
  `mute_key` varchar(64) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`user_id`, `mute_key`),
  CONSTRAINT `fk_notif_mute_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `notification_settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tenant_id` int DEFAULT NULL COMMENT 'NULL = platform zone',
  `type` varchar(64) NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `frequency` varchar(16) DEFAULT NULL,
  `hour` tinyint DEFAULT NULL,
  `weekday` tinyint DEFAULT NULL,
  `roles` json DEFAULT NULL,
  `only_new` tinyint(1) NOT NULL DEFAULT 1,
  `params` json DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_notif_settings` (`tenant_id`, `type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `i18n_overlays` (
  `locale` varchar(10) NOT NULL,
  `msg_key` varchar(191) NOT NULL,
  `msg_value` text NOT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`locale`, `msg_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- TOTP (optional MFA) on users
SET @q := (
  SELECT IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='users' AND COLUMN_NAME='totp_enabled'),
    'SELECT 1',
    'ALTER TABLE users ADD COLUMN totp_enabled TINYINT(1) NOT NULL DEFAULT 0'
  )
);
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @q := (
  SELECT IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='users' AND COLUMN_NAME='totp_secret_enc'),
    'SELECT 1',
    'ALTER TABLE users ADD COLUMN totp_secret_enc VARCHAR(255) NULL DEFAULT NULL'
  )
);
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;

SET @q := (
  SELECT IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='users' AND COLUMN_NAME='totp_last_step'),
    'SELECT 1',
    'ALTER TABLE users ADD COLUMN totp_last_step BIGINT NULL DEFAULT NULL'
  )
);
PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
