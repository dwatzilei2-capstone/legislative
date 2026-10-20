USE `legislative_management_db`;

-- ============================================================
-- LACMS REPAIR
-- Restore missing lacms_reminder_templates table only.
--
-- Safe for the current case:
-- - Migration 003 is already recorded as installed.
-- - SHOW TABLES LIKE 'lacms_reminder_templates' returned no row.
-- - This does NOT rerun the full Migration 003.
-- ============================================================

CREATE TABLE IF NOT EXISTS `lacms_reminder_templates` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `template_code` VARCHAR(80) NOT NULL,
    `name` VARCHAR(180) NOT NULL,
    `source_type` VARCHAR(80) NOT NULL DEFAULT 'Deadline',
    `subject_template` VARCHAR(255) NOT NULL,
    `message_template` TEXT NOT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_lacms_reminder_template_code` (`template_code`),
    CONSTRAINT `fk_lacms_reminder_template_user`
        FOREIGN KEY (`created_by`)
        REFERENCES `users` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_general_ci;

-- Restore the two default templates expected by the LACMS reminder workflow.

INSERT INTO `lacms_reminder_templates`
(
    `template_code`,
    `name`,
    `source_type`,
    `subject_template`,
    `message_template`,
    `is_active`,
    `created_by`
)
VALUES
(
    'DEADLINE_STANDARD',
    'Standard Legislative Deadline Reminder',
    'Deadline',
    'Reminder: {{title}} is due {{due_text}}',
    'This is a reminder that {{title}} is due on {{due_datetime}}. Priority: {{priority}}. Responsible: {{responsible}}. {{context}}',
    1,
    NULL
),
(
    'MEETING_STANDARD',
    'Standard Legislative Meeting Reminder',
    'Meeting',
    'Reminder: {{title}} on {{schedule}}',
    'This is a reminder for {{title}} scheduled on {{schedule}} at {{venue}}. {{context}}',
    1,
    NULL
)
ON DUPLICATE KEY UPDATE
    `name`=VALUES(`name`),
    `source_type`=VALUES(`source_type`),
    `subject_template`=VALUES(`subject_template`),
    `message_template`=VALUES(`message_template`),
    `is_active`=1;

-- ============================================================
-- VERIFICATION
-- ============================================================

SHOW TABLES LIKE 'lacms_reminder_templates';

SELECT
    `id`,
    `template_code`,
    `name`,
    `source_type`,
    `is_active`,
    `created_at`
FROM `lacms_reminder_templates`
ORDER BY `id`;
