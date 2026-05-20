-- ============================================================
-- ProcuChain Database Schema
-- Generated from Laravel migrations (validated via SQLite)
-- Database: MySQL 8+ (InnoDB, utf8mb4)
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- Table: users
-- Core user accounts with security features (Fortify)
-- Migrations: 0001_01_01_000000 + 2026_02_27_084000 (alter)
-- ============================================================
CREATE TABLE IF NOT EXISTS `users` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `blockchain_address` VARCHAR(255) NULL,
    `email_verified_at` TIMESTAMP NULL,
    `password` VARCHAR(255) NOT NULL,
    `account_locked` TINYINT(1) NOT NULL DEFAULT 0,
    `locked_at` TIMESTAMP NULL,
    `lock_expires_at` TIMESTAMP NULL,
    `failed_login_attempts` INT NOT NULL DEFAULT 0,
    `last_failed_login_at` TIMESTAMP NULL,
    `locked_reason` VARCHAR(255) NULL,
    `two_factor_secret` TEXT NULL,
    `two_factor_recovery_codes` TEXT NULL,
    `two_factor_confirmed_at` TIMESTAMP NULL,
    `email_notifications_enabled` TINYINT(1) NOT NULL DEFAULT 1,
    `notification_preferences` JSON NULL,
    `remember_token` VARCHAR(100) NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: password_reset_tokens
-- Password reset tokens (Fortify)
-- Migration: 0001_01_01_000000
-- ============================================================
CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
    `email` VARCHAR(255) NOT NULL,
    `token` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP NULL,
    PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: sessions
-- User sessions (database driver)
-- Migration: 0001_01_01_000000
-- ============================================================
CREATE TABLE IF NOT EXISTS `sessions` (
    `id` VARCHAR(255) NOT NULL,
    `user_id` BIGINT UNSIGNED NULL,
    `ip_address` VARCHAR(45) NULL,
    `user_agent` TEXT NULL,
    `payload` LONGTEXT NOT NULL,
    `last_activity` INT NOT NULL,
    PRIMARY KEY (`id`),
    INDEX `sessions_user_id_index` (`user_id`),
    INDEX `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: cache
-- Application cache storage
-- Migration: 0001_01_01_000001
-- ============================================================
CREATE TABLE IF NOT EXISTS `cache` (
    `key` VARCHAR(255) NOT NULL,
    `value` MEDIUMTEXT NOT NULL,
    `expiration` BIGINT NOT NULL,
    PRIMARY KEY (`key`),
    INDEX `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: cache_locks
-- Cache lock entries for atomic operations
-- Migration: 0001_01_01_000001
-- ============================================================
CREATE TABLE IF NOT EXISTS `cache_locks` (
    `key` VARCHAR(255) NOT NULL,
    `owner` VARCHAR(255) NOT NULL,
    `expiration` BIGINT NOT NULL,
    PRIMARY KEY (`key`),
    INDEX `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: jobs
-- Queue job storage
-- Migration: 0001_01_01_000002
-- ============================================================
CREATE TABLE IF NOT EXISTS `jobs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `queue` VARCHAR(255) NOT NULL,
    `payload` LONGTEXT NOT NULL,
    `attempts` TINYINT UNSIGNED NOT NULL,
    `reserved_at` INT UNSIGNED NULL,
    `available_at` INT UNSIGNED NOT NULL,
    `created_at` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`id`),
    INDEX `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: job_batches
-- Queue batch job tracking
-- Migration: 0001_01_01_000002
-- ============================================================
CREATE TABLE IF NOT EXISTS `job_batches` (
    `id` VARCHAR(255) NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `total_jobs` INT NOT NULL,
    `pending_jobs` INT NOT NULL,
    `failed_jobs` INT NOT NULL,
    `failed_job_ids` LONGTEXT NOT NULL,
    `options` MEDIUMTEXT NULL,
    `cancelled_at` INT NULL,
    `created_at` INT NOT NULL,
    `finished_at` INT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: failed_jobs
-- Failed queue job storage
-- Migration: 0001_01_01_000002
-- ============================================================
CREATE TABLE IF NOT EXISTS `failed_jobs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `uuid` VARCHAR(255) NOT NULL,
    `connection` TEXT NOT NULL,
    `queue` TEXT NOT NULL,
    `payload` LONGTEXT NOT NULL,
    `exception` LONGTEXT NOT NULL,
    `failed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: notifications
-- User notifications (polymorphic)
-- Migration: 2025_05_05_152800
-- ============================================================
CREATE TABLE IF NOT EXISTS `notifications` (
    `id` CHAR(36) NOT NULL,
    `type` VARCHAR(255) NOT NULL,
    `notifiable_type` VARCHAR(255) NOT NULL,
    `notifiable_id` BIGINT UNSIGNED NOT NULL,
    `data` TEXT NOT NULL,
    `read_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    INDEX `notifications_notifiable_type_notifiable_id_index` (`notifiable_type`, `notifiable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: user_login_logs
-- User login activity tracking (security audit)
-- Migration: 2025_06_07_074958
-- ============================================================
CREATE TABLE IF NOT EXISTS `user_login_logs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `user_agent` TEXT NULL,
    `device_type` VARCHAR(255) NULL,
    `browser` VARCHAR(255) NULL,
    `platform` VARCHAR(255) NULL,
    `location` VARCHAR(255) NULL,
    `successful` TINYINT(1) NOT NULL DEFAULT 1,
    `login_at` TIMESTAMP NOT NULL,
    `logout_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    INDEX `user_login_logs_user_id_login_at_index` (`user_id`, `login_at`),
    INDEX `user_login_logs_ip_address_index` (`ip_address`),
    INDEX `user_login_logs_login_at_index` (`login_at`),
    CONSTRAINT `user_login_logs_user_id_foreign`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: document_views
-- Procurement document access/audit trail
-- Migration: 2025_06_19_000001
-- ============================================================
CREATE TABLE IF NOT EXISTS `document_views` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `file_key` VARCHAR(255) NOT NULL,
    `pr_number` VARCHAR(255) NOT NULL,
    `procurement_title` VARCHAR(255) NULL,
    `document_type` VARCHAR(255) NULL,
    `stage` VARCHAR(255) NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `user_agent` VARCHAR(255) NULL,
    `view_duration` INT NULL,
    `metadata` JSON NULL,
    `viewed_at` TIMESTAMP NOT NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    INDEX `document_views_user_id_file_key_index` (`user_id`, `file_key`),
    INDEX `document_views_pr_number_viewed_at_index` (`pr_number`, `viewed_at`),
    INDEX `document_views_file_key_viewed_at_index` (`file_key`, `viewed_at`),
    INDEX `document_views_viewed_at_index` (`viewed_at`),
    CONSTRAINT `document_views_user_id_foreign`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: push_subscriptions
-- Web push notification subscriptions (polymorphic)
-- Migration: 2025_07_25_125737
-- ============================================================
CREATE TABLE IF NOT EXISTS `push_subscriptions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `subscribable_type` VARCHAR(255) NOT NULL,
    `subscribable_id` BIGINT UNSIGNED NOT NULL,
    `endpoint` VARCHAR(500) NOT NULL,
    `public_key` VARCHAR(255) NULL,
    `auth_token` VARCHAR(255) NULL,
    `content_encoding` VARCHAR(255) NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `push_subscriptions_endpoint_unique` (`endpoint`),
    INDEX `push_subscriptions_subscribable_type_subscribable_id_index` (`subscribable_type`, `subscribable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: permissions
-- spatie/laravel-permission — permission definitions
-- Migration: 2025_10_10_122129
-- ============================================================
CREATE TABLE IF NOT EXISTS `permissions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `guard_name` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `permissions_name_guard_name_unique` (`name`, `guard_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: roles
-- spatie/laravel-permission — role definitions
-- Migration: 2025_10_10_122129
-- ============================================================
CREATE TABLE IF NOT EXISTS `roles` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `guard_name` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `roles_name_guard_name_unique` (`name`, `guard_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: model_has_permissions
-- spatie/laravel-permission — polymorphic permission assignments
-- Migration: 2025_10_10_122129
-- ============================================================
CREATE TABLE IF NOT EXISTS `model_has_permissions` (
    `permission_id` BIGINT UNSIGNED NOT NULL,
    `model_type` VARCHAR(255) NOT NULL,
    `model_id` BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (`permission_id`, `model_id`, `model_type`),
    INDEX `model_has_permissions_model_id_model_type_index` (`model_id`, `model_type`),
    CONSTRAINT `model_has_permissions_permission_id_foreign`
        FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: model_has_roles
-- spatie/laravel-permission — polymorphic role assignments
-- Migration: 2025_10_10_122129
-- ============================================================
CREATE TABLE IF NOT EXISTS `model_has_roles` (
    `role_id` BIGINT UNSIGNED NOT NULL,
    `model_type` VARCHAR(255) NOT NULL,
    `model_id` BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (`role_id`, `model_id`, `model_type`),
    INDEX `model_has_roles_model_id_model_type_index` (`model_id`, `model_type`),
    CONSTRAINT `model_has_roles_role_id_foreign`
        FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: role_has_permissions
-- spatie/laravel-permission — role↔permission pivot
-- Migration: 2025_10_10_122129
-- ============================================================
CREATE TABLE IF NOT EXISTS `role_has_permissions` (
    `permission_id` BIGINT UNSIGNED NOT NULL,
    `role_id` BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (`permission_id`, `role_id`),
    CONSTRAINT `role_has_permissions_permission_id_foreign`
        FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
    CONSTRAINT `role_has_permissions_role_id_foreign`
        FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: blocked_ips
-- IP blocking for security (brute-force protection)
-- Migration: 2025_10_20_122706
-- ============================================================
CREATE TABLE IF NOT EXISTS `blocked_ips` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `ip_address` VARCHAR(255) NOT NULL,
    `blocked_by` BIGINT UNSIGNED NULL,
    `reason` VARCHAR(255) NULL,
    `expires_at` TIMESTAMP NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `blocked_ips_ip_address_unique` (`ip_address`),
    INDEX `blocked_ips_expires_at_index` (`expires_at`),
    INDEX `blocked_ips_is_active_index` (`is_active`),
    CONSTRAINT `blocked_ips_blocked_by_foreign`
        FOREIGN KEY (`blocked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: user_invitations
-- Role-based user invitation system
-- Migration: 2025_12_14_041833
-- ============================================================
CREATE TABLE IF NOT EXISTS `user_invitations` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `email` VARCHAR(255) NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `role` VARCHAR(255) NOT NULL,
    `token` VARCHAR(255) NOT NULL,
    `invited_by` BIGINT UNSIGNED NOT NULL,
    `expires_at` TIMESTAMP NOT NULL,
    `accepted_at` TIMESTAMP NULL,
    `user_id` BIGINT UNSIGNED NULL,
    `revoked` TINYINT(1) NOT NULL DEFAULT 0,
    `revoked_at` TIMESTAMP NULL,
    `revoked_by` BIGINT UNSIGNED NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `user_invitations_email_unique` (`email`),
    UNIQUE KEY `user_invitations_token_unique` (`token`),
    INDEX `user_invitations_token_index` (`token`),
    INDEX `user_invitations_email_index` (`email`),
    INDEX `user_invitations_expires_at_accepted_at_revoked_index` (`expires_at`, `accepted_at`, `revoked`),
    CONSTRAINT `user_invitations_invited_by_foreign`
        FOREIGN KEY (`invited_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `user_invitations_user_id_foreign`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `user_invitations_revoked_by_foreign`
        FOREIGN KEY (`revoked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: procurement_workflow_configs
-- Workflow stage configs per procurement mode (RA 12009)
-- Migration: 2025_12_25_060227
-- ============================================================
CREATE TABLE IF NOT EXISTS `procurement_workflow_configs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `procurement_mode` VARCHAR(255) NOT NULL,
    `display_name` VARCHAR(255) NOT NULL,
    `description` TEXT NULL,
    `stages` JSON NOT NULL,
    `optional_stages` JSON NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `updated_by` BIGINT UNSIGNED NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `procurement_workflow_configs_procurement_mode_unique` (`procurement_mode`),
    INDEX `procurement_workflow_configs_is_active_index` (`is_active`),
    CONSTRAINT `procurement_workflow_configs_updated_by_foreign`
        FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: stage_document_configs
-- Document requirements per stage/mode combination
-- Migration: 2025_12_25_060227
-- ============================================================
CREATE TABLE IF NOT EXISTS `stage_document_configs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `stage` VARCHAR(255) NOT NULL,
    `procurement_mode` VARCHAR(255) NOT NULL,
    `stage_display_name` VARCHAR(255) NOT NULL,
    `required_documents` JSON NOT NULL,
    `optional_documents` JSON NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `updated_by` BIGINT UNSIGNED NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `stage_document_configs_stage_procurement_mode_unique` (`stage`, `procurement_mode`),
    INDEX `stage_document_configs_stage_is_active_index` (`stage`, `is_active`),
    INDEX `stage_document_configs_procurement_mode_is_active_index` (`procurement_mode`, `is_active`),
    CONSTRAINT `stage_document_configs_updated_by_foreign`
        FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: audit_logs
-- Application-wide audit trail
-- Migration: 2026_03_04_061028
-- ============================================================
CREATE TABLE IF NOT EXISTS `audit_logs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NULL,
    `action` VARCHAR(255) NOT NULL,
    `subject_type` VARCHAR(255) NULL,
    `subject_id` VARCHAR(255) NULL,
    `old_values` JSON NULL,
    `new_values` JSON NULL,
    `ip_address` VARCHAR(45) NULL,
    `user_agent` TEXT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `audit_logs_user_id_foreign`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- Schema Summary: 20 tables
-- ============================================================
-- Core Auth:      users, password_reset_tokens, sessions
-- RBAC:           permissions, roles, model_has_permissions,
--                 model_has_roles, role_has_permissions
-- User Mgmt:      user_invitations, push_subscriptions, notifications
-- Security:       blocked_ips, user_login_logs
-- Procurement:    procurement_workflow_configs,
--                 stage_document_configs, document_views
-- Audit:          audit_logs
-- Infrastructure: cache, cache_locks, jobs, job_batches, failed_jobs
