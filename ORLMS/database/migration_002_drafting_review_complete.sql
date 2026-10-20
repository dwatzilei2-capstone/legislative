USE `legislative_management_db`;

-- ============================================================
-- ORLMS — STEPS 2, 3 AND 4
-- Ordinance Drafting, Resolution Submission,
-- Review and Committee Endorsement
-- ============================================================

ALTER TABLE `orlms_item_details`
    ADD COLUMN IF NOT EXISTS `submission_note` TEXT DEFAULT NULL AFTER `submitted_at`;

CREATE TABLE IF NOT EXISTS `orlms_review_checklist` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `review_id` BIGINT NOT NULL,
    `check_code` VARCHAR(80) NOT NULL,
    `check_name` VARCHAR(200) NOT NULL,
    `check_status` VARCHAR(30) NOT NULL DEFAULT 'Pending',
    `reviewer_note` TEXT DEFAULT NULL,
    `sequence_number` INT NOT NULL DEFAULT 1,
    `reviewed_by` INT DEFAULT NULL,
    `reviewed_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_orlms_review_check_code` (`review_id`,`check_code`),
    KEY `idx_orlms_review_check_status` (`review_id`,`check_status`),
    CONSTRAINT `fk_orlms_review_check_review`
        FOREIGN KEY (`review_id`) REFERENCES `orlms_reviews` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_orlms_review_check_user`
        FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `orlms_review_comments` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `review_id` BIGINT NOT NULL,
    `legislative_item_id` INT NOT NULL,
    `comment_type` VARCHAR(60) NOT NULL DEFAULT 'Comment',
    `section_reference` VARCHAR(150) DEFAULT NULL,
    `comment_text` LONGTEXT NOT NULL,
    `status` VARCHAR(30) NOT NULL DEFAULT 'Open',
    `resolution_text` LONGTEXT DEFAULT NULL,
    `created_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `resolved_by` INT DEFAULT NULL,
    `resolved_at` DATETIME DEFAULT NULL,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_orlms_review_comment_review` (`review_id`,`status`,`created_at`),
    KEY `idx_orlms_review_comment_item` (`legislative_item_id`,`status`),
    CONSTRAINT `fk_orlms_review_comment_review`
        FOREIGN KEY (`review_id`) REFERENCES `orlms_reviews` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_orlms_review_comment_item`
        FOREIGN KEY (`legislative_item_id`) REFERENCES `legislative_items` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_orlms_review_comment_created_by`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL,
    CONSTRAINT `fk_orlms_review_comment_resolved_by`
        FOREIGN KEY (`resolved_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

SET @endorsement_review_unique := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema=DATABASE()
      AND table_name='orlms_endorsements'
      AND index_name='uq_orlms_endorsement_review'
);

SET @sql := IF(
    @endorsement_review_unique=0,
    'ALTER TABLE `orlms_endorsements` ADD UNIQUE KEY `uq_orlms_endorsement_review` (`review_id`)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

INSERT INTO `orlms_schema_migrations` (`migration_key`,`description`)
VALUES (
    '002_drafting_review_complete',
    'Completes ordinance/resolution drafting, submission, committee review checklists, review comments and one-endorsement-per-review integrity.'
)
ON DUPLICATE KEY UPDATE
    `description`=VALUES(`description`);

SELECT
    EXISTS(
        SELECT 1 FROM information_schema.tables
        WHERE table_schema=DATABASE()
          AND table_name='orlms_review_checklist'
    ) AS review_checklist_ok,
    EXISTS(
        SELECT 1 FROM information_schema.tables
        WHERE table_schema=DATABASE()
          AND table_name='orlms_review_comments'
    ) AS review_comments_ok,
    EXISTS(
        SELECT 1 FROM information_schema.columns
        WHERE table_schema=DATABASE()
          AND table_name='orlms_item_details'
          AND column_name='submission_note'
    ) AS submission_note_ok;
