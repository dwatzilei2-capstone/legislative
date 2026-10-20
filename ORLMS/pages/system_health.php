<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/orlms_amendment_helpers.php';
requirePermission('orlms.system_health.view');

$pdo=db();$pageTitle='ORLMS System Health';$activeMenu='system_health';
$extraCss=[APP_URL.'/assets/css/orlms-final-ops.css'];

$checks=[];
$add=function(string $group,string $name,string $status,string $details) use (&$checks): void {
    $checks[]=['group'=>$group,'name'=>$name,'status'=>$status,'details'=>$details];
};

$requiredTables=[
 'systems','users','roles','user_roles','user_system_access','permissions','role_permissions',
 'activity_logs','notifications','committees','committee_members','offices','departments',
 'legislative_items','legislative_item_types','legislative_item_status_history','documents',
 'orlms_schema_migrations','orlms_templates','orlms_item_details','orlms_item_authors',
 'orlms_draft_versions','orlms_workflow_history','orlms_reviews','orlms_review_checklist',
 'orlms_review_comments','orlms_endorsements','orlms_approval_actions','orlms_approval_history',
 'orlms_enactments','orlms_enactment_history','orlms_publications','orlms_publication_history',
 'orlms_dissemination_recipients','orlms_publication_documents',
 'orlms_implementation_plans','orlms_implementation_updates','orlms_implementation_history',
 'orlms_implementation_documents','orlms_implementation_issues',
 'orlms_amendments','orlms_amendment_changes','orlms_amendment_history',
 'orlms_amendment_documents','orlms_revision_history','orlms_login_attempts'
];

foreach($requiredTables as $table){
    $ok=orlmsTableExists($table);
    $add('Database Objects',$table,$ok?'PASS':'FAIL',$ok?'Available':'Missing required table.');
}

$requiredColumns=[
 ['orlms_enactments','enactment_status'],
 ['orlms_enactments','finalized_by'],
 ['orlms_enactments','finalized_at'],
 ['orlms_amendments','source_revision_version'],
 ['orlms_amendments','revised_body_text'],
 ['orlms_amendments','applied_at'],
 ['orlms_dissemination_recipients','delivery_notes'],
];
foreach($requiredColumns as [$table,$column]){
    $q=$pdo->prepare(
      'SELECT COUNT(*) FROM information_schema.columns
       WHERE table_schema=DATABASE() AND table_name=:table AND column_name=:column'
    );
    $q->execute([':table'=>$table,':column'=>$column]);
    $ok=(int)$q->fetchColumn()>0;
    $add('Required Columns',$table.'.'.$column,$ok?'PASS':'FAIL',$ok?'Available':'Missing required column.');
}

$expectedMigrations=[
 '001_orlms_foundation',
 '002_drafting_review_complete',
 '003_approval_publication_implementation',
 '004_amendment_revision_admin',
 '005_final_rbac_security'
];
$applied=[];
if(orlmsTableExists('orlms_schema_migrations')){
    $applied=$pdo->query(
      'SELECT migration_key,description,applied_at
       FROM orlms_schema_migrations ORDER BY applied_at,migration_key'
    )->fetchAll();
}
$appliedKeys=array_column($applied,'migration_key');
foreach($expectedMigrations as $key){
    $ok=in_array($key,$appliedKeys,true);
    $add('Migrations',$key,$ok?'PASS':'FAIL',$ok?'Recorded as applied.':'Migration is not recorded.');
}

$system=$pdo->query("SELECT * FROM systems WHERE code='ordinance' LIMIT 1")->fetch();
$systemOk=$system && strcasecmp((string)$system['status'],'Active')===0;
$add('Configuration','Shared ORLMS system registration',$systemOk?'PASS':'FAIL',
    $system ? ('ID '.(int)$system['id'].' · '.$system['status'].' · '.($system['base_url']?:'no base URL')) : 'systems.code=ordinance is missing.');

$permissionCount=(int)$pdo->query(
 "SELECT COUNT(*) FROM permissions p
  JOIN systems s ON s.id=p.system_id
  WHERE s.code='ordinance' AND p.code LIKE 'orlms.%'"
)->fetchColumn();
$add('RBAC','Fine-grained ORLMS permissions',$permissionCount>=21?'PASS':'FAIL',
    $permissionCount.' ORLMS permission code(s) found; expected at least 21.');

$roleRows=$pdo->query(
 "SELECT r.name,COUNT(p.id) permission_count
  FROM roles r
  LEFT JOIN role_permissions rp ON rp.role_id=r.id
  LEFT JOIN permissions p ON p.id=rp.permission_id
    AND p.system_id=(SELECT id FROM systems WHERE code='ordinance' LIMIT 1)
    AND p.code LIKE 'orlms.%'
  GROUP BY r.id,r.name ORDER BY r.id"
)->fetchAll();

$activeUsers=(int)$pdo->query("SELECT COUNT(*) FROM users WHERE deleted_at IS NULL")->fetchColumn();
$explicitAccess=(int)$pdo->query(
 "SELECT COUNT(DISTINCT u.id)
  FROM users u
  JOIN user_system_access usa
    ON usa.user_id=u.id
   AND usa.system_id=(SELECT id FROM systems WHERE code='ordinance' LIMIT 1)
  WHERE u.deleted_at IS NULL"
)->fetchColumn();
$add('RBAC','Explicit ORLMS access rows',
    $explicitAccess===$activeUsers?'PASS':'WARN',
    "{$explicitAccess} of {$activeUsers} non-deleted shared users have an explicit ORLMS access row.");

$activeAdmins=(int)$pdo->query(
 "SELECT COUNT(*)
  FROM users u
  JOIN roles r ON r.id=u.role_id
  JOIN user_system_access usa
    ON usa.user_id=u.id
   AND usa.system_id=(SELECT id FROM systems WHERE code='ordinance' LIMIT 1)
  WHERE r.name='Administrator'
    AND u.status='Active'
    AND u.deleted_at IS NULL
    AND usa.status='Active'"
)->fetchColumn();
$add('RBAC','ORLMS-accessible Administrator',
    $activeAdmins>=1?'PASS':'FAIL',
    $activeAdmins.' active Administrator account(s) currently have ORLMS access.');

$uploadWritable=is_dir(UPLOAD_DIR)&&is_writable(UPLOAD_DIR);
$add('Filesystem','Uploads directory',$uploadWritable?'PASS':'FAIL',
    UPLOAD_DIR.($uploadWritable?' is writable.':' is missing or not writable.'));

$logDir=dirname((string)ini_get('error_log'));
$logWritable=is_dir($logDir)&&is_writable($logDir);
$add('Filesystem','PHP log directory',$logWritable?'PASS':'WARN',
    $logDir.($logWritable?' is writable.':' is missing or not writable by PHP.'));

$add('Configuration','APP_DEBUG',APP_DEBUG?'WARN':'PASS',
    APP_DEBUG?'APP_DEBUG is enabled. Keep this only for local development.':'APP_DEBUG is disabled.');

$add('Configuration','Shared session',
    SESSION_NAME==='lph_session'?'PASS':'WARN',
    'Session name: '.SESSION_NAME.' · idle lifetime: '.round(SESSION_LIFETIME/3600,1).' hours · cookie path is /.');

$integrityQueries=[
 'Duplicate legislative references'=>
   "SELECT COUNT(*) FROM (
      SELECT reference_number FROM legislative_items
      WHERE deleted_at IS NULL
      GROUP BY reference_number HAVING COUNT(*)>1
    ) x",
 'Multiple current official revisions'=>
   "SELECT COUNT(*) FROM (
      SELECT legislative_item_id FROM orlms_revision_history
      WHERE is_current=1 GROUP BY legislative_item_id HAVING COUNT(*)>1
    ) x",
 'Applied amendments without revision snapshot'=>
   "SELECT COUNT(*) FROM orlms_amendments a
    WHERE a.status='Applied'
      AND NOT EXISTS(SELECT 1 FROM orlms_revision_history rh WHERE rh.amendment_id=a.id)",
 'Published releases without finalized enactment'=>
   "SELECT COUNT(*) FROM orlms_publications p
    LEFT JOIN orlms_enactments e ON e.legislative_item_id=p.legislative_item_id
    WHERE p.publication_status='Published'
      AND (e.id IS NULL OR e.enactment_status<>'Finalized')",
 'Implementation plans without published release'=>
   "SELECT COUNT(*) FROM orlms_implementation_plans ip
    WHERE NOT EXISTS(
      SELECT 1 FROM orlms_publications p
      WHERE p.legislative_item_id=ip.legislative_item_id
        AND p.publication_status='Published'
    )",
 'Completed plans with unresolved High/Critical risk'=>
   "SELECT COUNT(*) FROM orlms_implementation_plans ip
    WHERE ip.status='Completed'
      AND EXISTS(
        SELECT 1 FROM orlms_implementation_issues ii
        WHERE ii.implementation_plan_id=ip.id
          AND ii.severity IN ('High','Critical')
          AND ii.status NOT IN ('Resolved','Accepted Risk')
      )",
 'Duplicate pending approval actions per item'=>
   "SELECT COUNT(*) FROM (
      SELECT legislative_item_id
      FROM orlms_approval_actions
      WHERE action_status='Pending'
      GROUP BY legislative_item_id HAVING COUNT(*)>1
    ) x",
 'Stale active amendment sources'=>
   "SELECT COUNT(*)
    FROM orlms_amendments a
    JOIN orlms_revision_history rh
      ON rh.legislative_item_id=a.legislative_item_id
     AND rh.is_current=1
    WHERE a.status IN ('Draft','Under Review','Revision Required','Approved')
      AND a.source_revision_version<>rh.version_number",
];

foreach($integrityQueries as $label=>$sql){
    try{
        $count=(int)$pdo->query($sql)->fetchColumn();
        $add('Data Integrity',$label,$count===0?'PASS':'FAIL',
            $count===0?'No integrity problems detected.':$count.' problem row(s) detected.');
    }catch(Throwable $e){
        $add('Data Integrity',$label,'FAIL','Health query failed: '.$e->getMessage());
    }
}

$statusWeights=['PASS'=>1,'WARN'=>0.5,'FAIL'=>0];
$earned=0;
foreach($checks as $c)$earned+=$statusWeights[$c['status']]??0;
$score=(int)round(($earned/max(1,count($checks)))*100);
$failCount=count(array_filter($checks,fn($c)=>$c['status']==='FAIL'));
$warnCount=count(array_filter($checks,fn($c)=>$c['status']==='WARN'));

include __DIR__ . '/../layouts/header.php';
?>
<div class="orlms-app-layout"><?php include __DIR__ . '/../layouts/sidebar.php'; ?><main class="orlms-main-content">
<div class="of-head"><div><div class="of-eyebrow"><i class="bi bi-heart-pulse"></i> Step 12 · Deployment & Integrity Health</div><h1>ORLMS System Health</h1><p>Checks schema migrations, fine-grained RBAC, shared access, filesystem readiness, security configuration and cross-module legislative data integrity.</p></div><div class="text-end"><div class="display-6 fw-bold <?= $failCount?'text-danger':($warnCount?'text-warning':'text-success') ?>"><?= $score ?>%</div><div class="small text-muted"><?= $failCount ?> failure(s) · <?= $warnCount ?> warning(s)</div></div></div>

<div class="row g-3 mb-3">
<?php foreach([
 ['Checks',count($checks),'bi-list-check'],
 ['Passed',count(array_filter($checks,fn($c)=>$c['status']==='PASS')),'bi-check-circle'],
 ['Warnings',$warnCount,'bi-exclamation-triangle'],
 ['Failures',$failCount,'bi-x-octagon'],
] as [$l,$v,$i]): ?><div class="col-6 col-lg-3"><div class="of-stat"><i class="bi <?= e($i) ?>"></i><div><strong><?= (int)$v ?></strong><small><?= e($l) ?></small></div></div></div><?php endforeach; ?>
</div>

<?php foreach(array_values(array_unique(array_column($checks,'group'))) as $group): ?>
<div class="card of-card mb-3"><div class="card-header"><?= e($group) ?></div><div class="table-responsive"><table class="table of-table mb-0"><thead><tr><th>Check</th><th>Status</th><th>Details</th></tr></thead><tbody>
<?php foreach(array_filter($checks,fn($c)=>$c['group']===$group) as $c): ?><tr><td><strong><?= e($c['name']) ?></strong></td><td><span class="badge text-bg-<?= $c['status']==='PASS'?'success':($c['status']==='WARN'?'warning':'danger') ?>"><?= e($c['status']) ?></span></td><td><?= e($c['details']) ?></td></tr><?php endforeach; ?>
</tbody></table></div></div>
<?php endforeach; ?>

<div class="card of-card"><div class="card-header">ORLMS Role Permission Matrix</div><div class="table-responsive"><table class="table of-table mb-0"><thead><tr><th>Role</th><th>Fine-Grained ORLMS Permissions</th></tr></thead><tbody><?php foreach($roleRows as $r): ?><tr><td><?= e($r['name']) ?></td><td><?= (int)$r['permission_count'] ?></td></tr><?php endforeach; ?></tbody></table></div></div>
</main></div>
<?php include __DIR__ . '/../layouts/footer.php'; ?>
