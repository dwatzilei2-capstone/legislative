USE `legislative_management_db`;

CREATE TABLE IF NOT EXISTS `lacms_ai_analysis` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `source_type` VARCHAR(60) NOT NULL,
  `source_id` BIGINT NOT NULL,
  `reference_number` VARCHAR(100) NULL,
  `analysis_type` VARCHAR(80) NOT NULL,
  `model_used` VARCHAR(100) NOT NULL,
  `result_json` LONGTEXT NOT NULL,
  `review_status` VARCHAR(40) NOT NULL DEFAULT 'Advisory',
  `reviewed_by` INT NULL,
  `reviewed_at` DATETIME NULL,
  `created_by` INT NULL,
  `duration_ms` INT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_lacms_ai_record` (`source_type`,`source_id`,`created_at`),
  CONSTRAINT `fk_lacms_ai_created` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_lacms_ai_reviewed` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

SET @lacms_system_id := (SELECT id FROM systems WHERE code='agenda' LIMIT 1);
INSERT INTO permissions (system_id,code,name,description) VALUES
(@lacms_system_id,'lacms.ai.view','View LACMS Ollama Planning Assistant','View and run AI priority, calendar, and synchronization advisory analysis.'),
(@lacms_system_id,'lacms.ai.manage','Manage LACMS AI Recommendations','Apply human-reviewed LACMS AI recommendations to official records.')
ON DUPLICATE KEY UPDATE name=VALUES(name),description=VALUES(description);

INSERT IGNORE INTO role_permissions (role_id,permission_id)
SELECT r.id,p.id FROM roles r JOIN permissions p ON p.system_id=@lacms_system_id
WHERE r.name IN ('Administrator','Legislative Staff') AND p.code IN ('lacms.ai.view','lacms.ai.manage');
INSERT IGNORE INTO role_permissions (role_id,permission_id)
SELECT r.id,p.id FROM roles r JOIN permissions p ON p.system_id=@lacms_system_id
WHERE r.name='Committee Member' AND p.code='lacms.ai.view';

INSERT INTO lacms_schema_migrations (migration_key,description)
VALUES ('005_ollama_planning_ai','Adds audited Ollama priority, calendar and executive-legislative synchronization decision support.')
ON DUPLICATE KEY UPDATE description=VALUES(description);
