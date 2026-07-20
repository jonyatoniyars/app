-- PalliCare — MySQL Schema
-- Import via cPanel → phpMyAdmin → Import
-- Run setup.php after importing to create tables AND seed users

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `users` (
  `id`                     VARCHAR(36)  NOT NULL,
  `name`                   VARCHAR(100) NOT NULL,
  `email`                  VARCHAR(150) NULL,
  `phone`                  VARCHAR(20)  NULL,
  `password_hash`          VARCHAR(255) NOT NULL,
  `role`                   ENUM('HEALTH_WORKER','DOCTOR','ADMIN') NOT NULL,
  `status`                 ENUM('PENDING','ACTIVE','SUSPENDED') NOT NULL DEFAULT 'PENDING',
  `can_write_prescription` TINYINT(1)   NOT NULL DEFAULT 0,
  `created_at`             DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`             DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_email` (`email`),
  UNIQUE KEY `uq_phone` (`phone`),
  INDEX `idx_role`   (`role`),
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `doctor_assignments` (
  `id`               VARCHAR(36) NOT NULL,
  `doctor_id`        VARCHAR(36) NOT NULL,
  `health_worker_id` VARCHAR(36) NOT NULL,
  `assigned_at`      DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hw` (`health_worker_id`),
  INDEX `idx_doctor` (`doctor_id`),
  FOREIGN KEY (`doctor_id`)        REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`health_worker_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `medicines` (
  `id`           VARCHAR(36)  NOT NULL,
  `name`         VARCHAR(100) NOT NULL,
  `generic_name` VARCHAR(100) NULL,
  `form`         ENUM('TABLET','CAPSULE','SYRUP','INJECTION','OINTMENT','DROPS','INHALER','SUPPOSITORY','PATCH','OTHER') NOT NULL DEFAULT 'TABLET',
  `is_active`    TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_name`   (`name`),
  INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `prescriptions` (
  `id`               VARCHAR(36)  NOT NULL,
  `health_worker_id` VARCHAR(36)  NOT NULL,
  `patient_name`     VARCHAR(100) NOT NULL,
  `patient_age`      TINYINT UNSIGNED NOT NULL,
  `patient_gender`   ENUM('male','female','other') NOT NULL,
  `chief_complaints` TEXT         NOT NULL,
  `on_examination`   TEXT         NULL,
  `advice`           TEXT         NULL,
  `status`           ENUM('DRAFT','SUBMITTED','REVIEWED') NOT NULL DEFAULT 'DRAFT',
  `reviewed_by_id`   VARCHAR(36)  NULL,
  `reviewed_at`      DATETIME     NULL,
  `review_notes`     TEXT         NULL,
  `created_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_hw`     (`health_worker_id`),
  INDEX `idx_status` (`status`),
  FOREIGN KEY (`health_worker_id`) REFERENCES `users`(`id`),
  FOREIGN KEY (`reviewed_by_id`)   REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `prescription_items` (
  `id`              VARCHAR(36)  NOT NULL,
  `prescription_id` VARCHAR(36)  NOT NULL,
  `medicine_id`     VARCHAR(36)  NOT NULL,
  `dose`            VARCHAR(50)  NOT NULL,
  `frequency`       VARCHAR(50)  NOT NULL,
  `duration`        VARCHAR(50)  NOT NULL,
  `instructions`    VARCHAR(200) NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_rx` (`prescription_id`),
  FOREIGN KEY (`prescription_id`) REFERENCES `prescriptions`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`medicine_id`)     REFERENCES `medicines`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `video_call_requests` (
  `id`           VARCHAR(36) NOT NULL,
  `requester_id` VARCHAR(36) NOT NULL,
  `receiver_id`  VARCHAR(36) NOT NULL,
  `note`         TEXT        NULL,
  `status`       ENUM('PENDING','ACCEPTED','DECLINED') NOT NULL DEFAULT 'PENDING',
  `created_at`   DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_req` (`requester_id`),
  INDEX `idx_rec` (`receiver_id`),
  FOREIGN KEY (`requester_id`) REFERENCES `users`(`id`),
  FOREIGN KEY (`receiver_id`)  REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id`                 VARCHAR(36)  NOT NULL,
  `admin_id`           VARCHAR(36)  NOT NULL,
  `action`             VARCHAR(100) NOT NULL,
  `target_entity_type` VARCHAR(50)  NULL,
  `target_entity_id`   VARCHAR(36)  NULL,
  `metadata`           JSON         NULL,
  `created_at`         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_admin`  (`admin_id`),
  FOREIGN KEY (`admin_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
