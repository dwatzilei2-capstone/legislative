USE `legislative_management_db`;

CREATE TABLE IF NOT EXISTS `orlms_ai_analysis` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `legislative_item_id` INT NOT NULL,
  `analysis_type` VARCHAR(60) NOT NULL,
  `model_used` VARCHAR(100) NOT NULL,
  `prompt_summary` TEXT NULL,
  `result_json` LONGTEXT NOT NULL,
  `confidence_score` DECIMAL(5,2) NULL,
  `review_status` VARCHAR(40) NOT NULL DEFAULT 'Pending Review',
  `reviewed_by` INT NULL,
  `reviewed_at` DATETIME NULL,
  `created_by` INT NULL,
  `duration_ms` INT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_orlms_ai_item` (`legislative_item_id`,`analysis_type`,`created_at`),
  CONSTRAINT `fk_orlms_ai_item` FOREIGN KEY (`legislative_item_id`) REFERENCES `legislative_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_orlms_ai_created` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_orlms_ai_reviewed` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

SET @orlms_system_id := (SELECT id FROM systems WHERE code='ordinance' LIMIT 1);
INSERT INTO permissions (system_id,code,name,description) VALUES
(@orlms_system_id,'orlms.ai.view','View ORLMS Ollama Assistant','View and run local Ollama classification and drafting analysis.'),
(@orlms_system_id,'orlms.ai.manage','Manage ORLMS AI Classification','Run Ollama analysis and apply human-approved classification suggestions.')
ON DUPLICATE KEY UPDATE name=VALUES(name),description=VALUES(description);

INSERT IGNORE INTO role_permissions (role_id,permission_id)
SELECT r.id,p.id FROM roles r JOIN permissions p ON p.system_id=@orlms_system_id
WHERE r.name IN ('Administrator','Legislative Staff') AND p.code IN ('orlms.ai.view','orlms.ai.manage');

INSERT IGNORE INTO role_permissions (role_id,permission_id)
SELECT r.id,p.id FROM roles r JOIN permissions p ON p.system_id=@orlms_system_id
WHERE r.name='Committee Member' AND p.code='orlms.ai.view';

INSERT INTO orlms_schema_migrations (migration_key,description,applied_at)
VALUES ('006_ollama_ai','Local Ollama document classification and drafting review with human approval.',NOW())
ON DUPLICATE KEY UPDATE description=VALUES(description);
