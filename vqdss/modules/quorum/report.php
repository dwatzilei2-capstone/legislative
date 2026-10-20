<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
requirePermission('vqd.quorum.view');

$pageTitle='Quorum Report';$activeMenu='quorum';$extraCss=[appUrl('assets/css/vqdss-operational.css')];
$rows=db()->query(
 "SELECT qc.reference_number,s.reference_number session_reference,s.session_title,
         qc.quorum_rule,qc.official_member_count,qc.eligible_member_count,
         qc.present_eligible_count,qc.required_count,qc.quorum_result,
         qc.verification_status,qc.certified_at
  FROM vqd_quorum_checks qc JOIN vqd_sessions s ON s.id=qc.session_id
  ORDER BY qc.created_at DESC"
)->fetchAll();
include __DIR__.'/../../layouts/header.php';
?>
<div class="app-wrapper"><?php include __DIR__.'/../../layouts/sidebar.php'; ?><main class="main-content"><div class="vqdx-head"><div><div class="vqdx-eyebrow">Operational Report</div><h1>Quorum Verification Report</h1><p>Live report generated from certified and draft quorum records.</p></div><a class="btn btn-outline-secondary" href="index.php"><i class="bi bi-arrow-left"></i> Quorum</a></div><div class="card vqdx-card"><div class="table-responsive"><table class="table vqdx-table mb-0"><thead><tr><th>Reference</th><th>Session</th><th>Rule</th><th>Official</th><th>Eligible</th><th>Present</th><th>Required</th><th>Result</th><th>Status</th></tr></thead><tbody><?php foreach($rows as $r): ?><tr><td><?= e($r['reference_number']) ?></td><td><?= e($r['session_reference'].' · '.$r['session_title']) ?></td><td><?= e($r['quorum_rule']) ?></td><td><?= (int)$r['official_member_count'] ?></td><td><?= (int)$r['eligible_member_count'] ?></td><td><?= (int)$r['present_eligible_count'] ?></td><td><?= (int)$r['required_count'] ?></td><td><?= e($r['quorum_result']) ?></td><td><?= e($r['verification_status']) ?></td></tr><?php endforeach; ?></tbody></table></div></div></main></div>
<?php include __DIR__.'/../../layouts/footer.php'; ?>
