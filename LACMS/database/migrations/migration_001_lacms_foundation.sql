USE `legislative_management_db`;

-- ============================================================
-- LACMS STEP 1
-- Database & Source Alignment Foundation
-- ============================================================

CREATE TABLE IF NOT EXISTS `lacms_schema_migrations` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `migration_key` VARCHAR(120) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `applied_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_lacms_schema_migration_key` (`migration_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------
-- AGENDA MANAGEMENT
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `lacms_agendas` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `agenda_reference` VARCHAR(80) NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `agenda_type` VARCHAR(100) NOT NULL DEFAULT 'Legislative Session',
    `committee_id` INT DEFAULT NULL,
    `office_id` INT DEFAULT NULL,
    `agenda_date` DATE DEFAULT NULL,
    `start_time` TIME DEFAULT NULL,
    `end_time` TIME DEFAULT NULL,
    `venue` VARCHAR(255) DEFAULT NULL,
    `meeting_link` VARCHAR(500) DEFAULT NULL,
    `description` TEXT DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `status` VARCHAR(50) NOT NULL DEFAULT 'Draft',
    `created_by` INT DEFAULT NULL,
    `finalized_by` INT DEFAULT NULL,
    `finalized_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_lacms_agenda_reference` (`agenda_reference`),
    KEY `idx_lacms_agenda_date_status` (`agenda_date`,`status`),
    KEY `idx_lacms_agenda_committee` (`committee_id`),
    CONSTRAINT `fk_lacms_agenda_committee`
        FOREIGN KEY (`committee_id`) REFERENCES `committees` (`id`)
        ON DELETE SET NULL,
    CONSTRAINT `fk_lacms_agenda_office`
        FOREIGN KEY (`office_id`) REFERENCES `offices` (`id`)
        ON DELETE SET NULL,
    CONSTRAINT `fk_lacms_agenda_created_by`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL,
    CONSTRAINT `fk_lacms_agenda_finalized_by`
        FOREIGN KEY (`finalized_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `lacms_agenda_items` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `agenda_id` BIGINT NOT NULL,
    `legislative_item_id` INT DEFAULT NULL,
    `item_number` VARCHAR(50) DEFAULT NULL,
    `sequence_number` INT NOT NULL DEFAULT 1,
    `agenda_section` VARCHAR(100) DEFAULT 'New Business',
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `sponsor_user_id` INT DEFAULT NULL,
    `presenter_user_id` INT DEFAULT NULL,
    `committee_id` INT DEFAULT NULL,
    `priority_level` VARCHAR(50) DEFAULT 'Normal',
    `estimated_minutes` INT DEFAULT NULL,
    `item_status` VARCHAR(50) NOT NULL DEFAULT 'Pending',
    `disposition` VARCHAR(150) DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `created_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_lacms_agenda_item_agenda_seq` (`agenda_id`,`sequence_number`),
    KEY `idx_lacms_agenda_item_legislative` (`legislative_item_id`),
    CONSTRAINT `fk_lacms_agenda_item_agenda`
        FOREIGN KEY (`agenda_id`) REFERENCES `lacms_agendas` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_lacms_agenda_item_legislative`
        FOREIGN KEY (`legislative_item_id`) REFERENCES `legislative_items` (`id`)
        ON DELETE SET NULL,
    CONSTRAINT `fk_lacms_agenda_item_sponsor`
        FOREIGN KEY (`sponsor_user_id`) REFERENCES `users` (`id`)
        ON DELETE SET NULL,
    CONSTRAINT `fk_lacms_agenda_item_presenter`
        FOREIGN KEY (`presenter_user_id`) REFERENCES `users` (`id`)
        ON DELETE SET NULL,
    CONSTRAINT `fk_lacms_agenda_item_committee`
        FOREIGN KEY (`committee_id`) REFERENCES `committees` (`id`)
        ON DELETE SET NULL,
    CONSTRAINT `fk_lacms_agenda_item_created_by`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `lacms_agenda_documents` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `agenda_id` BIGINT NOT NULL,
    `agenda_item_id` BIGINT DEFAULT NULL,
    `file_name` VARCHAR(255) NOT NULL,
    `file_path` VARCHAR(500) NOT NULL,
    `document_type` VARCHAR(100) NOT NULL DEFAULT 'Agenda Document',
    `description` TEXT DEFAULT NULL,
    `visibility` VARCHAR(30) NOT NULL DEFAULT 'Internal',
    `uploaded_by` INT DEFAULT NULL,
    `uploaded_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_lacms_agenda_document_agenda` (`agenda_id`,`uploaded_at`),
    CONSTRAINT `fk_lacms_agenda_document_agenda`
        FOREIGN KEY (`agenda_id`) REFERENCES `lacms_agendas` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_lacms_agenda_document_item`
        FOREIGN KEY (`agenda_item_id`) REFERENCES `lacms_agenda_items` (`id`)
        ON DELETE SET NULL,
    CONSTRAINT `fk_lacms_agenda_document_user`
        FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `lacms_agenda_history` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `agenda_id` BIGINT NOT NULL,
    `action` VARCHAR(100) NOT NULL,
    `previous_status` VARCHAR(50) DEFAULT NULL,
    `new_status` VARCHAR(50) DEFAULT NULL,
    `details` TEXT DEFAULT NULL,
    `changed_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_lacms_agenda_history` (`agenda_id`,`created_at`),
    CONSTRAINT `fk_lacms_agenda_history_agenda`
        FOREIGN KEY (`agenda_id`) REFERENCES `lacms_agendas` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_lacms_agenda_history_user`
        FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------
-- CALENDAR SCHEDULING
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `lacms_calendar_events` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `event_reference` VARCHAR(80) NOT NULL,
    `agenda_id` BIGINT DEFAULT NULL,
    `legislative_item_id` INT DEFAULT NULL,
    `event_type` VARCHAR(100) NOT NULL DEFAULT 'Legislative Activity',
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `start_datetime` DATETIME NOT NULL,
    `end_datetime` DATETIME DEFAULT NULL,
    `all_day` TINYINT(1) NOT NULL DEFAULT 0,
    `venue` VARCHAR(255) DEFAULT NULL,
    `meeting_link` VARCHAR(500) DEFAULT NULL,
    `committee_id` INT DEFAULT NULL,
    `office_id` INT DEFAULT NULL,
    `status` VARCHAR(50) NOT NULL DEFAULT 'Tentative',
    `conflict_status` VARCHAR(50) NOT NULL DEFAULT 'Unchecked',
    `conflict_notes` TEXT DEFAULT NULL,
    `created_by` INT DEFAULT NULL,
    `confirmed_by` INT DEFAULT NULL,
    `confirmed_at` DATETIME DEFAULT NULL,
    `cancelled_at` DATETIME DEFAULT NULL,
    `cancellation_reason` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_lacms_event_reference` (`event_reference`),
    KEY `idx_lacms_event_schedule` (`start_datetime`,`end_datetime`,`status`),
    KEY `idx_lacms_event_committee` (`committee_id`,`start_datetime`),
    KEY `idx_lacms_event_agenda` (`agenda_id`),
    CONSTRAINT `fk_lacms_event_agenda`
        FOREIGN KEY (`agenda_id`) REFERENCES `lacms_agendas` (`id`)
        ON DELETE SET NULL,
    CONSTRAINT `fk_lacms_event_legislative`
        FOREIGN KEY (`legislative_item_id`) REFERENCES `legislative_items` (`id`)
        ON DELETE SET NULL,
    CONSTRAINT `fk_lacms_event_committee`
        FOREIGN KEY (`committee_id`) REFERENCES `committees` (`id`)
        ON DELETE SET NULL,
    CONSTRAINT `fk_lacms_event_office`
        FOREIGN KEY (`office_id`) REFERENCES `offices` (`id`)
        ON DELETE SET NULL,
    CONSTRAINT `fk_lacms_event_created_by`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL,
    CONSTRAINT `fk_lacms_event_confirmed_by`
        FOREIGN KEY (`confirmed_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `lacms_calendar_event_participants` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `calendar_event_id` BIGINT NOT NULL,
    `user_id` INT DEFAULT NULL,
    `external_name` VARCHAR(180) DEFAULT NULL,
    `external_email` VARCHAR(180) DEFAULT NULL,
    `participant_role` VARCHAR(100) DEFAULT 'Participant',
    `attendance_required` TINYINT(1) NOT NULL DEFAULT 1,
    `response_status` VARCHAR(40) NOT NULL DEFAULT 'Pending',
    `response_at` DATETIME DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_lacms_event_participant_event` (`calendar_event_id`),
    KEY `idx_lacms_event_participant_user` (`user_id`,`calendar_event_id`),
    CONSTRAINT `fk_lacms_event_participant_event`
        FOREIGN KEY (`calendar_event_id`) REFERENCES `lacms_calendar_events` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_lacms_event_participant_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `lacms_calendar_event_documents` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `calendar_event_id` BIGINT NOT NULL,
    `file_name` VARCHAR(255) NOT NULL,
    `file_path` VARCHAR(500) NOT NULL,
    `document_type` VARCHAR(100) NOT NULL DEFAULT 'Calendar Document',
    `description` TEXT DEFAULT NULL,
    `visibility` VARCHAR(30) NOT NULL DEFAULT 'Internal',
    `uploaded_by` INT DEFAULT NULL,
    `uploaded_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_lacms_event_document` (`calendar_event_id`,`uploaded_at`),
    CONSTRAINT `fk_lacms_event_document_event`
        FOREIGN KEY (`calendar_event_id`) REFERENCES `lacms_calendar_events` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_lacms_event_document_user`
        FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `lacms_calendar_history` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `calendar_event_id` BIGINT NOT NULL,
    `action` VARCHAR(100) NOT NULL,
    `previous_status` VARCHAR(50) DEFAULT NULL,
    `new_status` VARCHAR(50) DEFAULT NULL,
    `details` TEXT DEFAULT NULL,
    `changed_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_lacms_calendar_history` (`calendar_event_id`,`created_at`),
    CONSTRAINT `fk_lacms_calendar_history_event`
        FOREIGN KEY (`calendar_event_id`) REFERENCES `lacms_calendar_events` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_lacms_calendar_history_user`
        FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------
-- MEETING COORDINATION
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `lacms_meetings` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `meeting_reference` VARCHAR(80) NOT NULL,
    `calendar_event_id` BIGINT DEFAULT NULL,
    `agenda_id` BIGINT DEFAULT NULL,
    `committee_id` INT DEFAULT NULL,
    `office_id` INT DEFAULT NULL,
    `title` VARCHAR(255) NOT NULL,
    `meeting_type` VARCHAR(100) NOT NULL DEFAULT 'Committee Meeting',
    `purpose` TEXT DEFAULT NULL,
    `start_datetime` DATETIME NOT NULL,
    `end_datetime` DATETIME DEFAULT NULL,
    `venue` VARCHAR(255) DEFAULT NULL,
    `meeting_link` VARCHAR(500) DEFAULT NULL,
    `chair_user_id` INT DEFAULT NULL,
    `secretary_user_id` INT DEFAULT NULL,
    `status` VARCHAR(50) NOT NULL DEFAULT 'Planned',
    `coordination_notes` TEXT DEFAULT NULL,
    `created_by` INT DEFAULT NULL,
    `confirmed_by` INT DEFAULT NULL,
    `confirmed_at` DATETIME DEFAULT NULL,
    `completed_at` DATETIME DEFAULT NULL,
    `cancelled_at` DATETIME DEFAULT NULL,
    `cancellation_reason` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_lacms_meeting_reference` (`meeting_reference`),
    KEY `idx_lacms_meeting_schedule` (`start_datetime`,`status`),
    KEY `idx_lacms_meeting_agenda` (`agenda_id`),
    KEY `idx_lacms_meeting_committee` (`committee_id`,`start_datetime`),
    CONSTRAINT `fk_lacms_meeting_calendar_event`
        FOREIGN KEY (`calendar_event_id`) REFERENCES `lacms_calendar_events` (`id`)
        ON DELETE SET NULL,
    CONSTRAINT `fk_lacms_meeting_agenda`
        FOREIGN KEY (`agenda_id`) REFERENCES `lacms_agendas` (`id`)
        ON DELETE SET NULL,
    CONSTRAINT `fk_lacms_meeting_committee`
        FOREIGN KEY (`committee_id`) REFERENCES `committees` (`id`)
        ON DELETE SET NULL,
    CONSTRAINT `fk_lacms_meeting_office`
        FOREIGN KEY (`office_id`) REFERENCES `offices` (`id`)
        ON DELETE SET NULL,
    CONSTRAINT `fk_lacms_meeting_chair`
        FOREIGN KEY (`chair_user_id`) REFERENCES `users` (`id`)
        ON DELETE SET NULL,
    CONSTRAINT `fk_lacms_meeting_secretary`
        FOREIGN KEY (`secretary_user_id`) REFERENCES `users` (`id`)
        ON DELETE SET NULL,
    CONSTRAINT `fk_lacms_meeting_created_by`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL,
    CONSTRAINT `fk_lacms_meeting_confirmed_by`
        FOREIGN KEY (`confirmed_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `lacms_meeting_participants` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `meeting_id` BIGINT NOT NULL,
    `user_id` INT DEFAULT NULL,
    `external_name` VARCHAR(180) DEFAULT NULL,
    `external_email` VARCHAR(180) DEFAULT NULL,
    `participant_role` VARCHAR(100) DEFAULT 'Participant',
    `attendance_required` TINYINT(1) NOT NULL DEFAULT 1,
    `notification_status` VARCHAR(50) NOT NULL DEFAULT 'Pending',
    `response_status` VARCHAR(50) NOT NULL DEFAULT 'Pending',
    `attendance_status` VARCHAR(50) NOT NULL DEFAULT 'Not Recorded',
    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_lacms_meeting_participant_meeting` (`meeting_id`),
    KEY `idx_lacms_meeting_participant_user` (`user_id`,`meeting_id`),
    CONSTRAINT `fk_lacms_meeting_participant_meeting`
        FOREIGN KEY (`meeting_id`) REFERENCES `lacms_meetings` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_lacms_meeting_participant_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `lacms_meeting_documents` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `meeting_id` BIGINT NOT NULL,
    `file_name` VARCHAR(255) NOT NULL,
    `file_path` VARCHAR(500) NOT NULL,
    `document_type` VARCHAR(100) NOT NULL DEFAULT 'Meeting Document',
    `description` TEXT DEFAULT NULL,
    `visibility` VARCHAR(30) NOT NULL DEFAULT 'Internal',
    `uploaded_by` INT DEFAULT NULL,
    `uploaded_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_lacms_meeting_document` (`meeting_id`,`uploaded_at`),
    CONSTRAINT `fk_lacms_meeting_document_meeting`
        FOREIGN KEY (`meeting_id`) REFERENCES `lacms_meetings` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_lacms_meeting_document_user`
        FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `lacms_meeting_history` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `meeting_id` BIGINT NOT NULL,
    `action` VARCHAR(100) NOT NULL,
    `previous_status` VARCHAR(50) DEFAULT NULL,
    `new_status` VARCHAR(50) DEFAULT NULL,
    `details` TEXT DEFAULT NULL,
    `changed_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_lacms_meeting_history` (`meeting_id`,`created_at`),
    CONSTRAINT `fk_lacms_meeting_history_meeting`
        FOREIGN KEY (`meeting_id`) REFERENCES `lacms_meetings` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_lacms_meeting_history_user`
        FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------
-- DEADLINE TRACKING
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `lacms_deadlines` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `deadline_reference` VARCHAR(80) NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `deadline_type` VARCHAR(100) NOT NULL DEFAULT 'Legislative Task',
    `due_datetime` DATETIME NOT NULL,
    `priority_level` VARCHAR(50) NOT NULL DEFAULT 'Normal',
    `status` VARCHAR(50) NOT NULL DEFAULT 'Pending',
    `legislative_item_id` INT DEFAULT NULL,
    `agenda_id` BIGINT DEFAULT NULL,
    `calendar_event_id` BIGINT DEFAULT NULL,
    `meeting_id` BIGINT DEFAULT NULL,
    `committee_id` INT DEFAULT NULL,
    `office_id` INT DEFAULT NULL,
    `responsible_user_id` INT DEFAULT NULL,
    `reminder_policy` VARCHAR(100) DEFAULT 'Standard',
    `completion_notes` TEXT DEFAULT NULL,
    `completed_by` INT DEFAULT NULL,
    `completed_at` DATETIME DEFAULT NULL,
    `created_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_lacms_deadline_reference` (`deadline_reference`),
    KEY `idx_lacms_deadline_due_status` (`due_datetime`,`status`),
    KEY `idx_lacms_deadline_user` (`responsible_user_id`,`due_datetime`),
    KEY `idx_lacms_deadline_office` (`office_id`,`due_datetime`),
    CONSTRAINT `fk_lacms_deadline_legislative`
        FOREIGN KEY (`legislative_item_id`) REFERENCES `legislative_items` (`id`)
        ON DELETE SET NULL,
    CONSTRAINT `fk_lacms_deadline_agenda`
        FOREIGN KEY (`agenda_id`) REFERENCES `lacms_agendas` (`id`)
        ON DELETE SET NULL,
    CONSTRAINT `fk_lacms_deadline_event`
        FOREIGN KEY (`calendar_event_id`) REFERENCES `lacms_calendar_events` (`id`)
        ON DELETE SET NULL,
    CONSTRAINT `fk_lacms_deadline_meeting`
        FOREIGN KEY (`meeting_id`) REFERENCES `lacms_meetings` (`id`)
        ON DELETE SET NULL,
    CONSTRAINT `fk_lacms_deadline_committee`
        FOREIGN KEY (`committee_id`) REFERENCES `committees` (`id`)
        ON DELETE SET NULL,
    CONSTRAINT `fk_lacms_deadline_office`
        FOREIGN KEY (`office_id`) REFERENCES `offices` (`id`)
        ON DELETE SET NULL,
    CONSTRAINT `fk_lacms_deadline_responsible`
        FOREIGN KEY (`responsible_user_id`) REFERENCES `users` (`id`)
        ON DELETE SET NULL,
    CONSTRAINT `fk_lacms_deadline_completed_by`
        FOREIGN KEY (`completed_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL,
    CONSTRAINT `fk_lacms_deadline_created_by`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `lacms_deadline_assignments` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `deadline_id` BIGINT NOT NULL,
    `user_id` INT DEFAULT NULL,
    `office_id` INT DEFAULT NULL,
    `committee_id` INT DEFAULT NULL,
    `assignment_role` VARCHAR(100) DEFAULT 'Responsible',
    `status` VARCHAR(50) NOT NULL DEFAULT 'Assigned',
    `assigned_by` INT DEFAULT NULL,
    `assigned_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `acknowledged_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_lacms_deadline_assignment_deadline` (`deadline_id`),
    CONSTRAINT `fk_lacms_deadline_assignment_deadline`
        FOREIGN KEY (`deadline_id`) REFERENCES `lacms_deadlines` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_lacms_deadline_assignment_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE SET NULL,
    CONSTRAINT `fk_lacms_deadline_assignment_office`
        FOREIGN KEY (`office_id`) REFERENCES `offices` (`id`)
        ON DELETE SET NULL,
    CONSTRAINT `fk_lacms_deadline_assignment_committee`
        FOREIGN KEY (`committee_id`) REFERENCES `committees` (`id`)
        ON DELETE SET NULL,
    CONSTRAINT `fk_lacms_deadline_assignment_by`
        FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `lacms_deadline_reminders` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `deadline_id` BIGINT NOT NULL,
    `remind_at` DATETIME NOT NULL,
    `reminder_type` VARCHAR(80) NOT NULL DEFAULT 'System',
    `status` VARCHAR(50) NOT NULL DEFAULT 'Scheduled',
    `subject` VARCHAR(255) DEFAULT NULL,
    `message` TEXT DEFAULT NULL,
    `generated_by_ai` TINYINT(1) NOT NULL DEFAULT 0,
    `sent_at` DATETIME DEFAULT NULL,
    `failure_reason` TEXT DEFAULT NULL,
    `created_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_lacms_deadline_reminder_schedule` (`status`,`remind_at`),
    CONSTRAINT `fk_lacms_deadline_reminder_deadline`
        FOREIGN KEY (`deadline_id`) REFERENCES `lacms_deadlines` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_lacms_deadline_reminder_user`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `lacms_deadline_documents` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `deadline_id` BIGINT NOT NULL,
    `file_name` VARCHAR(255) NOT NULL,
    `file_path` VARCHAR(500) NOT NULL,
    `document_type` VARCHAR(100) NOT NULL DEFAULT 'Deadline Document',
    `description` TEXT DEFAULT NULL,
    `visibility` VARCHAR(30) NOT NULL DEFAULT 'Internal',
    `uploaded_by` INT DEFAULT NULL,
    `uploaded_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_lacms_deadline_document` (`deadline_id`,`uploaded_at`),
    CONSTRAINT `fk_lacms_deadline_document_deadline`
        FOREIGN KEY (`deadline_id`) REFERENCES `lacms_deadlines` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_lacms_deadline_document_user`
        FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `lacms_deadline_history` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `deadline_id` BIGINT NOT NULL,
    `action` VARCHAR(100) NOT NULL,
    `previous_status` VARCHAR(50) DEFAULT NULL,
    `new_status` VARCHAR(50) DEFAULT NULL,
    `details` TEXT DEFAULT NULL,
    `changed_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_lacms_deadline_history` (`deadline_id`,`created_at`),
    CONSTRAINT `fk_lacms_deadline_history_deadline`
        FOREIGN KEY (`deadline_id`) REFERENCES `lacms_deadlines` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_lacms_deadline_history_user`
        FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------
-- EXECUTIVE-LEGISLATIVE SYNCHRONIZATION SUPPORT
-- Kept as a supporting capability because it exists in the
-- current LACMS source/readme even though the revised dashboard
-- emphasizes the four primary operational modules.
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `lacms_sync_records` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `sync_reference` VARCHAR(80) NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `sync_type` VARCHAR(100) NOT NULL DEFAULT 'Policy Coordination',
    `legislative_item_id` INT DEFAULT NULL,
    `agenda_id` BIGINT DEFAULT NULL,
    `calendar_event_id` BIGINT DEFAULT NULL,
    `source_office_id` INT DEFAULT NULL,
    `target_committee_id` INT DEFAULT NULL,
    `priority_level` VARCHAR(50) NOT NULL DEFAULT 'Normal',
    `executive_position` TEXT DEFAULT NULL,
    `legislative_position` TEXT DEFAULT NULL,
    `status` VARCHAR(50) NOT NULL DEFAULT 'Draft',
    `resolution_notes` TEXT DEFAULT NULL,
    `created_by` INT DEFAULT NULL,
    `resolved_by` INT DEFAULT NULL,
    `resolved_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_lacms_sync_reference` (`sync_reference`),
    KEY `idx_lacms_sync_status` (`status`,`priority_level`),
    CONSTRAINT `fk_lacms_sync_legislative`
        FOREIGN KEY (`legislative_item_id`) REFERENCES `legislative_items` (`id`)
        ON DELETE SET NULL,
    CONSTRAINT `fk_lacms_sync_agenda`
        FOREIGN KEY (`agenda_id`) REFERENCES `lacms_agendas` (`id`)
        ON DELETE SET NULL,
    CONSTRAINT `fk_lacms_sync_event`
        FOREIGN KEY (`calendar_event_id`) REFERENCES `lacms_calendar_events` (`id`)
        ON DELETE SET NULL,
    CONSTRAINT `fk_lacms_sync_office`
        FOREIGN KEY (`source_office_id`) REFERENCES `offices` (`id`)
        ON DELETE SET NULL,
    CONSTRAINT `fk_lacms_sync_committee`
        FOREIGN KEY (`target_committee_id`) REFERENCES `committees` (`id`)
        ON DELETE SET NULL,
    CONSTRAINT `fk_lacms_sync_created_by`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL,
    CONSTRAINT `fk_lacms_sync_resolved_by`
        FOREIGN KEY (`resolved_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `lacms_sync_history` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `sync_record_id` BIGINT NOT NULL,
    `action` VARCHAR(100) NOT NULL,
    `previous_status` VARCHAR(50) DEFAULT NULL,
    `new_status` VARCHAR(50) DEFAULT NULL,
    `details` TEXT DEFAULT NULL,
    `changed_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_lacms_sync_history` (`sync_record_id`,`created_at`),
    CONSTRAINT `fk_lacms_sync_history_record`
        FOREIGN KEY (`sync_record_id`) REFERENCES `lacms_sync_records` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_lacms_sync_history_user`
        FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------
-- MEETING / DEADLINE NOTIFICATION QUEUE
-- This supports system/email workflows now and later Ollama-assisted
-- reminder drafting without making core scheduling depend on AI.
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `lacms_notifications` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `notification_reference` VARCHAR(80) NOT NULL,
    `notification_type` VARCHAR(100) NOT NULL,
    `source_type` VARCHAR(80) NOT NULL,
    `source_id` BIGINT NOT NULL,
    `subject` VARCHAR(255) NOT NULL,
    `message` TEXT NOT NULL,
    `scheduled_at` DATETIME DEFAULT NULL,
    `status` VARCHAR(50) NOT NULL DEFAULT 'Draft',
    `generated_by_ai` TINYINT(1) NOT NULL DEFAULT 0,
    `created_by` INT DEFAULT NULL,
    `sent_at` DATETIME DEFAULT NULL,
    `cancelled_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_lacms_notification_reference` (`notification_reference`),
    KEY `idx_lacms_notification_queue` (`status`,`scheduled_at`),
    KEY `idx_lacms_notification_source` (`source_type`,`source_id`),
    CONSTRAINT `fk_lacms_notification_created_by`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `lacms_notification_recipients` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `notification_id` BIGINT NOT NULL,
    `user_id` INT DEFAULT NULL,
    `recipient_name` VARCHAR(180) DEFAULT NULL,
    `recipient_email` VARCHAR(180) DEFAULT NULL,
    `delivery_channel` VARCHAR(50) NOT NULL DEFAULT 'System',
    `delivery_status` VARCHAR(50) NOT NULL DEFAULT 'Pending',
    `sent_at` DATETIME DEFAULT NULL,
    `acknowledged_at` DATETIME DEFAULT NULL,
    `failure_reason` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_lacms_notification_recipient` (`notification_id`,`delivery_status`),
    CONSTRAINT `fk_lacms_notification_recipient_parent`
        FOREIGN KEY (`notification_id`) REFERENCES `lacms_notifications` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_lacms_notification_recipient_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `lacms_notification_history` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `notification_id` BIGINT NOT NULL,
    `action` VARCHAR(100) NOT NULL,
    `previous_status` VARCHAR(50) DEFAULT NULL,
    `new_status` VARCHAR(50) DEFAULT NULL,
    `details` TEXT DEFAULT NULL,
    `changed_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_lacms_notification_history` (`notification_id`,`created_at`),
    CONSTRAINT `fk_lacms_notification_history_parent`
        FOREIGN KEY (`notification_id`) REFERENCES `lacms_notifications` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_lacms_notification_history_user`
        FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ------------------------------------------------------------
-- SHARED SYSTEM ALIGNMENT
-- ------------------------------------------------------------

UPDATE `systems`
SET
    `base_url`='http://localhost/lacms',
    `status`='Active',
    `updated_at`=NOW()
WHERE `code`='agenda';

INSERT INTO `lacms_schema_migrations` (`migration_key`,`description`)
VALUES (
    '001_lacms_foundation',
    'Creates the operational LACMS foundation for agendas, calendar scheduling, meetings, deadlines, synchronization support, notification/reminder workflows and audit history.'
)
ON DUPLICATE KEY UPDATE
    `description`=VALUES(`description`);

-- ------------------------------------------------------------
-- VERIFICATION
-- ------------------------------------------------------------

SELECT COUNT(*) AS lacms_table_count
FROM information_schema.tables
WHERE table_schema=DATABASE()
  AND table_name LIKE 'lacms\_%';

SELECT id,code,name,base_url,status
FROM systems
WHERE code='agenda';

SELECT migration_key,description,applied_at
FROM lacms_schema_migrations
ORDER BY applied_at,migration_key;
