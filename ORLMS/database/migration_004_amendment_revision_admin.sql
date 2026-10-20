USE `legislative_management_db`;

-- ============================================================
-- ORLMS - STEPS 8, 9 AND 10
-- Amendment & Revision Tracking foundation plus final
-- operational history/evidence support for this batch.
-- ============================================================

ALTER TABLE `orlms_amendments`
    ADD COLUMN IF NOT EXISTS `source_revision_version` INT DEFAULT NULL
        AFTER `legislative_item_id`,
    ADD COLUMN IF NOT EXISTS `source_status` VARCHAR(80) DEFAULT NULL
        AFTER `source_revision_version`,
    ADD COLUMN IF NOT EXISTS `related_legislative_item_id` INT DEFAULT NULL
        AFTER `amendment_type`,
    ADD COLUMN IF NOT EXISTS `relationship_type` VARCHAR(80) DEFAULT NULL
        AFTER `related_legislative_item_id`,
    ADD COLUMN IF NOT EXISTS `revised_body_text` LONGTEXT DEFAULT NULL
        AFTER `rationale`,
    ADD COLUMN IF NOT EXISTS `review_notes` LONGTEXT DEFAULT NULL
        AFTER `status`,
    ADD COLUMN IF NOT EXISTS `reviewed_by` INT DEFAULT NULL
        AFTER `review_notes`,
    ADD COLUMN IF NOT EXISTS `reviewed_at` DATETIME DEFAULT NULL
        AFTER `reviewed_by`,
    ADD COLUMN IF NOT EXISTS `approval_remarks` LONGTEXT DEFAULT NULL
        AFTER `approved_at`,
    ADD COLUMN IF NOT EXISTS `applied_by` INT DEFAULT NULL
        AFTER `effective_date`,
    ADD COLUMN IF NOT EXISTS `applied_at` DATETIME DEFAULT NULL
        AFTER `applied_by`;

SET @fk_related_item := (
    SELECT COUNT(*)
    FROM information_schema.table_constraints
    WHERE constraint_schema=DATABASE()
      AND table_name='orlms_amendments'
      AND constraint_name='fk_orlms_amendment_related_item'
);
SET @sql := IF(
    @fk_related_item=0,
    'ALTER TABLE `orlms_amendments`
       ADD CONSTRAINT `fk_orlms_amendment_related_item`
       FOREIGN KEY (`related_legislative_item_id`) REFERENCES `legislative_items` (`id`)
       ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @fk_reviewed_by := (
    SELECT COUNT(*)
    FROM information_schema.table_constraints
    WHERE constraint_schema=DATABASE()
      AND table_name='orlms_amendments'
      AND constraint_name='fk_orlms_amendment_reviewed_by'
);
SET @sql := IF(
    @fk_reviewed_by=0,
    'ALTER TABLE `orlms_amendments`
       ADD CONSTRAINT `fk_orlms_amendment_reviewed_by`
       FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`)
       ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @fk_applied_by := (
    SELECT COUNT(*)
    FROM information_schema.table_constraints
    WHERE constraint_schema=DATABASE()
      AND table_name='orlms_amendments'
      AND constraint_name='fk_orlms_amendment_applied_by'
);
SET @sql := IF(
    @fk_applied_by=0,
    'ALTER TABLE `orlms_amendments`
       ADD CONSTRAINT `fk_orlms_amendment_applied_by`
       FOREIGN KEY (`applied_by`) REFERENCES `users` (`id`)
       ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS `orlms_amendment_history` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `amendment_id` BIGINT NOT NULL,
    `legislative_item_id` INT NOT NULL,
    `action` VARCHAR(100) NOT NULL,
    `previous_status` VARCHAR(80) DEFAULT NULL,
    `new_status` VARCHAR(80) DEFAULT NULL,
    `details` TEXT DEFAULT NULL,
    `changed_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_orlms_amendment_history_amendment` (`amendment_id`,`created_at`),
    KEY `idx_orlms_amendment_history_item` (`legislative_item_id`,`created_at`),
    CONSTRAINT `fk_orlms_amendment_history_parent`
        FOREIGN KEY (`amendment_id`) REFERENCES `orlms_amendments` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_orlms_amendment_history_item`
        FOREIGN KEY (`legislative_item_id`) REFERENCES `legislative_items` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_orlms_amendment_history_user`
        FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `orlms_amendment_documents` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `amendment_id` BIGINT NOT NULL,
    `file_name` VARCHAR(255) NOT NULL,
    `file_path` VARCHAR(500) NOT NULL,
    `document_type` VARCHAR(100) NOT NULL DEFAULT 'Amendment Evidence',
    `description` TEXT DEFAULT NULL,
    `visibility` VARCHAR(30) NOT NULL DEFAULT 'Internal',
    `uploaded_by` INT DEFAULT NULL,
    `uploaded_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_orlms_amendment_document` (`amendment_id`,`uploaded_at`),
    CONSTRAINT `fk_orlms_amendment_document_parent`
        FOREIGN KEY (`amendment_id`) REFERENCES `orlms_amendments` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_orlms_amendment_document_user`
        FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create a baseline official revision snapshot for every existing ORLMS
-- ordinance/resolution that does not yet have revision history.
INSERT INTO `orlms_revision_history`
    (`legislative_item_id`,`amendment_id`,`version_number`,`title_snapshot`,
     `body_snapshot`,`change_summary`,`is_current`,`created_by`,`created_at`)
SELECT
    li.id,NULL,1,li.title,d.body_text,
    'Baseline official revision snapshot created by ORLMS migration 004.',
    1,NULL,NOW()
FROM legislative_items li
JOIN legislative_item_types lit ON lit.id=li.item_type_id
LEFT JOIN orlms_item_details d ON d.legislative_item_id=li.id
WHERE lit.code IN ('ordinance','resolution')
  AND li.deleted_at IS NULL
  AND NOT EXISTS(
      SELECT 1
      FROM orlms_revision_history rh
      WHERE rh.legislative_item_id=li.id
  );

INSERT INTO `orlms_schema_migrations` (`migration_key`,`description`)
VALUES (
    '004_amendment_revision_admin',
    'Completes amendment/revision source tracking, relationship linking, amendment audit/evidence, baseline official revision history, dashboard/report/admin support.'
)
ON DUPLICATE KEY UPDATE
    `description`=VALUES(`description`);

SELECT
    EXISTS(
      SELECT 1 FROM information_schema.tables
      WHERE table_schema=DATABASE()
        AND table_name='orlms_amendment_history'
    ) AS amendment_history_ok,
    EXISTS(
      SELECT 1 FROM information_schema.tables
      WHERE table_schema=DATABASE()
        AND table_name='orlms_amendment_documents'
    ) AS amendment_documents_ok,
    EXISTS(
      SELECT 1 FROM information_schema.columns
      WHERE table_schema=DATABASE()
        AND table_name='orlms_amendments'
        AND column_name='revised_body_text'
    ) AS revised_body_ok,
    (SELECT COUNT(*) FROM orlms_revision_history) AS revision_snapshot_count;
