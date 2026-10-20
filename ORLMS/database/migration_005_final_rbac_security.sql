USE `legislative_management_db`;

-- ============================================================
-- ORLMS STEP 11
-- Fine-Grained RBAC + Final Security Foundation
-- ============================================================

CREATE TABLE IF NOT EXISTS `orlms_login_attempts` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `email_hash` CHAR(64) NOT NULL,
    `ip_hash` CHAR(64) NOT NULL,
    `was_successful` TINYINT(1) NOT NULL DEFAULT 0,
    `attempted_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_orlms_login_email_time` (`email_hash`,`attempted_at`),
    KEY `idx_orlms_login_ip_time` (`ip_hash`,`attempted_at`),
    KEY `idx_orlms_login_time` (`attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO systems (code,name,description,base_url,status,created_at,updated_at)
VALUES (
    'ordinance',
    'Ordinance and Resolution Life Cycle Management System',
    'Manages ordinances and resolutions from drafting through implementation and revision.',
    'http://localhost/orlms',
    'Active',
    NOW(),
    NOW()
)
ON DUPLICATE KEY UPDATE
    name=VALUES(name),
    description=VALUES(description),
    base_url=VALUES(base_url),
    status='Active',
    updated_at=NOW();

SET @orlms_system_id := (
    SELECT id FROM systems WHERE code='ordinance' LIMIT 1
);

-- Fine-grained ORLMS permissions.
INSERT INTO permissions (system_id,code,name,description)
VALUES
(@orlms_system_id,'orlms.dashboard.view','View ORLMS Dashboard','View the ORLMS operational dashboard and lifecycle metrics.'),
(@orlms_system_id,'orlms.drafting.view','View ORLMS Drafting','View ordinance and resolution drafting records.'),
(@orlms_system_id,'orlms.drafting.manage','Manage ORLMS Drafting','Create, edit, submit and manage ordinance/resolution drafts and documents.'),
(@orlms_system_id,'orlms.reviews.view','View ORLMS Reviews','View committee review records and review evidence.'),
(@orlms_system_id,'orlms.reviews.manage','Manage ORLMS Reviews','Start reviews, update checklists/comments and complete committee reviews.'),
(@orlms_system_id,'orlms.endorsements.manage','Manage ORLMS Endorsements','Record and update formal committee endorsements.'),
(@orlms_system_id,'orlms.approvals.view','View ORLMS Approvals','View approval and enactment records.'),
(@orlms_system_id,'orlms.approvals.manage','Manage ORLMS Approvals','Create and transition formal approval actions.'),
(@orlms_system_id,'orlms.enactments.manage','Manage ORLMS Enactments','Create and finalize enactment/adoption records and official numbering.'),
(@orlms_system_id,'orlms.publications.view','View ORLMS Publications','View official publication and dissemination records.'),
(@orlms_system_id,'orlms.publications.manage','Manage ORLMS Publications','Create, publish, withdraw and disseminate official publications.'),
(@orlms_system_id,'orlms.implementation.view','View ORLMS Implementation','View implementation plans, progress, risks and evidence.'),
(@orlms_system_id,'orlms.implementation.manage','Manage ORLMS Implementation','Create/update implementation plans, progress, issues and evidence.'),
(@orlms_system_id,'orlms.amendments.view','View ORLMS Amendments','View amendments and official revision history.'),
(@orlms_system_id,'orlms.amendments.manage','Manage ORLMS Amendments','Create, review, approve and apply amendments/revisions.'),
(@orlms_system_id,'orlms.workflow.view','View ORLMS Workflow','View cross-module lifecycle workflow and traceability.'),
(@orlms_system_id,'orlms.reports.view','View ORLMS Reports','View, print and export ORLMS reports and analytics.'),
(@orlms_system_id,'orlms.ai.view','View ORLMS AI Classification','View the planned/current ORLMS AI classification page.'),
(@orlms_system_id,'orlms.activity_logs.view','View ORLMS Activity Logs','View and export ORLMS audit/activity logs.'),
(@orlms_system_id,'orlms.users.manage','Manage ORLMS Users','Manage shared accounts and ORLMS subsystem access.'),
(@orlms_system_id,'orlms.system_health.view','View ORLMS System Health','View ORLMS deployment, schema, security and integrity health checks.')
ON DUPLICATE KEY UPDATE
    system_id=VALUES(system_id),
    name=VALUES(name),
    description=VALUES(description);

-- Administrator receives every ORLMS permission.
INSERT IGNORE INTO role_permissions (role_id,permission_id)
SELECT r.id,p.id
FROM roles r
JOIN permissions p ON p.system_id=@orlms_system_id
WHERE r.name='Administrator'
  AND p.code LIKE 'orlms.%';

-- Legislative Staff can operate the full legislative lifecycle
-- except administration/security pages.
INSERT IGNORE INTO role_permissions (role_id,permission_id)
SELECT r.id,p.id
FROM roles r
JOIN permissions p ON p.system_id=@orlms_system_id
WHERE r.name='Legislative Staff'
  AND p.code IN (
    'orlms.dashboard.view',
    'orlms.drafting.view',
    'orlms.drafting.manage',
    'orlms.reviews.view',
    'orlms.reviews.manage',
    'orlms.endorsements.manage',
    'orlms.approvals.view',
    'orlms.approvals.manage',
    'orlms.enactments.manage',
    'orlms.publications.view',
    'orlms.publications.manage',
    'orlms.implementation.view',
    'orlms.implementation.manage',
    'orlms.amendments.view',
    'orlms.amendments.manage',
    'orlms.workflow.view',
    'orlms.reports.view',
    'orlms.ai.view'
  );

-- Committee Members preserve their current review/endorsement workflow,
-- with read-only downstream visibility and reporting.
INSERT IGNORE INTO role_permissions (role_id,permission_id)
SELECT r.id,p.id
FROM roles r
JOIN permissions p ON p.system_id=@orlms_system_id
WHERE r.name='Committee Member'
  AND p.code IN (
    'orlms.dashboard.view',
    'orlms.reviews.view',
    'orlms.reviews.manage',
    'orlms.endorsements.manage',
    'orlms.approvals.view',
    'orlms.publications.view',
    'orlms.implementation.view',
    'orlms.amendments.view',
    'orlms.workflow.view',
    'orlms.reports.view',
    'orlms.ai.view'
  );

-- Explicit ORLMS system access for every existing shared account.
-- Internal legislative roles are active; stakeholder/public accounts
-- remain available to the shared platform but are not granted ORLMS.
INSERT INTO user_system_access
    (user_id,system_id,access_level,status,granted_by,granted_at,updated_at)
SELECT
    u.id,
    @orlms_system_id,
    CASE r.name
      WHEN 'Administrator' THEN 'Administrator'
      WHEN 'Legislative Staff' THEN 'Staff'
      WHEN 'Committee Member' THEN 'Committee'
      WHEN 'Registered Stakeholder' THEN 'Stakeholder'
      ELSE 'Standard'
    END,
    CASE
      WHEN r.name IN ('Administrator','Legislative Staff','Committee Member')
           AND u.status='Active'
      THEN 'Active'
      ELSE 'Inactive'
    END,
    (
      SELECT ua.id
      FROM users ua
      JOIN roles ra ON ra.id=ua.role_id
      WHERE ra.name='Administrator'
        AND ua.status='Active'
        AND ua.deleted_at IS NULL
      ORDER BY ua.id LIMIT 1
    ),
    NOW(),
    NOW()
FROM users u
JOIN roles r ON r.id=u.role_id
WHERE u.deleted_at IS NULL
ON DUPLICATE KEY UPDATE
    access_level=VALUES(access_level),
    status=CASE
      WHEN user_system_access.status='Inactive' THEN 'Inactive'
      ELSE VALUES(status)
    END,
    updated_at=NOW();

INSERT INTO orlms_schema_migrations (migration_key,description)
VALUES (
    '005_final_rbac_security',
    'Adds ORLMS fine-grained permissions, role mappings, explicit subsystem access backfill and hashed login-attempt rate limiting.'
)
ON DUPLICATE KEY UPDATE
    description=VALUES(description);

-- Cleanup old rate-limit records when the migration is re-run.
DELETE FROM orlms_login_attempts
WHERE attempted_at < DATE_SUB(NOW(),INTERVAL 30 DAY);

SELECT
  (SELECT COUNT(*) FROM permissions
   WHERE system_id=@orlms_system_id AND code LIKE 'orlms.%') AS orlms_permission_count,
  (SELECT COUNT(*) FROM role_permissions rp
   JOIN permissions p ON p.id=rp.permission_id
   WHERE p.system_id=@orlms_system_id) AS orlms_role_permission_mappings,
  (SELECT COUNT(*) FROM users u
   JOIN user_system_access usa
     ON usa.user_id=u.id AND usa.system_id=@orlms_system_id
   WHERE u.deleted_at IS NULL) AS explicit_orlms_access_rows,
  EXISTS(
    SELECT 1 FROM information_schema.tables
    WHERE table_schema=DATABASE()
      AND table_name='orlms_login_attempts'
  ) AS login_attempt_table_ok;
