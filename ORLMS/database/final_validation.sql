USE `legislative_management_db`;

-- ============================================================
-- ORLMS FINAL READ-ONLY VALIDATION
-- Steps 1-12
-- This script does not modify application records.
-- ============================================================

SELECT '1. ORLMS MIGRATIONS - EXPECT 001 THROUGH 005' section_name;
SELECT migration_key,description,applied_at
FROM orlms_schema_migrations
ORDER BY migration_key;

SELECT '2. FINE-GRAINED RBAC' section_name;

SELECT COUNT(*) AS orlms_permissions
FROM permissions
WHERE system_id=(SELECT id FROM systems WHERE code='ordinance' LIMIT 1)
  AND code LIKE 'orlms.%';

SELECT r.name role_name,COUNT(p.id) permission_count
FROM roles r
LEFT JOIN role_permissions rp ON rp.role_id=r.id
LEFT JOIN permissions p
  ON p.id=rp.permission_id
 AND p.system_id=(SELECT id FROM systems WHERE code='ordinance' LIMIT 1)
 AND p.code LIKE 'orlms.%'
GROUP BY r.id,r.name
ORDER BY r.id;

SELECT
  COUNT(*) total_non_deleted_users,
  SUM(usa.id IS NOT NULL) explicit_orlms_access_rows,
  SUM(usa.status='Active') active_orlms_access_rows,
  SUM(usa.status='Inactive') inactive_orlms_access_rows
FROM users u
LEFT JOIN user_system_access usa
  ON usa.user_id=u.id
 AND usa.system_id=(SELECT id FROM systems WHERE code='ordinance' LIMIT 1)
WHERE u.deleted_at IS NULL;

SELECT COUNT(*) active_orlms_administrators
FROM users u
JOIN roles r ON r.id=u.role_id
JOIN user_system_access usa
  ON usa.user_id=u.id
 AND usa.system_id=(SELECT id FROM systems WHERE code='ordinance' LIMIT 1)
WHERE r.name='Administrator'
  AND u.status='Active'
  AND u.deleted_at IS NULL
  AND usa.status='Active';

SELECT '3. CORE RECORD COUNTS' section_name;

SELECT
 (SELECT COUNT(*) FROM legislative_items li
  JOIN legislative_item_types lit ON lit.id=li.item_type_id
  WHERE li.deleted_at IS NULL AND lit.code='ordinance') ordinances,
 (SELECT COUNT(*) FROM legislative_items li
  JOIN legislative_item_types lit ON lit.id=li.item_type_id
  WHERE li.deleted_at IS NULL AND lit.code='resolution') resolutions,
 (SELECT COUNT(*) FROM orlms_reviews) reviews,
 (SELECT COUNT(*) FROM orlms_endorsements) endorsements,
 (SELECT COUNT(*) FROM orlms_approval_actions) approval_actions,
 (SELECT COUNT(*) FROM orlms_enactments) enactments,
 (SELECT COUNT(*) FROM orlms_publications) publications,
 (SELECT COUNT(*) FROM orlms_implementation_plans) implementation_plans,
 (SELECT COUNT(*) FROM orlms_amendments) amendments,
 (SELECT COUNT(*) FROM orlms_revision_history) revision_snapshots;

SELECT '4. INTEGRITY CHECKS - EXPECT ZERO PROBLEM ROWS' section_name;

SELECT 'Duplicate legislative reference_number' check_name,COUNT(*) problem_rows
FROM (
 SELECT reference_number
 FROM legislative_items
 WHERE deleted_at IS NULL
 GROUP BY reference_number
 HAVING COUNT(*)>1
) x

UNION ALL
SELECT 'Duplicate active Pending approval action per item',COUNT(*)
FROM (
 SELECT legislative_item_id
 FROM orlms_approval_actions
 WHERE action_status='Pending'
 GROUP BY legislative_item_id
 HAVING COUNT(*)>1
) x

UNION ALL
SELECT 'Duplicate official enactment number',COUNT(*)
FROM (
 SELECT official_number
 FROM orlms_enactments
 WHERE official_number IS NOT NULL AND official_number<>''
 GROUP BY official_number
 HAVING COUNT(*)>1
) x

UNION ALL
SELECT 'Enacted item without finalized enactment',COUNT(*)
FROM legislative_items li
JOIN legislative_item_types lit ON lit.id=li.item_type_id
LEFT JOIN orlms_enactments e ON e.legislative_item_id=li.id
WHERE li.deleted_at IS NULL
  AND lit.code IN ('ordinance','resolution')
  AND li.current_status='Enacted'
  AND (e.id IS NULL OR e.enactment_status<>'Finalized')

UNION ALL
SELECT 'Published publication without finalized enactment',COUNT(*)
FROM orlms_publications p
LEFT JOIN orlms_enactments e ON e.legislative_item_id=p.legislative_item_id
WHERE p.publication_status='Published'
  AND (e.id IS NULL OR e.enactment_status<>'Finalized')

UNION ALL
SELECT 'Implementation plan without Published release',COUNT(*)
FROM orlms_implementation_plans ip
WHERE NOT EXISTS(
 SELECT 1 FROM orlms_publications p
 WHERE p.legislative_item_id=ip.legislative_item_id
   AND p.publication_status='Published'
)

UNION ALL
SELECT 'Completed implementation plan below 100 percent',COUNT(*)
FROM orlms_implementation_plans
WHERE status='Completed'
  AND compliance_percent<100

UNION ALL
SELECT 'Completed implementation plan with unresolved High/Critical risk',COUNT(*)
FROM orlms_implementation_plans ip
WHERE ip.status='Completed'
 AND EXISTS(
   SELECT 1 FROM orlms_implementation_issues ii
   WHERE ii.implementation_plan_id=ip.id
     AND ii.severity IN ('High','Critical')
     AND ii.status NOT IN ('Resolved','Accepted Risk')
 )

UNION ALL
SELECT 'Multiple current official revisions per item',COUNT(*)
FROM (
 SELECT legislative_item_id
 FROM orlms_revision_history
 WHERE is_current=1
 GROUP BY legislative_item_id
 HAVING COUNT(*)>1
) x

UNION ALL
SELECT 'ORLMS item without any official revision snapshot',COUNT(*)
FROM legislative_items li
JOIN legislative_item_types lit ON lit.id=li.item_type_id
WHERE li.deleted_at IS NULL
 AND lit.code IN ('ordinance','resolution')
 AND NOT EXISTS(
   SELECT 1 FROM orlms_revision_history rh
   WHERE rh.legislative_item_id=li.id
 )

UNION ALL
SELECT 'Applied amendment without resulting revision snapshot',COUNT(*)
FROM orlms_amendments a
WHERE a.status='Applied'
 AND NOT EXISTS(
   SELECT 1 FROM orlms_revision_history rh
   WHERE rh.amendment_id=a.id
 )

UNION ALL
SELECT 'Applied amendment revision not marked current',COUNT(*)
FROM orlms_amendments a
JOIN orlms_revision_history rh ON rh.amendment_id=a.id
WHERE a.status='Applied'
 AND rh.is_current<>1

UNION ALL
SELECT 'Current revision text differs from current ORLMS body',COUNT(*)
FROM orlms_revision_history rh
JOIN orlms_item_details d ON d.legislative_item_id=rh.legislative_item_id
WHERE rh.is_current=1
 AND COALESCE(rh.body_snapshot,'')<>COALESCE(d.body_text,'')

UNION ALL
SELECT 'Active amendment locked to stale source revision',COUNT(*)
FROM orlms_amendments a
JOIN orlms_revision_history rh
 ON rh.legislative_item_id=a.legislative_item_id
AND rh.is_current=1
WHERE a.status IN ('Draft','Under Review','Revision Required','Approved')
 AND a.source_revision_version<>rh.version_number

UNION ALL
SELECT 'Committee review without source legislative item',COUNT(*)
FROM orlms_reviews r
LEFT JOIN legislative_items li ON li.id=r.legislative_item_id
WHERE li.id IS NULL

UNION ALL
SELECT 'Endorsement without review',COUNT(*)
FROM orlms_endorsements e
LEFT JOIN orlms_reviews r ON r.id=e.review_id
WHERE r.id IS NULL

UNION ALL
SELECT 'Publication dissemination recipient without publication',COUNT(*)
FROM orlms_dissemination_recipients r
LEFT JOIN orlms_publications p ON p.id=r.publication_id
WHERE p.id IS NULL

UNION ALL
SELECT 'Implementation update without plan',COUNT(*)
FROM orlms_implementation_updates u
LEFT JOIN orlms_implementation_plans p ON p.id=u.implementation_plan_id
WHERE p.id IS NULL;

SELECT '5. OPERATIONAL WATCH LISTS' section_name;

SELECT r.id,r.review_round,r.status,li.reference_number,li.title,c.name committee_name
FROM orlms_reviews r
JOIN legislative_items li ON li.id=r.legislative_item_id
LEFT JOIN committees c ON c.id=r.committee_id
WHERE r.status IN ('Pending','In Progress')
ORDER BY r.created_at;

SELECT a.action_reference,a.action_type,a.action_status,
       li.reference_number,li.title
FROM orlms_approval_actions a
JOIN legislative_items li ON li.id=a.legislative_item_id
WHERE a.action_status IN ('Pending','Returned')
ORDER BY a.created_at;

SELECT p.publication_reference,p.publication_date,p.publication_channel,
       li.reference_number,li.title
FROM orlms_publications p
JOIN legislative_items li ON li.id=p.legislative_item_id
WHERE p.publication_status='Scheduled'
ORDER BY p.publication_date;

SELECT p.plan_reference,p.due_date,p.compliance_percent,p.status,
       li.reference_number,li.title
FROM orlms_implementation_plans p
JOIN legislative_items li ON li.id=p.legislative_item_id
WHERE p.due_date<CURDATE()
  AND p.status NOT IN ('Completed','Cancelled')
ORDER BY p.due_date;

SELECT a.amendment_reference,a.status,a.source_revision_version,
       rh.version_number current_revision,li.reference_number,li.title
FROM orlms_amendments a
JOIN legislative_items li ON li.id=a.legislative_item_id
LEFT JOIN orlms_revision_history rh
  ON rh.legislative_item_id=a.legislative_item_id
 AND rh.is_current=1
WHERE a.status IN ('Draft','Under Review','Revision Required','Approved')
ORDER BY a.updated_at DESC;

SELECT '6. SECURITY / AUDIT SUMMARY' section_name;

SELECT COUNT(*) recent_failed_login_attempts_24h
FROM orlms_login_attempts
WHERE was_successful=0
  AND attempted_at>=DATE_SUB(NOW(),INTERVAL 24 HOUR);

SELECT COUNT(*) recent_permission_denials_30d
FROM activity_logs
WHERE system_id=(SELECT id FROM systems WHERE code='ordinance' LIMIT 1)
  AND action='ORLMS Permission Denied'
  AND created_at>=DATE_SUB(NOW(),INTERVAL 30 DAY);

SELECT COUNT(*) recent_orlms_activity_30d
FROM activity_logs
WHERE system_id=(SELECT id FROM systems WHERE code='ordinance' LIMIT 1)
  AND created_at>=DATE_SUB(NOW(),INTERVAL 30 DAY);
