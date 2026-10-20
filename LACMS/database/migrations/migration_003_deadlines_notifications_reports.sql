USE `legislative_management_db`;

-- ============================================================
-- LACMS STEPS 5-7
-- Deadline Tracking
-- Notifications / AI-Assisted Reminder Foundation
-- Dashboard / Reports / Cross-Module Workflow
-- ============================================================

CREATE TABLE IF NOT EXISTS `lacms_deadline_escalations` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `deadline_id` BIGINT NOT NULL,
    `escalation_level` VARCHAR(50) NOT NULL DEFAULT 'Reminder',
    `reason` TEXT NOT NULL,
    `status` VARCHAR(50) NOT NULL DEFAULT 'Open',
    `escalated_to_user_id` INT DEFAULT NULL,
    `escalated_to_office_id` INT DEFAULT NULL,
    `created_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `resolved_by` INT DEFAULT NULL,
    `resolved_at` DATETIME DEFAULT NULL,
    `resolution_notes` TEXT DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_lacms_deadline_escalation` (`deadline_id`,`status`,`created_at`),
    CONSTRAINT `fk_lacms_deadline_escalation_deadline`
        FOREIGN KEY (`deadline_id`) REFERENCES `lacms_deadlines` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_lacms_deadline_escalation_user`
        FOREIGN KEY (`escalated_to_user_id`) REFERENCES `users` (`id`)
        ON DELETE SET NULL,
    CONSTRAINT `fk_lacms_deadline_escalation_office`
        FOREIGN KEY (`escalated_to_office_id`) REFERENCES `offices` (`id`)
        ON DELETE SET NULL,
    CONSTRAINT `fk_lacms_deadline_escalation_created`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL,
    CONSTRAINT `fk_lacms_deadline_escalation_resolved`
        FOREIGN KEY (`resolved_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_lacms_reminder_template_code` (`template_code`),
    CONSTRAINT `fk_lacms_reminder_template_user`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `lacms_notification_delivery_attempts` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `notification_recipient_id` BIGINT NOT NULL,
    `delivery_channel` VARCHAR(50) NOT NULL,
    `attempt_number` INT NOT NULL DEFAULT 1,
    `status` VARCHAR(50) NOT NULL,
    `provider` VARCHAR(100) DEFAULT NULL,
    `response_code` VARCHAR(100) DEFAULT NULL,
    `response_message` TEXT DEFAULT NULL,
    `attempted_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_lacms_notification_delivery_attempt`
        (`notification_recipient_id`,`attempted_at`),
    CONSTRAINT `fk_lacms_notification_delivery_recipient`
        FOREIGN KEY (`notification_recipient_id`)
        REFERENCES `lacms_notification_recipients` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `lacms_ai_request_logs` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `source_type` VARCHAR(80) NOT NULL,
    `source_id` BIGINT DEFAULT NULL,
    `provider` VARCHAR(50) NOT NULL DEFAULT 'ollama',
    `model_used` VARCHAR(100) DEFAULT NULL,
    `request_prompt` LONGTEXT DEFAULT NULL,
    `response_payload` LONGTEXT DEFAULT NULL,
    `http_status` INT DEFAULT NULL,
    `success` TINYINT(1) NOT NULL DEFAULT 0,
    `error_message` TEXT DEFAULT NULL,
    `duration_ms` INT DEFAULT NULL,
    `created_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_lacms_ai_source` (`source_type`,`source_id`,`created_at`),
    CONSTRAINT `fk_lacms_ai_request_user`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

SET @deadline_reminder_unique := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema=DATABASE()
      AND table_name='lacms_deadline_reminders'
      AND index_name='uq_lacms_deadline_reminder_schedule'
);
SET @sql := IF(
    @deadline_reminder_unique=0,
    'ALTER TABLE `lacms_deadline_reminders`
       ADD UNIQUE KEY `uq_lacms_deadline_reminder_schedule`
       (`deadline_id`,`remind_at`,`reminder_type`)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

INSERT INTO `lacms_reminder_templates`
(`template_code`,`name`,`source_type`,`subject_template`,`message_template`,`is_active`,`created_by`)
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
    `subject_template`=VALUES(`subject_template`),
    `message_template`=VALUES(`message_template`),
    `is_active`=1;

INSERT INTO `lacms_schema_migrations` (`migration_key`,`description`)
VALUES (
    '003_deadlines_notifications_reports',
    'Completes Deadline Tracking, notification/reminder delivery audit, Ollama-assisted reminder logging, and reporting support.'
)
ON DUPLICATE KEY UPDATE
    `description`=VALUES(`description`);

SELECT migration_key,description,applied_at
FROM lacms_schema_migrations
ORDER BY applied_at,migration_key;
