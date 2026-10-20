<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/vqdss_final_helpers.php';
requirePermission('vqd.reports.view');

$pdo=db();$pageTitle='Reports & Analytics';$activeMenu='reports';
$extraCss=[appUrl('assets/css/vqdss-final-ops.css')];

$reports=[
 ['quorum','Quorum Verification','vqd_quorum_checks','bi-person-check'],
 ['voting','Voting Management','vqd_voting_events','bi-ui-checks'],
 ['tally','Vote Tallying','vqd_vote_tallies','bi-calculator'],
 ['decisions','Decision Recording','vqd_decisions','bi-journal-check'],
 ['validation','Result Validation','vqd_result_validations','bi-patch-check'],
];

include __DIR__.'/../layouts/header.php';
?>
<div class="app-wrapper"><?php include __DIR__.'/../layouts/sidebar.php'; ?><main class="main-content">
<div class="vqdf-head"><div><div class="vqdf-eyebrow">Step 7 · Consolidated Reports</div><h1>Reports & Analytics</h1><p>Open module reports, export consolidated cross-module records, and trace a certified voting result from quorum through final validation.</p></div><div class="d-flex gap-2"><a class="btn btn-outline-secondary" target="_blank" href="print.php"><i class="bi bi-printer"></i> Print Summary</a><a class="btn btn-primary" href="export.php"><i class="bi bi-filetype-csv"></i> Export CSV</a></div></div>
<div class="row g-3 mb-3"><?php foreach($reports as [$key,$label,$table,$icon]): ?><div class="col-md-6 col-xl"><div class="card vqdf-card h-100"><div class="card-body"><i class="bi <?= e($icon) ?> fs-3 text-primary"></i><h5 class="mt-3"><?= e($label) ?></h5><div class="display-6 fw-bold"><?= countTableRows($table) ?></div><a class="btn btn-outline-primary btn-sm w-100 mt-2" href="<?= e(appUrl('modules/'.($key==='tally'?'tallying':$key).'/report.php')) ?>">Open Report</a></div></div></div><?php endforeach; ?></div>
<div class="card vqdf-card"><div class="card-header">Cross-Module Workflow Trace</div><div class="card-body"><p class="small text-muted">The trace report joins Session → Quorum → Voting → Tally → Decision → Validation using the dedicated foreign keys created in the VQDSS backend.</p><a class="btn btn-primary" href="workflow.php"><i class="bi bi-diagram-3"></i> Open Workflow Trace</a></div></div>
</main></div>
<?php include __DIR__.'/../layouts/footer.php'; ?>
