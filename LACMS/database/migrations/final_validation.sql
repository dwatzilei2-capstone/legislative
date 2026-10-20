USE `legislative_management_db`;

-- ============================================================
-- LACMS FINAL DATABASE VALIDATION
-- Run after migrations 001 through 004.
-- READ-ONLY: no application rows are modified.
-- ============================================================

SELECT '1. SYSTEM / MIGRATION READINESS' AS section_name;

SELECT id,code,name,base_url,status
FROM legislative_management_db.systems
WHERE code='agenda';

SELECT migration_key,description,applied_at
FROM legislative_management_db.lacms_schema_migrations
ORDER BY migration_key;

SELECT COUNT(*) AS lacms_table_count
FROM information_schema.tables
WHERE table_schema='legislative_management_db'
  AND table_name LIKE 'lacms\_%';

SELECT
  (SELECT COUNT(*)
   FROM legislative_management_db.permissions p
   JOIN legislative_management_db.systems s ON s.id=p.system_id
   WHERE s.code='agenda'
     AND p.code LIKE 'lacms.%') AS fine_grained_permission_count,

  (SELECT COUNT(*)
   FROM legislative_management_db.users u
   JOIN legislative_management_db.user_system_access usa
     ON usa.user_id=u.id
    AND usa.system_id=(
      SELECT id
      FROM legislative_management_db.systems
      WHERE code='agenda'
      LIMIT 1
    )
   WHERE u.deleted_at IS NULL) AS explicit_access_rows,

  (SELECT COUNT(*)
   FROM legislative_management_db.users u
   JOIN legislative_management_db.roles r ON r.id=u.role_id
   JOIN legislative_management_db.user_system_access usa
     ON usa.user_id=u.id
    AND usa.system_id=(
      SELECT id
      FROM legislative_management_db.systems
      WHERE code='agenda'
      LIMIT 1
    )
   WHERE r.name='Administrator'
     AND u.status='Active'
     AND u.deleted_at IS NULL
     AND usa.status='Active') AS active_lacms_administrators;

SELECT '2. OPERATIONAL COUNTS' AS section_name;

SELECT
  (SELECT COUNT(*) FROM legislative_management_db.lacms_agendas) AS agendas,
  (SELECT COUNT(*) FROM legislative_management_db.lacms_agenda_items) AS agenda_items,
  (SELECT COUNT(*) FROM legislative_management_db.lacms_calendar_events) AS calendar_events,
  (SELECT COUNT(*) FROM legislative_management_db.lacms_calendar_conflicts) AS calendar_conflicts,
  (SELECT COUNT(*) FROM legislative_management_db.lacms_meetings) AS meetings,
  (SELECT COUNT(*) FROM legislative_management_db.lacms_deadlines) AS deadlines,
  (SELECT COUNT(*) FROM legislative_management_db.lacms_deadline_reminders) AS deadline_reminders,
  (SELECT COUNT(*) FROM legislative_management_db.lacms_notifications) AS notifications,
  (SELECT COUNT(*) FROM legislative_management_db.lacms_notification_recipients) AS notification_recipients,
  (SELECT COUNT(*) FROM legislative_management_db.lacms_sync_records) AS synchronization_records,
  (SELECT COUNT(*) FROM legislative_management_db.lacms_sync_actions) AS synchronization_actions,
  (SELECT COUNT(*) FROM legislative_management_db.lacms_ai_request_logs) AS ai_requests;

SELECT '3. ROLE / PERMISSION MATRIX' AS section_name;

SELECT
    r.name AS role_name,
    COUNT(p.id) AS lacms_permission_count
FROM legislative_management_db.roles r
LEFT JOIN legislative_management_db.role_permissions rp
  ON rp.role_id=r.id
LEFT JOIN legislative_management_db.permissions p
  ON p.id=rp.permission_id
 AND p.system_id=(
     SELECT id
     FROM legislative_management_db.systems
     WHERE code='agenda'
     LIMIT 1
 )
 AND p.code LIKE 'lacms.%'
GROUP BY r.id,r.name
ORDER BY r.id;

SELECT '4. INTEGRITY CHECKS - EXPECT ZERO PROBLEM ROWS' AS section_name;

SELECT 'Finalized agenda without active agenda item' AS check_name,COUNT(*) AS problem_rows
FROM legislative_management_db.lacms_agendas a
WHERE a.status='Finalized'
  AND NOT EXISTS(
    SELECT 1
    FROM legislative_management_db.lacms_agenda_items i
    WHERE i.agenda_id=a.id
      AND i.item_status NOT IN ('Removed','Deferred')
  )

UNION ALL
SELECT 'Duplicate linked legislative item inside one agenda',COUNT(*)
FROM (
  SELECT agenda_id,legislative_item_id
  FROM legislative_management_db.lacms_agenda_items
  WHERE legislative_item_id IS NOT NULL
  GROUP BY agenda_id,legislative_item_id
  HAVING COUNT(*)>1
) x

UNION ALL
SELECT 'Calendar event with end not later than start',COUNT(*)
FROM legislative_management_db.lacms_calendar_events
WHERE end_datetime IS NOT NULL
  AND end_datetime<=start_datetime

UNION ALL
SELECT 'Confirmed event with open Critical schedule conflict',COUNT(*)
FROM legislative_management_db.lacms_calendar_events e
JOIN legislative_management_db.lacms_calendar_conflicts c
  ON c.calendar_event_id=e.id
WHERE e.status='Confirmed'
  AND c.status='Open'
  AND c.severity='Critical'

UNION ALL
SELECT 'Meeting without linked calendar event',COUNT(*)
FROM legislative_management_db.lacms_meetings
WHERE calendar_event_id IS NULL

UNION ALL
SELECT 'Meeting schedule differs from linked calendar event',COUNT(*)
FROM legislative_management_db.lacms_meetings m
JOIN legislative_management_db.lacms_calendar_events e
  ON e.id=m.calendar_event_id
WHERE m.start_datetime<>e.start_datetime
   OR NOT (m.end_datetime<=>e.end_datetime)
   OR COALESCE(m.venue,'')<>COALESCE(e.venue,'')

UNION ALL
SELECT 'Confirmed meeting linked to invalid calendar state',COUNT(*)
FROM legislative_management_db.lacms_meetings m
JOIN legislative_management_db.lacms_calendar_events e
  ON e.id=m.calendar_event_id
WHERE m.status='Confirmed'
  AND e.status NOT IN ('Confirmed','In Progress','Completed')

UNION ALL
SELECT 'Past-due deadline not marked Overdue or closed',COUNT(*)
FROM legislative_management_db.lacms_deadlines
WHERE due_datetime<NOW()
  AND status NOT IN ('Overdue','Completed','Cancelled')

UNION ALL
SELECT 'Completed deadline missing completion metadata',COUNT(*)
FROM legislative_management_db.lacms_deadlines
WHERE status='Completed'
  AND (completed_at IS NULL OR completed_by IS NULL)

UNION ALL
SELECT 'Completed deadline still has Scheduled reminder',COUNT(*)
FROM legislative_management_db.lacms_deadlines d
JOIN legislative_management_db.lacms_deadline_reminders r
  ON r.deadline_id=d.id
WHERE d.status='Completed'
  AND r.status='Scheduled'

UNION ALL
SELECT 'Reminder scheduled at or after deadline due time',COUNT(*)
FROM legislative_management_db.lacms_deadline_reminders r
JOIN legislative_management_db.lacms_deadlines d
  ON d.id=r.deadline_id
WHERE r.remind_at>=d.due_datetime

UNION ALL
SELECT 'Sent notification still has Pending recipient',COUNT(*)
FROM legislative_management_db.lacms_notifications n
JOIN legislative_management_db.lacms_notification_recipients r
  ON r.notification_id=n.id
WHERE n.status='Sent'
  AND r.delivery_status='Pending'

UNION ALL
SELECT 'Deadline notification with missing source deadline',COUNT(*)
FROM legislative_management_db.lacms_notifications n
LEFT JOIN legislative_management_db.lacms_deadlines d
  ON n.source_type='Deadline'
 AND d.id=n.source_id
WHERE n.source_type='Deadline'
  AND d.id IS NULL

UNION ALL
SELECT 'Meeting notification with missing source meeting',COUNT(*)
FROM legislative_management_db.lacms_notifications n
LEFT JOIN legislative_management_db.lacms_meetings m
  ON n.source_type='Meeting'
 AND m.id=n.source_id
WHERE n.source_type='Meeting'
  AND m.id IS NULL

UNION ALL
SELECT 'Closed synchronization with unfinished action',COUNT(*)
FROM legislative_management_db.lacms_sync_records s
WHERE s.status='Closed'
  AND EXISTS(
    SELECT 1
    FROM legislative_management_db.lacms_sync_actions a
    WHERE a.sync_record_id=s.id
      AND a.status IN ('Pending','In Progress')
  )

UNION ALL
SELECT 'Resolved/Closed synchronization missing resolution metadata',COUNT(*)
FROM legislative_management_db.lacms_sync_records
WHERE status IN ('Resolved','Closed')
  AND (resolved_at IS NULL OR resolved_by IS NULL)

UNION ALL
SELECT 'Completed synchronization action missing completion metadata',COUNT(*)
FROM legislative_management_db.lacms_sync_actions
WHERE status='Completed'
  AND (completed_at IS NULL OR completed_by IS NULL)

UNION ALL
SELECT 'Non-completed synchronization action retains completion metadata',COUNT(*)
FROM legislative_management_db.lacms_sync_actions
WHERE status<>'Completed'
  AND (completed_at IS NOT NULL OR completed_by IS NOT NULL)

UNION ALL
SELECT 'Non-deleted shared user missing explicit LACMS access row',COUNT(*)
FROM legislative_management_db.users u
LEFT JOIN legislative_management_db.user_system_access usa
  ON usa.user_id=u.id
 AND usa.system_id=(
   SELECT id
   FROM legislative_management_db.systems
   WHERE code='agenda'
   LIMIT 1
 )
WHERE u.deleted_at IS NULL
  AND usa.user_id IS NULL

UNION ALL
SELECT 'Active Administrator without active LACMS access',COUNT(*)
FROM legislative_management_db.users u
JOIN legislative_management_db.roles r ON r.id=u.role_id
LEFT JOIN legislative_management_db.user_system_access usa
  ON usa.user_id=u.id
 AND usa.system_id=(
   SELECT id
   FROM legislative_management_db.systems
   WHERE code='agenda'
   LIMIT 1
 )
WHERE r.name='Administrator'
  AND u.status='Active'
  AND u.deleted_at IS NULL
  AND (usa.user_id IS NULL OR usa.status<>'Active');

SELECT '5. OPERATIONAL WATCH LISTS' AS section_name;

SELECT deadline_reference,title,due_datetime,priority_level,status
FROM legislative_management_db.lacms_deadlines
WHERE status='Overdue'
ORDER BY due_datetime;

SELECT e.event_reference,e.title,e.start_datetime,
       c.conflict_type,c.severity,c.details
FROM legislative_management_db.lacms_calendar_conflicts c
JOIN legislative_management_db.lacms_calendar_events e
  ON e.id=c.calendar_event_id
WHERE c.status='Open'
ORDER BY FIELD(c.severity,'Critical','Warning'),e.start_datetime;

SELECT s.sync_reference,s.title,s.priority_level,s.status,
       COUNT(a.id) AS open_actions
FROM legislative_management_db.lacms_sync_records s
LEFT JOIN legislative_management_db.lacms_sync_actions a
  ON a.sync_record_id=s.id
 AND a.status IN ('Pending','In Progress')
WHERE s.status NOT IN ('Closed','Cancelled')
GROUP BY s.id,s.sync_reference,s.title,s.priority_level,s.status
HAVING open_actions>0
ORDER BY FIELD(s.priority_level,'Urgent','High','Normal','Low'),s.updated_at;

SELECT n.notification_reference,n.subject,
       r.recipient_name,r.recipient_email,
       r.delivery_channel,r.delivery_status
FROM legislative_management_db.lacms_notification_recipients r
JOIN legislative_management_db.lacms_notifications n
  ON n.id=r.notification_id
WHERE r.user_id IS NULL
  AND r.delivery_status='Pending'
ORDER BY n.created_at;

SELECT '6. SECURITY / AUDIT SUMMARY' AS section_name;

SELECT COUNT(*) AS recent_failed_login_attempts_24h
FROM legislative_management_db.lacms_login_attempts
WHERE was_successful=0
  AND attempted_at>=DATE_SUB(NOW(),INTERVAL 24 HOUR);

SELECT COUNT(*) AS recent_permission_denials_30d
FROM legislative_management_db.activity_logs
WHERE system_id=(
    SELECT id
    FROM legislative_management_db.systems
    WHERE code='agenda'
    LIMIT 1
)
  AND action='LACMS Permission Denied'
  AND created_at>=DATE_SUB(NOW(),INTERVAL 30 DAY);

SELECT COUNT(*) AS recent_lacms_activity_30d
FROM legislative_management_db.activity_logs
WHERE system_id=(
    SELECT id
    FROM legislative_management_db.systems
    WHERE code='agenda'
    LIMIT 1
)
  AND created_at>=DATE_SUB(NOW(),INTERVAL 30 DAY);

SELECT '7. FINAL RESULT GUIDE' AS section_name;
SELECT
 'Every row in section 4 should normally return problem_rows = 0. APP_DEBUG may remain enabled only during local XAMPP development. External email may remain Pending until SMTP integration is configured.' AS validation_note;
