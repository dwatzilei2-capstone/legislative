USE `legislative_management_db`;

-- ============================================================
-- VQDSS SUBSYSTEM #5 — FINAL SECURITY / RBAC MIGRATION
-- ============================================================

UPDATE `systems`
SET `base_url`='http://localhost/vqdss',
    `status`='Active',
    `updated_at`=NOW()
WHERE `code`='voting';

CREATE TABLE IF NOT EXISTS `vqd_login_attempts` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `email_hash` CHAR(64) NOT NULL,
    `ip_hash` CHAR(64) NOT NULL,
    `successful` TINYINT(1) NOT NULL DEFAULT 0,
    `attempted_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_vqd_login_email` (`email_hash`,`attempted_at`),
    KEY `idx_vqd_login_ip` (`ip_hash`,`attempted_at`),
    KEY `idx_vqd_login_success` (`successful`,`attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Fine-grained VQDSS permissions.
INSERT INTO `permissions` (`system_id`,`code`,`name`,`description`,`created_at`)
SELECT s.id,p.code,p.name,p.description,NOW()
FROM `systems` s
JOIN (
    SELECT 'vqd.dashboard.view' code,'View VQDSS Dashboard' name,
           'Allows access to the VQDSS operational dashboard.' description
    UNION ALL SELECT 'vqd.records.manage','Manage VQDSS Core Records',
           'Allows staff-level create and update operations across VQDSS.'
    UNION ALL SELECT 'vqd.quorum.view','View Quorum Verification',
           'Allows viewing voting sessions, rosters and quorum records.'
    UNION ALL SELECT 'vqd.quorum.manage','Manage Quorum Sessions',
           'Allows creating and editing voting sessions and quorum configuration.'
    UNION ALL SELECT 'vqd.quorum.attendance','Record Quorum Attendance',
           'Allows attendance and voter-eligibility updates for quorum verification.'
    UNION ALL SELECT 'vqd.quorum.certify','Certify Quorum',
           'Allows verification, certification and controlled revocation of quorum results.'
    UNION ALL SELECT 'vqd.voting.view','View Voting Management',
           'Allows viewing voting events and authorized voting rosters.'
    UNION ALL SELECT 'vqd.voting.manage','Manage Voting Events',
           'Allows creation and operational control of voting events.'
    UNION ALL SELECT 'vqd.voting.cast','Cast or Record Vote',
           'Allows an authorized user to submit a ballot or staff to record a manual ballot.'
    UNION ALL SELECT 'vqd.tally.view','View Vote Tallies',
           'Allows viewing computed and certified vote tallies.'
    UNION ALL SELECT 'vqd.tally.manage','Manage Vote Tallies',
           'Allows computation, recounts, evidence upload and tally review.'
    UNION ALL SELECT 'vqd.tally.certify','Certify Vote Tally',
           'Allows final certification of vote tally results.'
    UNION ALL SELECT 'vqd.decisions.view','View Decision Records',
           'Allows viewing official VQDSS decisions.'
    UNION ALL SELECT 'vqd.decisions.manage','Manage Decision Records',
           'Allows drafting decisions, implementation actions and decision documents.'
    UNION ALL SELECT 'vqd.decisions.certify','Certify Decision',
           'Allows formal recording and certification of official decisions.'
    UNION ALL SELECT 'vqd.decisions.publish','Publish Decision',
           'Allows publication of authorized Public decision records.'
    UNION ALL SELECT 'vqd.validation.view','View Result Validation',
           'Allows viewing validation checks, findings and reports.'
    UNION ALL SELECT 'vqd.validation.manage','Manage Result Validation',
           'Allows creating validation records, findings, evidence and report versions.'
    UNION ALL SELECT 'vqd.validation.certify','Certify Result Validation',
           'Allows validation and final certification of VQDSS results.'
    UNION ALL SELECT 'vqd.reports.view','View VQDSS Reports',
           'Allows access to module reports, analytics and workflow traceability.'
    UNION ALL SELECT 'vqd.activity_logs.view','View VQDSS Activity Logs',
           'Allows access to the VQDSS audit trail.'
    UNION ALL SELECT 'vqd.users.manage','Manage VQDSS Users',
           'Allows administration of shared user accounts and VQDSS access.'
    UNION ALL SELECT 'vqd.system_health.view','View VQDSS System Health',
           'Allows access to final readiness, migration and integrity checks.'
) p
WHERE s.code='voting'
ON DUPLICATE KEY UPDATE
    `name`=VALUES(`name`),
    `description`=VALUES(`description`),
    `system_id`=VALUES(`system_id`);

-- Administrator gets every VQDSS permission.
INSERT IGNORE INTO `role_permissions` (`role_id`,`permission_id`,`created_at`)
SELECT r.id,p.id,NOW()
FROM roles r
JOIN permissions p ON p.code LIKE 'vqd.%'
WHERE r.name='Administrator';

-- Legislative Staff operational permissions.
INSERT IGNORE INTO `role_permissions` (`role_id`,`permission_id`,`created_at`)
SELECT r.id,p.id,NOW()
FROM roles r
JOIN permissions p ON p.code IN (
    'vqd.dashboard.view',
    'vqd.records.manage',
    'vqd.quorum.view',
    'vqd.quorum.manage',
    'vqd.quorum.attendance',
    'vqd.quorum.certify',
    'vqd.voting.view',
    'vqd.voting.manage',
    'vqd.voting.cast',
    'vqd.tally.view',
    'vqd.tally.manage',
    'vqd.tally.certify',
    'vqd.decisions.view',
    'vqd.decisions.manage',
    'vqd.decisions.certify',
    'vqd.decisions.publish',
    'vqd.validation.view',
    'vqd.validation.manage',
    'vqd.validation.certify',
    'vqd.reports.view',
    'vqd.activity_logs.view'
)
WHERE r.name='Legislative Staff';

-- Committee Member permissions.
INSERT IGNORE INTO `role_permissions` (`role_id`,`permission_id`,`created_at`)
SELECT r.id,p.id,NOW()
FROM roles r
JOIN permissions p ON p.code IN (
    'vqd.dashboard.view',
    'vqd.quorum.view',
    'vqd.quorum.attendance',
    'vqd.voting.view',
    'vqd.voting.cast',
    'vqd.tally.view',
    'vqd.decisions.view',
    'vqd.validation.view',
    'vqd.reports.view'
)
WHERE r.name='Committee Member';

-- VQDSS is an internal legislative decision-support subsystem.
-- Registered Stakeholder and Public User roles receive no VQDSS module
-- permissions by default.

-- Preserve current behavior by explicitly granting current active users access.
INSERT INTO `user_system_access`
    (`user_id`,`system_id`,`access_level`,`status`,`granted_by`,`granted_at`,`updated_at`)
SELECT
    u.id,
    s.id,
    CASE r.name
        WHEN 'Administrator' THEN 'Administrator'
        WHEN 'Legislative Staff' THEN 'Staff'
        WHEN 'Committee Member' THEN 'Committee'
        WHEN 'Registered Stakeholder' THEN 'Stakeholder'
        ELSE 'Standard'
    END,
    'Active',
    (
      SELECT ua.id
      FROM users ua
      JOIN roles ra ON ra.id=ua.role_id
      WHERE ra.name='Administrator'
        AND ua.status='Active'
        AND ua.deleted_at IS NULL
      ORDER BY ua.id
      LIMIT 1
    ),
    NOW(),
    NOW()
FROM users u
JOIN roles r ON r.id=u.role_id
JOIN systems s ON s.code='voting'
LEFT JOIN user_system_access usa
       ON usa.user_id=u.id
      AND usa.system_id=s.id
WHERE u.status='Active'
  AND u.deleted_at IS NULL
  AND r.name IN ('Administrator','Legislative Staff','Committee Member')
  AND usa.id IS NULL
ON DUPLICATE KEY UPDATE
    `updated_at`=VALUES(`updated_at`);

INSERT INTO `vqd_schema_migrations` (`migration_key`,`description`)
VALUES (
    '004_vqdss_security_rbac_final',
    'Final VQDSS RBAC permissions, explicit system access, login throttling and production system activation.'
)
ON DUPLICATE KEY UPDATE
    `description`=VALUES(`description`);

SELECT
    (SELECT COUNT(*) FROM permissions WHERE code LIKE 'vqd.%') AS vqd_permission_count,
    (SELECT COUNT(*)
       FROM role_permissions rp
       JOIN permissions p ON p.id=rp.permission_id
      WHERE p.code LIKE 'vqd.%') AS vqd_role_permission_count,
    (SELECT COUNT(*)
       FROM user_system_access usa
       JOIN systems s ON s.id=usa.system_id
      WHERE s.code='voting') AS vqd_user_access_rows,
    EXISTS(
      SELECT 1 FROM information_schema.tables
      WHERE table_schema=DATABASE()
        AND table_name='vqd_login_attempts'
    ) AS login_throttle_table_ok;
