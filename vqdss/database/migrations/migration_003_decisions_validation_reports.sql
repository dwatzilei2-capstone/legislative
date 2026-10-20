USE `legislative_management_db`;

-- ============================================================
-- VQDSS — STEPS 5, 6 AND 7
-- Decision Recording, Result Validation and Reporting
-- ============================================================

CREATE TABLE IF NOT EXISTS `vqd_decision_action_history` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `decision_action_id` BIGINT NOT NULL,
    `previous_status` VARCHAR(50) DEFAULT NULL,
    `new_status` VARCHAR(50) DEFAULT NULL,
    `details` TEXT DEFAULT NULL,
    `changed_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_vqd_decision_action_history_action`
        (`decision_action_id`,`created_at`),
    CONSTRAINT `fk_vqd_decision_action_history_action`
        FOREIGN KEY (`decision_action_id`)
        REFERENCES `vqd_decision_actions` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_vqd_decision_action_history_user`
        FOREIGN KEY (`changed_by`)
        REFERENCES `users` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- One certified tally should create only one primary decision record.
-- Multiple NULL values remain allowed by MySQL/MariaDB unique indexes.
SET @has_decision_tally_uq := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema=DATABASE()
      AND table_name='vqd_decisions'
      AND index_name='uq_vqd_decision_tally'
);

SET @sql := IF(
    @has_decision_tally_uq=0,
    'ALTER TABLE `vqd_decisions` ADD UNIQUE KEY `uq_vqd_decision_tally` (`tally_id`)',
    'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

INSERT INTO `vqd_schema_migrations` (`migration_key`,`description`)
VALUES (
    '003_decisions_validation_reports',
    'Completes decision action audit history and primary tally-to-decision integrity for Decision Recording, Validation and consolidated reporting.'
)
ON DUPLICATE KEY UPDATE
    `description`=VALUES(`description`);

SELECT
    EXISTS(
        SELECT 1
        FROM information_schema.tables
        WHERE table_schema=DATABASE()
          AND table_name='vqd_decision_action_history'
    ) AS decision_action_history_ok,
    EXISTS(
        SELECT 1
        FROM information_schema.statistics
        WHERE table_schema=DATABASE()
          AND table_name='vqd_decisions'
          AND index_name='uq_vqd_decision_tally'
    ) AS decision_tally_unique_ok,
    EXISTS(
        SELECT 1
        FROM information_schema.tables
        WHERE table_schema=DATABASE()
          AND table_name='vqd_result_validations'
    ) AS validation_foundation_ok;
