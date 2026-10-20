USE `legislative_management_db`;

-- ============================================================
-- ORLMS — STEPS 5, 6 AND 7
-- Approval & Enactment, Publication & Dissemination,
-- Implementation Monitoring
-- ============================================================

CREATE TABLE IF NOT EXISTS `orlms_approval_history` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `approval_action_id` BIGINT NOT NULL,
    `legislative_item_id` INT NOT NULL,
    `action` VARCHAR(100) NOT NULL,
    `previous_status` VARCHAR(50) DEFAULT NULL,
    `new_status` VARCHAR(50) DEFAULT NULL,
    `details` TEXT DEFAULT NULL,
    `changed_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_orlms_approval_history_action` (`approval_action_id`,`created_at`),
    KEY `idx_orlms_approval_history_item` (`legislative_item_id`,`created_at`),
    CONSTRAINT `fk_orlms_approval_history_action`
        FOREIGN KEY (`approval_action_id`) REFERENCES `orlms_approval_actions` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_orlms_approval_history_item`
        FOREIGN KEY (`legislative_item_id`) REFERENCES `legislative_items` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_orlms_approval_history_user`
        FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `orlms_enactments`
    ADD COLUMN IF NOT EXISTS `enactment_status` VARCHAR(40) NOT NULL DEFAULT 'Draft'
        AFTER `enactment_type`,
    ADD COLUMN IF NOT EXISTS `finalized_by` INT DEFAULT NULL
        AFTER `recorded_by`,
    ADD COLUMN IF NOT EXISTS `finalized_at` DATETIME DEFAULT NULL
        AFTER `finalized_by`;

SET @has_enactment_finalized_fk := (
    SELECT COUNT(*)
    FROM information_schema.table_constraints
    WHERE constraint_schema=DATABASE()
      AND table_name='orlms_enactments'
      AND constraint_name='fk_orlms_enactment_finalized_by'
);
SET @sql := IF(
    @has_enactment_finalized_fk=0,
    'ALTER TABLE `orlms_enactments`
       ADD CONSTRAINT `fk_orlms_enactment_finalized_by`
       FOREIGN KEY (`finalized_by`) REFERENCES `users` (`id`) ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_official_no_uq := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema=DATABASE()
      AND table_name='orlms_enactments'
      AND index_name='uq_orlms_official_number'
);
SET @sql := IF(
    @has_official_no_uq=0,
    'ALTER TABLE `orlms_enactments`
       ADD UNIQUE KEY `uq_orlms_official_number` (`official_number`)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS `orlms_enactment_history` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `enactment_id` BIGINT NOT NULL,
    `legislative_item_id` INT NOT NULL,
    `action` VARCHAR(100) NOT NULL,
    `previous_status` VARCHAR(50) DEFAULT NULL,
    `new_status` VARCHAR(50) DEFAULT NULL,
    `details` TEXT DEFAULT NULL,
    `changed_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_orlms_enactment_history_enactment` (`enactment_id`,`created_at`),
    CONSTRAINT `fk_orlms_enactment_history_enactment`
        FOREIGN KEY (`enactment_id`) REFERENCES `orlms_enactments` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_orlms_enactment_history_item`
        FOREIGN KEY (`legislative_item_id`) REFERENCES `legislative_items` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_orlms_enactment_history_user`
        FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `orlms_publication_history` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `publication_id` BIGINT NOT NULL,
    `legislative_item_id` INT NOT NULL,
    `action` VARCHAR(100) NOT NULL,
    `previous_status` VARCHAR(50) DEFAULT NULL,
    `new_status` VARCHAR(50) DEFAULT NULL,
    `details` TEXT DEFAULT NULL,
    `changed_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_orlms_publication_history_publication` (`publication_id`,`created_at`),
    CONSTRAINT `fk_orlms_publication_history_publication`
        FOREIGN KEY (`publication_id`) REFERENCES `orlms_publications` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_orlms_publication_history_item`
        FOREIGN KEY (`legislative_item_id`) REFERENCES `legislative_items` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_orlms_publication_history_user`
        FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `orlms_dissemination_recipients`
    ADD COLUMN IF NOT EXISTS `delivery_notes` TEXT DEFAULT NULL
        AFTER `delivery_status`,
    ADD COLUMN IF NOT EXISTS `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`;

CREATE TABLE IF NOT EXISTS `orlms_publication_documents` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `publication_id` BIGINT NOT NULL,
    `file_name` VARCHAR(255) NOT NULL,
    `file_path` VARCHAR(500) NOT NULL,
    `document_type` VARCHAR(100) NOT NULL DEFAULT 'Publication Document',
    `description` TEXT DEFAULT NULL,
    `visibility` VARCHAR(30) NOT NULL DEFAULT 'Public',
    `uploaded_by` INT DEFAULT NULL,
    `uploaded_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_orlms_publication_document` (`publication_id`,`uploaded_at`),
    CONSTRAINT `fk_orlms_publication_document_publication`
        FOREIGN KEY (`publication_id`) REFERENCES `orlms_publications` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_orlms_publication_document_user`
        FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `orlms_implementation_history` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `implementation_plan_id` BIGINT NOT NULL,
    `legislative_item_id` INT NOT NULL,
    `action` VARCHAR(100) NOT NULL,
    `previous_status` VARCHAR(50) DEFAULT NULL,
    `new_status` VARCHAR(50) DEFAULT NULL,
    `details` TEXT DEFAULT NULL,
    `changed_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_orlms_implementation_history_plan` (`implementation_plan_id`,`created_at`),
    CONSTRAINT `fk_orlms_implementation_history_plan`
        FOREIGN KEY (`implementation_plan_id`) REFERENCES `orlms_implementation_plans` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_orlms_implementation_history_item`
        FOREIGN KEY (`legislative_item_id`) REFERENCES `legislative_items` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_orlms_implementation_history_user`
        FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `orlms_implementation_documents` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `implementation_plan_id` BIGINT NOT NULL,
    `implementation_update_id` BIGINT DEFAULT NULL,
    `file_name` VARCHAR(255) NOT NULL,
    `file_path` VARCHAR(500) NOT NULL,
    `document_type` VARCHAR(100) NOT NULL DEFAULT 'Implementation Evidence',
    `description` TEXT DEFAULT NULL,
    `visibility` VARCHAR(30) NOT NULL DEFAULT 'Internal',
    `uploaded_by` INT DEFAULT NULL,
    `uploaded_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_orlms_impl_document_plan` (`implementation_plan_id`,`uploaded_at`),
    KEY `idx_orlms_impl_document_update` (`implementation_update_id`),
    CONSTRAINT `fk_orlms_impl_document_plan`
        FOREIGN KEY (`implementation_plan_id`) REFERENCES `orlms_implementation_plans` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_orlms_impl_document_update`
        FOREIGN KEY (`implementation_update_id`) REFERENCES `orlms_implementation_updates` (`id`)
        ON DELETE SET NULL,
    CONSTRAINT `fk_orlms_impl_document_user`
        FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `orlms_implementation_issues` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `implementation_plan_id` BIGINT NOT NULL,
    `issue_title` VARCHAR(255) NOT NULL,
    `description` LONGTEXT DEFAULT NULL,
    `severity` VARCHAR(30) NOT NULL DEFAULT 'Medium',
    `status` VARCHAR(40) NOT NULL DEFAULT 'Open',
    `responsible_office_id` INT DEFAULT NULL,
    `due_date` DATE DEFAULT NULL,
    `resolution` LONGTEXT DEFAULT NULL,
    `created_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `resolved_by` INT DEFAULT NULL,
    `resolved_at` DATETIME DEFAULT NULL,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_orlms_impl_issue_plan` (`implementation_plan_id`,`status`,`severity`),
    KEY `idx_orlms_impl_issue_due` (`due_date`,`status`),
    CONSTRAINT `fk_orlms_impl_issue_plan`
        FOREIGN KEY (`implementation_plan_id`) REFERENCES `orlms_implementation_plans` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_orlms_impl_issue_office`
        FOREIGN KEY (`responsible_office_id`) REFERENCES `offices` (`id`)
        ON DELETE SET NULL,
    CONSTRAINT `fk_orlms_impl_issue_created_by`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL,
    CONSTRAINT `fk_orlms_impl_issue_resolved_by`
        FOREIGN KEY (`resolved_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `orlms_schema_migrations` (`migration_key`,`description`)
VALUES (
    '003_approval_publication_implementation',
    'Completes approval/enactment history, official-number integrity, publication dissemination evidence, and implementation monitoring history/issues/evidence.'
)
ON DUPLICATE KEY UPDATE
    `description`=VALUES(`description`);

SELECT
    EXISTS(
      SELECT 1 FROM information_schema.tables
      WHERE table_schema=DATABASE() AND table_name='orlms_approval_history'
    ) AS approval_history_ok,
    EXISTS(
      SELECT 1 FROM information_schema.tables
      WHERE table_schema=DATABASE() AND table_name='orlms_publication_documents'
    ) AS publication_documents_ok,
    EXISTS(
      SELECT 1 FROM information_schema.tables
      WHERE table_schema=DATABASE() AND table_name='orlms_implementation_issues'
    ) AS implementation_issues_ok,
    EXISTS(
      SELECT 1 FROM information_schema.columns
      WHERE table_schema=DATABASE()
        AND table_name='orlms_enactments'
        AND column_name='enactment_status'
    ) AS enactment_status_ok;
