<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
requireRole([ROLE_ADMIN,ROLE_STAFF]);

$pageTitle='Database Readiness';
$activeMenu='readiness';
$extraCss=[appUrl('assets/css/readiness.css')];
$pdo=db();

$sharedTables=[
    'users','roles','systems','user_system_access',
    'legislative_items','legislative_item_types',
    'committees','committee_members','offices','activity_logs'
];

$vqdTables=[
    'vqd_schema_migrations','vqd_sessions','vqd_session_members','vqd_session_documents',
    'vqd_quorum_checks','vqd_quorum_check_members','vqd_quorum_documents','vqd_quorum_history',
    'vqd_voting_events','vqd_vote_options','vqd_voting_event_voters','vqd_votes','vqd_voting_documents','vqd_voting_history',
    'vqd_vote_tallies','vqd_vote_tally_options','vqd_tally_recounts','vqd_tally_documents','vqd_tally_history',
    'vqd_decisions','vqd_decision_actions','vqd_decision_notifications','vqd_decision_documents','vqd_decision_history',
    'vqd_result_validations','vqd_validation_checks','vqd_validation_findings','vqd_validation_reports','vqd_validation_documents','vqd_validation_history'
];

$checks=[];
foreach($sharedTables as $table){
    $ok=tableExists($table);
    $checks[]=['status'=>$ok?'pass':'fail','name'=>'Shared: '.$table,'message'=>$ok?'Available in the shared legislative database.':'Required shared table is missing.'];
}
foreach($vqdTables as $table){
    $ok=tableExists($table);
    $checks[]=['status'=>$ok?'pass':'fail','name'=>'VQDSS: '.$table,'message'=>$ok?'Dedicated backend table is installed.':'Run migration_001_vqdss_foundation.sql.'];
}

$system=null;
if(tableExists('systems')){
    $s=$pdo->query("SELECT id,code,name,base_url,status FROM systems WHERE code='voting' LIMIT 1");
    $system=$s->fetch();
}
$checks[]=[
    'status'=>$system && $system['status']==='Active'?'pass':'fail',
    'name'=>'Shared system registry',
    'message'=>$system
        ? 'System #'.$system['id'].' · '.$system['status'].' · '.($system['base_url']?:'No base URL')
        : 'The voting system registry row was not found.'
];

$pass=count(array_filter($checks,fn($c)=>$c['status']==='pass'));
$total=max(1,count($checks));
$score=(int)round(($pass/$total)*100);

$migrations=[];
if(tableExists('vqd_schema_migrations')){
    $migrations=$pdo->query('SELECT migration_key,description,applied_at FROM vqd_schema_migrations ORDER BY applied_at,migration_key')->fetchAll();
}

include __DIR__ . '/../layouts/header.php';
?>
<div class="app-wrapper">
<?php include __DIR__ . '/../layouts/sidebar.php'; ?>
<main class="main-content">
<section class="readiness-head"><div><div class="readiness-eyebrow"><i class="bi bi-database-check"></i> Step 1 · Source / Database Alignment</div><h1>VQDSS Database Readiness</h1><p>Read-only verification of the latest shared database and the dedicated voting, quorum, tally, decision, and validation foundation.</p></div><a class="btn btn-outline-secondary" href="<?= e(appUrl('dashboard.php')) ?>"><i class="bi bi-speedometer2"></i> Dashboard</a></section>

<div class="readiness-score"><strong><?= $score ?>%</strong><div><div class="fw-semibold">Foundation Readiness</div><span><?= $pass ?> of <?= $total ?> checks passed. Run the Step 1 migration until the dedicated VQDSS checks are available.</span></div></div>

<div class="readiness-grid mb-4">
<?php foreach($checks as $check): ?><div class="readiness-item <?= e($check['status']) ?>"><strong><i class="bi <?= $check['status']==='pass'?'bi-check-circle text-success':'bi-x-circle text-danger' ?>"></i> <?= e($check['name']) ?></strong><small><?= e($check['message']) ?></small></div><?php endforeach; ?>
</div>

<section class="card border-0 shadow-sm"><div class="card-header bg-dark text-white border-bottom border-warning border-3"><strong><i class="bi bi-clock-history"></i> Applied VQDSS Migrations</strong></div><div class="card-body">
<?php if(!$migrations): ?><div class="text-muted">No VQDSS migration records found yet.</div><?php endif; ?>
<?php foreach($migrations as $m): ?><div class="p-2 mb-2 bg-light rounded"><strong class="small"><?= e($m['migration_key']) ?></strong><div class="small text-muted"><?= e($m['description']?:'') ?> · <?= e(formatDateTime($m['applied_at'])) ?></div></div><?php endforeach; ?>
</div></section>

</main></div>
<?php include __DIR__ . '/../layouts/footer.php'; ?>
