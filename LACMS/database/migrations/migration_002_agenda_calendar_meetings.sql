USE `legislative_management_db`;

-- ============================================================
-- LACMS STEPS 2-4
-- Legislative Agenda Management
-- Calendar Scheduling
-- Meeting Coordination
-- ============================================================

CREATE TABLE IF NOT EXISTS `lacms_agenda_item_history` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `agenda_item_id` BIGINT NOT NULL,
    `agenda_id` BIGINT NOT NULL,
    `action` VARCHAR(100) NOT NULL,
    `details` TEXT DEFAULT NULL,
    `changed_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_lacms_agenda_item_history` (`agenda_item_id`,`created_at`),
    CONSTRAINT `fk_lacms_agenda_item_history_item`
        FOREIGN KEY (`agenda_item_id`) REFERENCES `lacms_agenda_items` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_lacms_agenda_item_history_agenda`
        FOREIGN KEY (`agenda_id`) REFERENCES `lacms_agendas` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_lacms_agenda_item_history_user`
        FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `lacms_calendar_conflicts` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `calendar_event_id` BIGINT NOT NULL,
    `conflicting_event_id` BIGINT NOT NULL,
    `conflict_type` VARCHAR(100) NOT NULL,
    `severity` VARCHAR(30) NOT NULL DEFAULT 'Warning',
    `details` TEXT DEFAULT NULL,
    `status` VARCHAR(30) NOT NULL DEFAULT 'Open',
    `resolved_by` INT DEFAULT NULL,
    `resolved_at` DATETIME DEFAULT NULL,
    `resolution_notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_lacms_calendar_conflict_pair`
        (`calendar_event_id`,`conflicting_event_id`,`conflict_type`),
    KEY `idx_lacms_calendar_conflict_status` (`calendar_event_id`,`status`),
    CONSTRAINT `fk_lacms_calendar_conflict_event`
        FOREIGN KEY (`calendar_event_id`) REFERENCES `lacms_calendar_events` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_lacms_calendar_conflict_other`
        FOREIGN KEY (`conflicting_event_id`) REFERENCES `lacms_calendar_events` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_lacms_calendar_conflict_resolver`
        FOREIGN KEY (`resolved_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

SET @agenda_item_unique := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema=DATABASE()
      AND table_name='lacms_agenda_items'
      AND index_name='uq_lacms_agenda_legislative_item'
);
SET @sql := IF(
    @agenda_item_unique=0,
    'ALTER TABLE `lacms_agenda_items`
       ADD UNIQUE KEY `uq_lacms_agenda_legislative_item`
       (`agenda_id`,`legislative_item_id`)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @calendar_user_unique := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema=DATABASE()
      AND table_name='lacms_calendar_event_participants'
      AND index_name='uq_lacms_calendar_event_user'
);
SET @sql := IF(
    @calendar_user_unique=0,
    'ALTER TABLE `lacms_calendar_event_participants`
       ADD UNIQUE KEY `uq_lacms_calendar_event_user`
       (`calendar_event_id`,`user_id`)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @meeting_user_unique := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema=DATABASE()
      AND table_name='lacms_meeting_participants'
      AND index_name='uq_lacms_meeting_user'
);
SET @sql := IF(
    @meeting_user_unique=0,
    'ALTER TABLE `lacms_meeting_participants`
       ADD UNIQUE KEY `uq_lacms_meeting_user`
       (`meeting_id`,`user_id`)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

INSERT INTO `lacms_schema_migrations` (`migration_key`,`description`)
VALUES (
    '002_agenda_calendar_meetings',
    'Completes operational support for Legislative Agenda Management, Calendar Scheduling conflict audit, and Meeting Coordination.'
)
ON DUPLICATE KEY UPDATE
    `description`=VALUES(`description`);

SELECT migration_key,description,applied_at
FROM lacms_schema_migrations
ORDER BY applied_at,migration_key;
