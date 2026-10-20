<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
requirePermission('vqd.system_health.view');

$pdo=db();$pageTitle='System Health';$activeMenu='system_health';
$extraCss=[appUrl('assets/css/vqdss-admin-final.css')];

function vqdHealthTable(PDO $pdo,string $table): bool {
    $s=$pdo->prepare(
      'SELECT COUNT(*) FROM information_schema.tables
       WHERE table_schema=DATABASE() AND table_name=:table'
    );
    $s->execute([':table'=>$table]);
    return (int)$s->fetchColumn()>0;
}

function vqdHealthColumn(PDO $pdo,string $table,string $column): bool {
    $s=$pdo->prepare(
      'SELECT COUNT(*) FROM information_schema.columns
       WHERE table_schema=DATABASE() AND table_name=:table AND column_name=:column'
    );
    $s->execute([':table'=>$table,':column'=>$column]);
    return (int)$s->fetchColumn()>0;
}

$checks=[];

$requiredTables=[
 'vqd_schema_migrations',
 'vqd_sessions','vqd_session_members','vqd_session_documents','vqd_session_history',
 'vqd_quorum_checks','vqd_quorum_check_members','vqd_quorum_documents','vqd_quorum_history',
 'vqd_voting_events','vqd_vote_options','vqd_voting_event_voters','vqd_votes',
 'vqd_voting_documents','vqd_voting_history','vqd_ballot_challenges',
 'vqd_vote_tallies','vqd_vote_tally_options','vqd_tally_recounts',
 'vqd_tally_documents','vqd_tally_history',
 'vqd_decisions','vqd_decision_actions','vqd_decision_action_history',
 'vqd_decision_notifications','vqd_decision_documents','vqd_decision_history',
 'vqd_result_validations','vqd_validation_checks','vqd_validation_findings',
 'vqd_validation_reports','vqd_validation_documents','vqd_validation_history',
 'vqd_login_attempts', 'vqd_ai_analysis'
];

foreach($requiredTables as $table){
    $ok=vqdHealthTable($pdo,$table);
    $checks[]=[
      'status'=>$ok?'pass':'fail',
      'name'=>'Table: '.$table,
      'message'=>$ok?'Available':'Missing — run the required VQDSS migration.'
    ];
}

$columns=[
 ['vqd_sessions','reference_number'],
 ['vqd_quorum_checks','verification_status'],
 ['vqd_voting_events','decision_threshold'],
 ['vqd_votes','ballot_reference'],
 ['vqd_vote_tallies','decision_handoff'],
 ['vqd_decisions','decision_record_number'],
 ['vqd_result_validations','integrity_score'],
 ['activity_logs','system_id'],
 ['users','deleted_at'],
];

foreach($columns as [$table,$column]){
    $ok=vqdHealthColumn($pdo,$table,$column);
    $checks[]=[
      'status'=>$ok?'pass':'fail',
      'name'=>"Column: {$table}.{$column}",
      'message'=>$ok?'Available':'Missing final schema field.'
    ];
}

$migrations=[];
if(vqdHealthTable($pdo,'vqd_schema_migrations')){
    $migrations=$pdo->query(
      'SELECT migration_key,description,applied_at
       FROM vqd_schema_migrations
       ORDER BY applied_at,migration_key'
    )->fetchAll();
}

$expected=[
 '001_vqdss_foundation',
 '002_quorum_voting_tally_complete',
 '003_decisions_validation_reports',
 '004_vqdss_security_rbac_final'
];

$applied=array_column($migrations,'migration_key');
foreach($expected as $migration){
    $ok=in_array($migration,$applied,true);
    $checks[]=[
      'status'=>$ok?'pass':'fail',
      'name'=>'Migration: '.$migration,
      'message'=>$ok?'Recorded as applied':'Not recorded in vqd_schema_migrations.'
    ];
}

$permCount=(int)$pdo->query("SELECT COUNT(*) FROM permissions WHERE code LIKE 'vqd.%'")->fetchColumn();
$checks[]=[
 'status'=>$permCount>=23?'pass':'warn',
 'name'=>'VQDSS permission matrix',
 'message'=>"{$permCount} fine-grained VQDSS permission(s) found."
];

$q=$pdo->prepare(
 "SELECT status,base_url FROM systems WHERE code='voting' LIMIT 1"
);
$q->execute();$system=$q->fetch();
$checks[]=[
 'status'=>($system&&$system['status']==='Active')?'pass':'warn',
 'name'=>'Shared systems registry',
 'message'=>$system
   ? "Voting system status={$system['status']}; base URL=".($system['base_url']?:'not set')
   : 'Voting system row is missing.'
];

$q=$pdo->prepare(
 "SELECT COUNT(*) FROM user_system_access
  WHERE system_id=:system AND status='Active'"
);
$q->execute([':system'=>vqdSystemId()]);
$accessRows=(int)$q->fetchColumn();
$checks[]=[
 'status'=>$accessRows>0?'pass':'warn',
 'name'=>'Explicit VQDSS user access',
 'message'=>"{$accessRows} active user-system access record(s)."
];

require_once __DIR__ . '/../includes/vqdss_operational_helpers.php';
try {
    $aiHealth = vqdssAiService()->healthCheck();
    $checks[] = [
        'status' => $aiHealth['available'] && $aiHealth['model_installed'] ? 'pass' : ($aiHealth['available'] ? 'warn' : 'fail'),
        'name' => 'Ollama Local AI Daemon & Model (' . OLLAMA_MODEL . ')',
        'message' => $aiHealth['message'] . ' [Base: ' . OLLAMA_BASE_URL . ']',
    ];
} catch (Throwable $e) {
    $checks[] = [
        'status' => 'warn',
        'name' => 'Ollama Local AI Service',
        'message' => 'Check error: ' . $e->getMessage(),
    ];
}


$uploadDir=dirname(__DIR__).'/assets/uploads';
if(!is_dir($uploadDir))@mkdir($uploadDir,0775,true);
$checks[]=[
 'status'=>(is_dir($uploadDir)&&is_writable($uploadDir))?'pass':'warn',
 'name'=>'Uploads directory',
 'message'=>(is_dir($uploadDir)&&is_writable($uploadDir))
    ? 'Writable: '.$uploadDir
    : 'Directory is not writable: '.$uploadDir
];

$debug=defined('APP_DEBUG')&&APP_DEBUG;
$checks[]=[
 'status'=>$debug?'warn':'pass',
 'name'=>'Production error display',
 'message'=>$debug
    ? 'APP_DEBUG is TRUE. This is acceptable for local development but should be FALSE in production.'
    : 'APP_DEBUG is disabled.'
];

$integrity=[];
$integrityQueries=[
 'Sessions without roster'=>
   "SELECT COUNT(*) FROM vqd_sessions s
    WHERE s.status<>'Cancelled'
      AND NOT EXISTS(SELECT 1 FROM vqd_session_members sm WHERE sm.session_id=s.id)",
 'Certified quorum not Met'=>
   "SELECT COUNT(*) FROM vqd_quorum_checks
    WHERE verification_status='Certified' AND quorum_result<>'Met'",
 'Duplicate voter ballots'=>
   "SELECT COUNT(*) FROM (
      SELECT voting_event_id,event_voter_id FROM vqd_votes
      GROUP BY voting_event_id,event_voter_id HAVING COUNT(*)>1
    ) x",
 'Certified tally arithmetic mismatch'=>
   "SELECT COUNT(*) FROM vqd_vote_tallies
    WHERE tally_status='Certified'
      AND votes_cast<>(valid_votes+invalid_ballots)",
 'Decision/tally mismatch'=>
   "SELECT COUNT(*)
    FROM vqd_decisions d JOIN vqd_vote_tallies t ON t.id=d.tally_id
    WHERE d.record_status IN ('Certified','Published')
      AND (d.required_votes<>t.required_votes
       OR d.affirmative_votes<>t.affirmative_votes
       OR d.negative_votes<>t.negative_votes
       OR d.abstain_votes<>t.abstain_votes
       OR d.official_outcome<>t.result_outcome)",
 'Certified validation unresolved high risk'=>
   "SELECT COUNT(*)
    FROM vqd_result_validations rv
    WHERE rv.validation_status='Certified'
      AND EXISTS(
        SELECT 1 FROM vqd_validation_findings f
        WHERE f.validation_id=rv.id
          AND f.status<>'Resolved'
          AND f.severity IN ('High','Critical')
      )",
];

foreach($integrityQueries as $name=>$sql){
    try{
        $value=(int)$pdo->query($sql)->fetchColumn();
        $integrity[]=['name'=>$name,'value'=>$value];
    }catch(Throwable $e){
        $integrity[]=['name'=>$name,'value'=>-1];
        $checks[]=['status'=>'warn','name'=>'Integrity check: '.$name,'message'=>'Could not execute: '.$e->getMessage()];
    }
}

$pass=count(array_filter($checks,fn($c)=>$c['status']==='pass'));
$warn=count(array_filter($checks,fn($c)=>$c['status']==='warn'));
$fail=count(array_filter($checks,fn($c)=>$c['status']==='fail'));
$total=max(1,count($checks));
$score=(int)round((($pass+($warn*.5))/$total)*100);

$counts=[
 'sessions'=>vqdHealthTable($pdo,'vqd_sessions')?(int)$pdo->query('SELECT COUNT(*) FROM vqd_sessions')->fetchColumn():0,
 'quorum'=>vqdHealthTable($pdo,'vqd_quorum_checks')?(int)$pdo->query("SELECT COUNT(*) FROM vqd_quorum_checks WHERE verification_status='Certified'")->fetchColumn():0,
 'votes'=>vqdHealthTable($pdo,'vqd_votes')?(int)$pdo->query('SELECT COUNT(*) FROM vqd_votes')->fetchColumn():0,
 'tallies'=>vqdHealthTable($pdo,'vqd_vote_tallies')?(int)$pdo->query("SELECT COUNT(*) FROM vqd_vote_tallies WHERE tally_status='Certified'")->fetchColumn():0,
 'decisions'=>vqdHealthTable($pdo,'vqd_decisions')?(int)$pdo->query("SELECT COUNT(*) FROM vqd_decisions WHERE record_status IN ('Certified','Published')")->fetchColumn():0,
 'validations'=>vqdHealthTable($pdo,'vqd_result_validations')?(int)$pdo->query("SELECT COUNT(*) FROM vqd_result_validations WHERE validation_status='Certified'")->fetchColumn():0,
];

include __DIR__.'/../layouts/header.php';
?>
<div class="app-wrapper"><?php include __DIR__.'/../layouts/sidebar.php'; ?><main class="main-content">

<div class="vqda-head"><div><div class="vqda-eyebrow"><i class="bi bi-heart-pulse"></i> Step 10 · Final Integration</div><h1>VQDSS System Health & Readiness</h1><p>Read-only validation of the final VQDSS database objects, migrations, access controls, operational integrity and environment readiness.</p></div></div>

<div class="vqda-score mb-3"><strong><?= $score ?>%</strong><div><div class="fw-semibold">Readiness Score</div><span><?= $pass ?> PASS · <?= $warn ?> WARNING · <?= $fail ?> FAIL checks. Local-development warnings may be expected.</span></div></div>

<div class="row g-3 mb-3"><?php foreach([
 ['Sessions',$counts['sessions'],'bi-calendar3'],['Certified Quorum',$counts['quorum'],'bi-person-check'],
 ['Ballots',$counts['votes'],'bi-check2-square'],['Certified Tallies',$counts['tallies'],'bi-calculator'],
 ['Official Decisions',$counts['decisions'],'bi-journal-check'],['Certified Validations',$counts['validations'],'bi-patch-check'],
] as [$l,$v,$i]): ?><div class="col-6 col-xl-2"><div class="vqda-stat"><i class="bi <?= e($i) ?>"></i><div><strong><?= $v ?></strong><small><?= e($l) ?></small></div></div></div><?php endforeach; ?></div>

<div class="card vqda-card mb-3"><div class="card-header">System Checks</div><div class="card-body"><div class="vqda-health-grid"><?php foreach($checks as $c): ?><div class="vqda-health <?= e($c['status']) ?>"><strong><?php if($c['status']==='pass'): ?><i class="bi bi-check-circle text-success"></i><?php elseif($c['status']==='warn'): ?><i class="bi bi-exclamation-triangle text-warning"></i><?php else: ?><i class="bi bi-x-circle text-danger"></i><?php endif; ?> <?= e($c['name']) ?></strong><span><?= e($c['message']) ?></span></div><?php endforeach; ?></div></div></div>

<div class="row g-3">
<div class="col-xl-6"><div class="card vqda-card h-100"><div class="card-header">Data Integrity</div><div class="table-responsive"><table class="table vqda-table mb-0"><thead><tr><th>Check</th><th>Problem Rows</th><th>Status</th></tr></thead><tbody><?php foreach($integrity as $i): ?><tr><td><?= e($i['name']) ?></td><td><?= $i['value']<0?'N/A':(int)$i['value'] ?></td><td><span class="badge text-bg-<?= $i['value']===0?'success':($i['value']<0?'secondary':'warning') ?>"><?= $i['value']===0?'PASS':($i['value']<0?'REVIEW':'REVIEW') ?></span></td></tr><?php endforeach; ?></tbody></table></div></div></div>
<div class="col-xl-6"><div class="card vqda-card h-100"><div class="card-header">Applied VQDSS Migrations</div><div class="card-body"><?php if(!$migrations): ?><div class="text-muted">No VQDSS migration tracking records were found.</div><?php endif; ?><?php foreach($migrations as $m): ?><div class="p-2 mb-2 bg-light rounded"><strong class="small"><?= e($m['migration_key']) ?></strong><div class="small text-muted"><?= e($m['description']?:'') ?><br><?= formatDateTime($m['applied_at']) ?></div></div><?php endforeach; ?></div></div></div>
</div>

<div class="alert alert-info mt-3 mb-0"><strong>Final verification:</strong> run <code>database/final_validation.sql</code> in phpMyAdmin and follow <code>FINAL_TESTING_CHECKLIST.md</code>. Every problem count under the integrity section should normally be zero.</div>
</main></div>
<?php include __DIR__.'/../layouts/footer.php'; ?>
