USE `legislative_management_db`;

CREATE TABLE IF NOT EXISTS `lacms_sync_actions` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `sync_record_id` BIGINT NOT NULL,
    `action_title` VARCHAR(255) NOT NULL,
    `action_description` TEXT DEFAULT NULL,
    `responsible_user_id` INT DEFAULT NULL,
    `responsible_office_id` INT DEFAULT NULL,
    `due_datetime` DATETIME DEFAULT NULL,
    `priority_level` VARCHAR(50) NOT NULL DEFAULT 'Normal',
    `status` VARCHAR(50) NOT NULL DEFAULT 'Pending',
    `completion_notes` TEXT DEFAULT NULL,
    `completed_by` INT DEFAULT NULL,
    `completed_at` DATETIME DEFAULT NULL,
    `created_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_lacms_sync_action_record` (`sync_record_id`,`status`,`due_datetime`),
    CONSTRAINT `fk_lacms_sync_action_record` FOREIGN KEY (`sync_record_id`) REFERENCES `lacms_sync_records` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_lacms_sync_action_user` FOREIGN KEY (`responsible_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_lacms_sync_action_office` FOREIGN KEY (`responsible_office_id`) REFERENCES `offices` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_lacms_sync_action_completed_by` FOREIGN KEY (`completed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_lacms_sync_action_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `lacms_sync_documents` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `sync_record_id` BIGINT NOT NULL,
    `file_name` VARCHAR(255) NOT NULL,
    `file_path` VARCHAR(500) NOT NULL,
    `document_type` VARCHAR(100) NOT NULL DEFAULT 'Coordination Document',
    `description` TEXT DEFAULT NULL,
    `visibility` VARCHAR(30) NOT NULL DEFAULT 'Internal',
    `uploaded_by` INT DEFAULT NULL,
    `uploaded_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_lacms_sync_document_record` (`sync_record_id`,`uploaded_at`),
    CONSTRAINT `fk_lacms_sync_document_record` FOREIGN KEY (`sync_record_id`) REFERENCES `lacms_sync_records` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_lacms_sync_document_user` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `lacms_login_attempts` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `email_hash` CHAR(64) NOT NULL,
    `ip_hash` CHAR(64) NOT NULL,
    `was_successful` TINYINT(1) NOT NULL DEFAULT 0,
    `attempted_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_lacms_login_email_time` (`email_hash`,`attempted_at`),
    KEY `idx_lacms_login_ip_time` (`ip_hash`,`attempted_at`),
    KEY `idx_lacms_login_time` (`attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO systems (code,name,description,base_url,status,created_at,updated_at)
VALUES ('agenda','Legislative Agenda and Calendar Management System','Manages legislative agendas, calendar schedules, meetings, deadlines, reminders and executive-legislative coordination.','http://localhost/lacms','Active',NOW(),NOW())
ON DUPLICATE KEY UPDATE name=VALUES(name),description=VALUES(description),base_url=VALUES(base_url),status='Active',updated_at=NOW();

SET @lacms_system_id := (SELECT id FROM systems WHERE code='agenda' LIMIT 1);

INSERT INTO permissions (system_id,code,name,description) VALUES
(@lacms_system_id,'lacms.dashboard.view','View LACMS Dashboard','View the LACMS operational dashboard and coordination metrics.'),
(@lacms_system_id,'lacms.agendas.view','View Legislative Agendas','View legislative agenda records and agenda items.'),
(@lacms_system_id,'lacms.agendas.manage','Manage Legislative Agendas','Create, edit, organize, finalize and manage agenda records and documents.'),
(@lacms_system_id,'lacms.calendar.view','View Legislative Calendar','View calendar activities, participants and schedule-conflict information.'),
(@lacms_system_id,'lacms.calendar.manage','Manage Legislative Calendar','Create, edit, confirm, reschedule and manage legislative calendar activities.'),
(@lacms_system_id,'lacms.meetings.view','View Meeting Coordination','View meeting coordination records, participants and supporting documents.'),
(@lacms_system_id,'lacms.meetings.manage','Manage Meeting Coordination','Create, edit, confirm, notify and manage legislative meetings.'),
(@lacms_system_id,'lacms.deadlines.view','View Deadline Tracking','View deadline assignments, reminders, escalation and completion state.'),
(@lacms_system_id,'lacms.deadlines.manage','Manage Deadline Tracking','Create, edit, assign, escalate, complete and manage legislative deadlines.'),
(@lacms_system_id,'lacms.notifications.view','View LACMS Notifications','View reminder and meeting notification queues and delivery state.'),
(@lacms_system_id,'lacms.notifications.manage','Manage LACMS Notifications','Generate, queue, process and cancel LACMS reminders and notifications.'),
(@lacms_system_id,'lacms.sync.view','View Executive-Legislative Synchronization','View executive-legislative coordination records, positions and actions.'),
(@lacms_system_id,'lacms.sync.manage','Manage Executive-Legislative Synchronization','Create and transition synchronization records, actions and evidence.'),
(@lacms_system_id,'lacms.reports.view','View LACMS Reports','View, print and export LACMS reports and workflow traceability.'),
(@lacms_system_id,'lacms.activity_logs.view','View LACMS Activity Logs','View, filter, print and export the LACMS audit trail.'),
(@lacms_system_id,'lacms.users.manage','Manage LACMS Users','Manage shared accounts and explicit LACMS subsystem access.'),
(@lacms_system_id,'lacms.system_health.view','View LACMS System Health','View final LACMS deployment, security and integrity readiness.')
ON DUPLICATE KEY UPDATE system_id=VALUES(system_id),name=VALUES(name),description=VALUES(description);

INSERT IGNORE INTO role_permissions (role_id,permission_id)
SELECT r.id,p.id FROM roles r JOIN permissions p ON p.system_id=@lacms_system_id
WHERE r.name='Administrator' AND p.code LIKE 'lacms.%';

INSERT IGNORE INTO role_permissions (role_id,permission_id)
SELECT r.id,p.id FROM roles r JOIN permissions p ON p.system_id=@lacms_system_id
WHERE r.name='Legislative Staff' AND p.code IN (
'lacms.dashboard.view','lacms.agendas.view','lacms.agendas.manage','lacms.calendar.view','lacms.calendar.manage',
'lacms.meetings.view','lacms.meetings.manage','lacms.deadlines.view','lacms.deadlines.manage',
'lacms.notifications.view','lacms.notifications.manage','lacms.sync.view','lacms.sync.manage',
'lacms.reports.view','lacms.activity_logs.view');

INSERT IGNORE INTO role_permissions (role_id,permission_id)
SELECT r.id,p.id FROM roles r JOIN permissions p ON p.system_id=@lacms_system_id
WHERE r.name='Committee Member' AND p.code IN (
'lacms.dashboard.view','lacms.agendas.view','lacms.calendar.view','lacms.meetings.view',
'lacms.deadlines.view','lacms.notifications.view','lacms.sync.view','lacms.reports.view');

INSERT INTO user_system_access (user_id,system_id,access_level,status,granted_by,granted_at,updated_at)
SELECT u.id,@lacms_system_id,
CASE r.name WHEN 'Administrator' THEN 'Administrator' WHEN 'Legislative Staff' THEN 'Staff' WHEN 'Committee Member' THEN 'Committee' WHEN 'Registered Stakeholder' THEN 'Stakeholder' ELSE 'Standard' END,
CASE WHEN r.name IN ('Administrator','Legislative Staff','Committee Member') AND u.status='Active' THEN 'Active' ELSE 'Inactive' END,
(SELECT ua.id FROM users ua JOIN roles ra ON ra.id=ua.role_id WHERE ra.name='Administrator' AND ua.status='Active' AND ua.deleted_at IS NULL ORDER BY ua.id LIMIT 1),
NOW(),NOW()
FROM users u JOIN roles r ON r.id=u.role_id WHERE u.deleted_at IS NULL
ON DUPLICATE KEY UPDATE access_level=VALUES(access_level),status=CASE WHEN user_system_access.status='Inactive' THEN 'Inactive' ELSE VALUES(status) END,updated_at=NOW();

INSERT INTO lacms_schema_migrations (migration_key,description)
VALUES ('004_sync_admin_rbac_security','Completes executive-legislative synchronization, LACMS administration, fine-grained RBAC, explicit subsystem access and hashed login-attempt rate limiting.')
ON DUPLICATE KEY UPDATE description=VALUES(description);

DELETE FROM lacms_login_attempts WHERE attempted_at < DATE_SUB(NOW(),INTERVAL 30 DAY);

SELECT
  (SELECT COUNT(*) FROM permissions WHERE system_id=@lacms_system_id AND code LIKE 'lacms.%') AS lacms_permission_count,
  (SELECT COUNT(*) FROM role_permissions rp JOIN permissions p ON p.id=rp.permission_id WHERE p.system_id=@lacms_system_id) AS lacms_role_permission_mappings,
  (SELECT COUNT(*) FROM users u JOIN user_system_access usa ON usa.user_id=u.id AND usa.system_id=@lacms_system_id WHERE u.deleted_at IS NULL) AS explicit_lacms_access_rows,
  EXISTS(SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='lacms_login_attempts') AS login_attempt_table_ok;
