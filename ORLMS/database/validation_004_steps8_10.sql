USE `legislative_management_db`;

-- ============================================================
-- ORLMS STEPS 8-10 READ-ONLY VALIDATION
-- No records are modified by this file.
-- ============================================================

SELECT 'MIGRATION CHECK' AS section_name;
SELECT migration_key,description,applied_at
FROM orlms_schema_migrations
ORDER BY applied_at,migration_key;

SELECT 'AMENDMENT / REVISION INTEGRITY - EXPECT 0 PROBLEM ROWS' AS section_name;

SELECT 'Legislative items with multiple current official revisions' check_name,COUNT(*) problem_rows
FROM (
    SELECT legislative_item_id
    FROM orlms_revision_history
    WHERE is_current=1
    GROUP BY legislative_item_id
    HAVING COUNT(*)>1
) x

UNION ALL
SELECT 'Active amendments whose locked source revision is no longer current',COUNT(*)
FROM orlms_amendments a
JOIN orlms_revision_history rh
  ON rh.legislative_item_id=a.legislative_item_id
 AND rh.is_current=1
WHERE a.status IN ('Draft','Under Review','Revision Required','Approved')
  AND a.source_revision_version<>rh.version_number

UNION ALL
SELECT 'Applied amendments without resulting revision record',COUNT(*)
FROM orlms_amendments a
WHERE a.status='Applied'
  AND NOT EXISTS(
      SELECT 1 FROM orlms_revision_history rh
      WHERE rh.amendment_id=a.id
  )

UNION ALL
SELECT 'Applied amendment revision is not current',COUNT(*)
FROM orlms_amendments a
JOIN orlms_revision_history rh ON rh.amendment_id=a.id
WHERE a.status='Applied'
  AND rh.is_current<>1

UNION ALL
SELECT 'Current revision text differs from ORLMS current body text',COUNT(*)
FROM orlms_revision_history rh
JOIN orlms_item_details d ON d.legislative_item_id=rh.legislative_item_id
WHERE rh.is_current=1
  AND COALESCE(rh.body_snapshot,'')<>COALESCE(d.body_text,'')

UNION ALL
SELECT 'Implementation plans without any Published publication',COUNT(*)
FROM orlms_implementation_plans ip
WHERE NOT EXISTS(
    SELECT 1 FROM orlms_publications p
    WHERE p.legislative_item_id=ip.legislative_item_id
      AND p.publication_status='Published'
)

UNION ALL
SELECT 'Published records whose enactment is not finalized',COUNT(*)
FROM orlms_publications p
LEFT JOIN orlms_enactments en ON en.legislative_item_id=p.legislative_item_id
WHERE p.publication_status='Published'
  AND (en.id IS NULL OR en.enactment_status<>'Finalized');

SELECT 'ORLMS SHARED ACCESS SUMMARY' AS section_name;
SELECT COALESCE(usa.status,'Legacy') access_status,COUNT(*) total
FROM users u
LEFT JOIN user_system_access usa
  ON usa.user_id=u.id
 AND usa.system_id=(SELECT id FROM systems WHERE code='ordinance' LIMIT 1)
WHERE u.deleted_at IS NULL
GROUP BY COALESCE(usa.status,'Legacy');

SELECT 'ORLMS ADMIN ACCESS - SHOULD BE AT LEAST 1' AS section_name;
SELECT COUNT(*) active_orlms_administrators
FROM users u
JOIN roles r ON r.id=u.role_id
LEFT JOIN user_system_access usa
  ON usa.user_id=u.id
 AND usa.system_id=(SELECT id FROM systems WHERE code='ordinance' LIMIT 1)
WHERE r.name='Administrator'
  AND u.status='Active'
  AND u.deleted_at IS NULL
  AND COALESCE(usa.status,'Active')='Active';

SELECT 'OPERATIONAL WATCH LISTS' AS section_name;

SELECT a.amendment_reference,li.reference_number,a.title,a.status,
       a.source_revision_version,rh.version_number current_revision
FROM orlms_amendments a
JOIN legislative_items li ON li.id=a.legislative_item_id
LEFT JOIN orlms_revision_history rh
  ON rh.legislative_item_id=a.legislative_item_id
 AND rh.is_current=1
WHERE a.status IN ('Draft','Under Review','Revision Required','Approved')
ORDER BY a.updated_at DESC;

SELECT p.plan_reference,li.reference_number,p.objective,p.due_date,
       p.compliance_percent,p.status
FROM orlms_implementation_plans p
JOIN legislative_items li ON li.id=p.legislative_item_id
WHERE p.due_date<CURDATE()
  AND p.status NOT IN ('Completed','Cancelled')
ORDER BY p.due_date;

SELECT p.publication_reference,li.reference_number,p.publication_date,
       p.publication_channel,p.publication_status
FROM orlms_publications p
JOIN legislative_items li ON li.id=p.legislative_item_id
WHERE p.publication_status='Scheduled'
ORDER BY p.publication_date;
