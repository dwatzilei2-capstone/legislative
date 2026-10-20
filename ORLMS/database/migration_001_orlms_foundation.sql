USE `legislative_management_db`;

-- ============================================================
-- ORLMS SUBSYSTEM #1 — DATABASE FOUNDATION
-- Ordinance and Resolution Life Cycle Management System
-- ============================================================

CREATE TABLE IF NOT EXISTS `orlms_schema_migrations` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `migration_key` VARCHAR(150) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `applied_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_orlms_migration_key` (`migration_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `orlms_templates` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `item_type_id` INT NOT NULL,
    `template_code` VARCHAR(100) NOT NULL,
    `template_name` VARCHAR(200) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `template_body` LONGTEXT DEFAULT NULL,
    `status` VARCHAR(30) NOT NULL DEFAULT 'Active',
    `created_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_orlms_template_code` (`template_code`),
    KEY `idx_orlms_template_type_status` (`item_type_id`,`status`),
    CONSTRAINT `fk_orlms_template_type`
        FOREIGN KEY (`item_type_id`) REFERENCES `legislative_item_types` (`id`)
        ON DELETE RESTRICT,
    CONSTRAINT `fk_orlms_template_created_by`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `orlms_item_details` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `legislative_item_id` INT NOT NULL,
    `template_id` BIGINT DEFAULT NULL,
    `short_title` VARCHAR(255) DEFAULT NULL,
    `subject` VARCHAR(255) DEFAULT NULL,
    `legislative_category` VARCHAR(150) DEFAULT NULL,
    `policy_area` VARCHAR(150) DEFAULT NULL,
    `purpose` TEXT DEFAULT NULL,
    `explanatory_note` LONGTEXT DEFAULT NULL,
    `body_text` LONGTEXT DEFAULT NULL,
    `legal_basis` TEXT DEFAULT NULL,
    `fiscal_impact` TEXT DEFAULT NULL,
    `keywords` TEXT DEFAULT NULL,
    `draft_status` VARCHAR(50) NOT NULL DEFAULT 'Draft',
    `submitted_by` INT DEFAULT NULL,
    `submitted_at` DATETIME DEFAULT NULL,
    `returned_at` DATETIME DEFAULT NULL,
    `return_reason` TEXT DEFAULT NULL,
    `last_edited_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_orlms_item_details_item` (`legislative_item_id`),
    KEY `idx_orlms_item_details_status` (`draft_status`,`updated_at`),
    KEY `idx_orlms_item_details_category` (`legislative_category`,`policy_area`),
    CONSTRAINT `fk_orlms_item_details_item`
        FOREIGN KEY (`legislative_item_id`) REFERENCES `legislative_items` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_orlms_item_details_template`
        FOREIGN KEY (`template_id`) REFERENCES `orlms_templates` (`id`)
        ON DELETE SET NULL,
    CONSTRAINT `fk_orlms_item_details_submitted_by`
        FOREIGN KEY (`submitted_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL,
    CONSTRAINT `fk_orlms_item_details_last_editor`
        FOREIGN KEY (`last_edited_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `orlms_item_authors` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `legislative_item_id` INT NOT NULL,
    `user_id` INT DEFAULT NULL,
    `office_id` INT DEFAULT NULL,
    `author_name` VARCHAR(200) NOT NULL,
    `author_type` VARCHAR(50) NOT NULL DEFAULT 'Author',
    `sequence_number` INT NOT NULL DEFAULT 1,
    `created_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_orlms_author_item` (`legislative_item_id`,`sequence_number`),
    KEY `idx_orlms_author_user` (`user_id`),
    CONSTRAINT `fk_orlms_author_item`
        FOREIGN KEY (`legislative_item_id`) REFERENCES `legislative_items` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_orlms_author_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE SET NULL,
    CONSTRAINT `fk_orlms_author_office`
        FOREIGN KEY (`office_id`) REFERENCES `offices` (`id`)
        ON DELETE SET NULL,
    CONSTRAINT `fk_orlms_author_created_by`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `orlms_draft_versions` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `legislative_item_id` INT NOT NULL,
    `version_number` INT NOT NULL,
    `title_snapshot` VARCHAR(255) NOT NULL,
    `summary_snapshot` TEXT DEFAULT NULL,
    `body_snapshot` LONGTEXT DEFAULT NULL,
    `explanatory_note_snapshot` LONGTEXT DEFAULT NULL,
    `change_summary` TEXT DEFAULT NULL,
    `created_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_orlms_draft_version` (`legislative_item_id`,`version_number`),
    KEY `idx_orlms_draft_version_date` (`legislative_item_id`,`created_at`),
    CONSTRAINT `fk_orlms_draft_version_item`
        FOREIGN KEY (`legislative_item_id`) REFERENCES `legislative_items` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_orlms_draft_version_user`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `orlms_workflow_history` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `legislative_item_id` INT NOT NULL,
    `stage_code` VARCHAR(80) NOT NULL,
    `action` VARCHAR(100) NOT NULL,
    `previous_status` VARCHAR(100) DEFAULT NULL,
    `new_status` VARCHAR(100) DEFAULT NULL,
    `from_committee_id` INT DEFAULT NULL,
    `to_committee_id` INT DEFAULT NULL,
    `from_office_id` INT DEFAULT NULL,
    `to_office_id` INT DEFAULT NULL,
    `assigned_user_id` INT DEFAULT NULL,
    `remarks` TEXT DEFAULT NULL,
    `acted_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_orlms_workflow_item_date` (`legislative_item_id`,`created_at`),
    KEY `idx_orlms_workflow_stage` (`stage_code`,`new_status`),
    CONSTRAINT `fk_orlms_workflow_item`
        FOREIGN KEY (`legislative_item_id`) REFERENCES `legislative_items` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_orlms_workflow_from_committee`
        FOREIGN KEY (`from_committee_id`) REFERENCES `committees` (`id`)
        ON DELETE SET NULL,
    CONSTRAINT `fk_orlms_workflow_to_committee`
        FOREIGN KEY (`to_committee_id`) REFERENCES `committees` (`id`)
        ON DELETE SET NULL,
    CONSTRAINT `fk_orlms_workflow_from_office`
        FOREIGN KEY (`from_office_id`) REFERENCES `offices` (`id`)
        ON DELETE SET NULL,
    CONSTRAINT `fk_orlms_workflow_to_office`
        FOREIGN KEY (`to_office_id`) REFERENCES `offices` (`id`)
        ON DELETE SET NULL,
    CONSTRAINT `fk_orlms_workflow_assigned_user`
        FOREIGN KEY (`assigned_user_id`) REFERENCES `users` (`id`)
        ON DELETE SET NULL,
    CONSTRAINT `fk_orlms_workflow_actor`
        FOREIGN KEY (`acted_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `orlms_reviews` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `legislative_item_id` INT NOT NULL,
    `committee_id` INT DEFAULT NULL,
    `reviewer_id` INT DEFAULT NULL,
    `review_round` INT NOT NULL DEFAULT 1,
    `review_type` VARCHAR(80) NOT NULL DEFAULT 'Committee Review',
    `recommendation` VARCHAR(100) DEFAULT NULL,
    `comments` LONGTEXT DEFAULT NULL,
    `status` VARCHAR(50) NOT NULL DEFAULT 'Pending',
    `started_at` DATETIME DEFAULT NULL,
    `completed_at` DATETIME DEFAULT NULL,
    `created_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_orlms_review_item` (`legislative_item_id`,`status`),
    KEY `idx_orlms_review_committee` (`committee_id`,`status`),
    CONSTRAINT `fk_orlms_review_item`
        FOREIGN KEY (`legislative_item_id`) REFERENCES `legislative_items` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_orlms_review_committee`
        FOREIGN KEY (`committee_id`) REFERENCES `committees` (`id`)
        ON DELETE SET NULL,
    CONSTRAINT `fk_orlms_review_reviewer`
        FOREIGN KEY (`reviewer_id`) REFERENCES `users` (`id`)
        ON DELETE SET NULL,
    CONSTRAINT `fk_orlms_review_created_by`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `orlms_endorsements` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `legislative_item_id` INT NOT NULL,
    `review_id` BIGINT DEFAULT NULL,
    `committee_id` INT DEFAULT NULL,
    `endorsement_status` VARCHAR(50) NOT NULL DEFAULT 'Pending',
    `recommendation` VARCHAR(150) DEFAULT NULL,
    `vote_summary` VARCHAR(255) DEFAULT NULL,
    `remarks` TEXT DEFAULT NULL,
    `endorsed_by` INT DEFAULT NULL,
    `endorsed_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_orlms_endorsement_item` (`legislative_item_id`,`endorsement_status`),
    CONSTRAINT `fk_orlms_endorsement_item`
        FOREIGN KEY (`legislative_item_id`) REFERENCES `legislative_items` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_orlms_endorsement_review`
        FOREIGN KEY (`review_id`) REFERENCES `orlms_reviews` (`id`)
        ON DELETE SET NULL,
    CONSTRAINT `fk_orlms_endorsement_committee`
        FOREIGN KEY (`committee_id`) REFERENCES `committees` (`id`)
        ON DELETE SET NULL,
    CONSTRAINT `fk_orlms_endorsement_user`
        FOREIGN KEY (`endorsed_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `orlms_approval_actions` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `legislative_item_id` INT NOT NULL,
    `action_reference` VARCHAR(100) NOT NULL,
    `action_type` VARCHAR(80) NOT NULL,
    `action_status` VARCHAR(50) NOT NULL DEFAULT 'Pending',
    `authority_user_id` INT DEFAULT NULL,
    `authority_office_id` INT DEFAULT NULL,
    `action_date` DATETIME DEFAULT NULL,
    `remarks` TEXT DEFAULT NULL,
    `created_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_orlms_approval_reference` (`action_reference`),
    KEY `idx_orlms_approval_item` (`legislative_item_id`,`action_status`),
    CONSTRAINT `fk_orlms_approval_item`
        FOREIGN KEY (`legislative_item_id`) REFERENCES `legislative_items` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_orlms_approval_user`
        FOREIGN KEY (`authority_user_id`) REFERENCES `users` (`id`)
        ON DELETE SET NULL,
    CONSTRAINT `fk_orlms_approval_office`
        FOREIGN KEY (`authority_office_id`) REFERENCES `offices` (`id`)
        ON DELETE SET NULL,
    CONSTRAINT `fk_orlms_approval_created_by`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `orlms_enactments` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `legislative_item_id` INT NOT NULL,
    `enactment_reference` VARCHAR(100) NOT NULL,
    `official_number` VARCHAR(100) DEFAULT NULL,
    `enactment_type` VARCHAR(80) NOT NULL DEFAULT 'Enactment',
    `approved_date` DATE DEFAULT NULL,
    `signed_date` DATE DEFAULT NULL,
    `enactment_date` DATE DEFAULT NULL,
    `signing_authority` VARCHAR(200) DEFAULT NULL,
    `effectivity_type` VARCHAR(80) DEFAULT NULL,
    `effectivity_date` DATE DEFAULT NULL,
    `remarks` TEXT DEFAULT NULL,
    `recorded_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_orlms_enactment_item` (`legislative_item_id`),
    UNIQUE KEY `uq_orlms_enactment_reference` (`enactment_reference`),
    KEY `idx_orlms_enactment_date` (`enactment_date`),
    CONSTRAINT `fk_orlms_enactment_item`
        FOREIGN KEY (`legislative_item_id`) REFERENCES `legislative_items` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_orlms_enactment_recorded_by`
        FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `orlms_publications` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `legislative_item_id` INT NOT NULL,
    `publication_reference` VARCHAR(100) NOT NULL,
    `publication_channel` VARCHAR(100) NOT NULL DEFAULT 'Official Publication',
    `publication_date` DATETIME DEFAULT NULL,
    `publication_status` VARCHAR(50) NOT NULL DEFAULT 'Draft',
    `release_classification` VARCHAR(30) NOT NULL DEFAULT 'Public',
    `public_url` VARCHAR(500) DEFAULT NULL,
    `publication_text` LONGTEXT DEFAULT NULL,
    `remarks` TEXT DEFAULT NULL,
    `published_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_orlms_publication_reference` (`publication_reference`),
    KEY `idx_orlms_publication_item` (`legislative_item_id`,`publication_status`),
    KEY `idx_orlms_publication_date` (`publication_date`),
    CONSTRAINT `fk_orlms_publication_item`
        FOREIGN KEY (`legislative_item_id`) REFERENCES `legislative_items` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_orlms_publication_user`
        FOREIGN KEY (`published_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `orlms_dissemination_recipients` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `publication_id` BIGINT NOT NULL,
    `recipient_type` VARCHAR(50) NOT NULL DEFAULT 'Office',
    `office_id` INT DEFAULT NULL,
    `user_id` INT DEFAULT NULL,
    `recipient_name` VARCHAR(200) DEFAULT NULL,
    `recipient_email` VARCHAR(200) DEFAULT NULL,
    `delivery_channel` VARCHAR(80) DEFAULT 'System Notification',
    `delivery_status` VARCHAR(50) NOT NULL DEFAULT 'Pending',
    `sent_at` DATETIME DEFAULT NULL,
    `acknowledged_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_orlms_dissemination_pub` (`publication_id`,`delivery_status`),
    CONSTRAINT `fk_orlms_dissemination_publication`
        FOREIGN KEY (`publication_id`) REFERENCES `orlms_publications` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_orlms_dissemination_office`
        FOREIGN KEY (`office_id`) REFERENCES `offices` (`id`)
        ON DELETE SET NULL,
    CONSTRAINT `fk_orlms_dissemination_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `orlms_implementation_plans` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `legislative_item_id` INT NOT NULL,
    `plan_reference` VARCHAR(100) NOT NULL,
    `objective` TEXT NOT NULL,
    `responsible_office_id` INT DEFAULT NULL,
    `responsible_user_id` INT DEFAULT NULL,
    `start_date` DATE DEFAULT NULL,
    `due_date` DATE DEFAULT NULL,
    `status` VARCHAR(50) NOT NULL DEFAULT 'Pending',
    `compliance_percent` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `completion_notes` TEXT DEFAULT NULL,
    `completed_at` DATETIME DEFAULT NULL,
    `created_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_orlms_plan_reference` (`plan_reference`),
    KEY `idx_orlms_plan_item` (`legislative_item_id`,`status`),
    KEY `idx_orlms_plan_due` (`due_date`,`status`),
    CONSTRAINT `fk_orlms_plan_item`
        FOREIGN KEY (`legislative_item_id`) REFERENCES `legislative_items` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_orlms_plan_office`
        FOREIGN KEY (`responsible_office_id`) REFERENCES `offices` (`id`)
        ON DELETE SET NULL,
    CONSTRAINT `fk_orlms_plan_user`
        FOREIGN KEY (`responsible_user_id`) REFERENCES `users` (`id`)
        ON DELETE SET NULL,
    CONSTRAINT `fk_orlms_plan_created_by`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `orlms_implementation_updates` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `implementation_plan_id` BIGINT NOT NULL,
    `update_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `progress_percent` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `status` VARCHAR(50) NOT NULL DEFAULT 'In Progress',
    `update_text` LONGTEXT NOT NULL,
    `issues_encountered` TEXT DEFAULT NULL,
    `next_steps` TEXT DEFAULT NULL,
    `evidence_summary` TEXT DEFAULT NULL,
    `updated_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_orlms_impl_update_plan` (`implementation_plan_id`,`update_date`),
    CONSTRAINT `fk_orlms_impl_update_plan`
        FOREIGN KEY (`implementation_plan_id`) REFERENCES `orlms_implementation_plans` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_orlms_impl_update_user`
        FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `orlms_amendments` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `legislative_item_id` INT NOT NULL,
    `amendment_reference` VARCHAR(100) NOT NULL,
    `amendment_type` VARCHAR(80) NOT NULL DEFAULT 'Amendment',
    `title` VARCHAR(255) NOT NULL,
    `summary` TEXT DEFAULT NULL,
    `rationale` LONGTEXT DEFAULT NULL,
    `status` VARCHAR(50) NOT NULL DEFAULT 'Draft',
    `proposed_by` INT DEFAULT NULL,
    `proposed_at` DATETIME DEFAULT NULL,
    `approved_by` INT DEFAULT NULL,
    `approved_at` DATETIME DEFAULT NULL,
    `effective_date` DATE DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_orlms_amendment_reference` (`amendment_reference`),
    KEY `idx_orlms_amendment_item` (`legislative_item_id`,`status`),
    CONSTRAINT `fk_orlms_amendment_item`
        FOREIGN KEY (`legislative_item_id`) REFERENCES `legislative_items` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_orlms_amendment_proposed_by`
        FOREIGN KEY (`proposed_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL,
    CONSTRAINT `fk_orlms_amendment_approved_by`
        FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `orlms_amendment_changes` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `amendment_id` BIGINT NOT NULL,
    `section_reference` VARCHAR(150) DEFAULT NULL,
    `change_type` VARCHAR(50) NOT NULL DEFAULT 'Modify',
    `previous_text` LONGTEXT DEFAULT NULL,
    `new_text` LONGTEXT DEFAULT NULL,
    `rationale` TEXT DEFAULT NULL,
    `sequence_number` INT NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_orlms_amendment_changes` (`amendment_id`,`sequence_number`),
    CONSTRAINT `fk_orlms_amendment_change_parent`
        FOREIGN KEY (`amendment_id`) REFERENCES `orlms_amendments` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `orlms_revision_history` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `legislative_item_id` INT NOT NULL,
    `amendment_id` BIGINT DEFAULT NULL,
    `version_number` INT NOT NULL,
    `title_snapshot` VARCHAR(255) NOT NULL,
    `body_snapshot` LONGTEXT DEFAULT NULL,
    `change_summary` TEXT DEFAULT NULL,
    `is_current` TINYINT(1) NOT NULL DEFAULT 0,
    `created_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_orlms_revision_version` (`legislative_item_id`,`version_number`),
    KEY `idx_orlms_revision_current` (`legislative_item_id`,`is_current`),
    CONSTRAINT `fk_orlms_revision_item`
        FOREIGN KEY (`legislative_item_id`) REFERENCES `legislative_items` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_orlms_revision_amendment`
        FOREIGN KEY (`amendment_id`) REFERENCES `orlms_amendments` (`id`)
        ON DELETE SET NULL,
    CONSTRAINT `fk_orlms_revision_user`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `orlms_ai_classifications` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `legislative_item_id` INT NOT NULL,
    `draft_version_id` BIGINT DEFAULT NULL,
    `provider` VARCHAR(80) NOT NULL DEFAULT 'Ollama',
    `model_name` VARCHAR(150) DEFAULT NULL,
    `classification_label` VARCHAR(150) DEFAULT NULL,
    `policy_area` VARCHAR(150) DEFAULT NULL,
    `tags_json` LONGTEXT DEFAULT NULL,
    `confidence_score` DECIMAL(6,4) DEFAULT NULL,
    `rationale` TEXT DEFAULT NULL,
    `raw_response` LONGTEXT DEFAULT NULL,
    `status` VARCHAR(50) NOT NULL DEFAULT 'Pending',
    `requested_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_orlms_ai_item` (`legislative_item_id`,`created_at`),
    CONSTRAINT `fk_orlms_ai_item`
        FOREIGN KEY (`legislative_item_id`) REFERENCES `legislative_items` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_orlms_ai_version`
        FOREIGN KEY (`draft_version_id`) REFERENCES `orlms_draft_versions` (`id`)
        ON DELETE SET NULL,
    CONSTRAINT `fk_orlms_ai_user`
        FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Ensure shared system registration is aligned with the actual ORLMS URL.
UPDATE `systems`
SET `name`='Ordinance and Resolution Life Cycle Management System',
    `description`='Manages ordinances and resolutions from drafting through implementation and revision.',
    `base_url`='http://localhost/orlms',
    `status`='Active',
    `updated_at`=NOW()
WHERE `code`='ordinance';

-- Seed two safe drafting templates using existing shared item types.
INSERT INTO `orlms_templates`
    (`item_type_id`,`template_code`,`template_name`,`description`,`template_body`,`status`,`created_by`,`created_at`,`updated_at`)
SELECT
    lit.id,
    'ORD-STANDARD',
    'Standard Ordinance Draft',
    'Baseline ordinance structure for the ORLMS drafting workspace.',
    'TITLE\n\nBE IT ORDAINED BY THE SANGGUNIANG PANLUNGSOD...\n\nSECTION 1. Title.\nSECTION 2. Declaration of Policy / Purpose.\nSECTION 3. Provisions.\nSECTION 4. Implementing Responsibility.\nSECTION 5. Separability Clause.\nSECTION 6. Repealing Clause.\nSECTION 7. Effectivity.',
    'Active',
    NULL,
    NOW(),
    NOW()
FROM `legislative_item_types` lit
WHERE lit.code='ordinance'
  AND NOT EXISTS(
      SELECT 1 FROM `orlms_templates` t WHERE t.template_code='ORD-STANDARD'
  );

INSERT INTO `orlms_templates`
    (`item_type_id`,`template_code`,`template_name`,`description`,`template_body`,`status`,`created_by`,`created_at`,`updated_at`)
SELECT
    lit.id,
    'RES-STANDARD',
    'Standard Resolution Draft',
    'Baseline resolution structure for the ORLMS drafting and submission workspace.',
    'TITLE\n\nWHEREAS, ...;\n\nWHEREAS, ...;\n\nNOW, THEREFORE, BE IT RESOLVED...\n\nRESOLVED FURTHER, ...',
    'Active',
    NULL,
    NOW(),
    NOW()
FROM `legislative_item_types` lit
WHERE lit.code='resolution'
  AND NOT EXISTS(
      SELECT 1 FROM `orlms_templates` t WHERE t.template_code='RES-STANDARD'
  );

INSERT INTO `orlms_schema_migrations` (`migration_key`,`description`)
VALUES (
    '001_orlms_foundation',
    'Creates the ORLMS operational foundation for drafting, review, approval, publication, implementation, amendment/revision tracking and future Ollama classification.'
)
ON DUPLICATE KEY UPDATE
    `description`=VALUES(`description`);

SELECT
    (SELECT COUNT(*) FROM information_schema.tables
      WHERE table_schema=DATABASE() AND table_name LIKE 'orlms_%') AS orlms_table_count,
    EXISTS(SELECT 1 FROM information_schema.tables
      WHERE table_schema=DATABASE() AND table_name='orlms_item_details') AS drafting_foundation_ok,
    EXISTS(SELECT 1 FROM information_schema.tables
      WHERE table_schema=DATABASE() AND table_name='orlms_reviews') AS review_foundation_ok,
    EXISTS(SELECT 1 FROM information_schema.tables
      WHERE table_schema=DATABASE() AND table_name='orlms_enactments') AS enactment_foundation_ok,
    EXISTS(SELECT 1 FROM information_schema.tables
      WHERE table_schema=DATABASE() AND table_name='orlms_publications') AS publication_foundation_ok,
    EXISTS(SELECT 1 FROM information_schema.tables
      WHERE table_schema=DATABASE() AND table_name='orlms_implementation_plans') AS implementation_foundation_ok,
    EXISTS(SELECT 1 FROM information_schema.tables
      WHERE table_schema=DATABASE() AND table_name='orlms_amendments') AS amendment_foundation_ok;
