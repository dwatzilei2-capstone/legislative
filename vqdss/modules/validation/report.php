<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
requirePermission('vqd.validation.view');

$rows=db()->query(
 "SELECT v.validation_reference,v.integrity_score,v.risk_level,v.challenge_status,
         v.validation_outcome,v.validation_status,v.certified_at,
         d.reference_number decision_reference,li.reference_number legislative_reference,
         (SELECT COUNT(*) FROM vqd_validation_findings f WHERE f.validation_id=v.id AND f.status<>'Resolved') open_findings
  FROM vqd_result_validations v
  LEFT JOIN vqd_decisions d ON d.id=v.decision_id
  LEFT JOIN legislative_items li ON li.id=v.legislative_item_id
  ORDER BY v.created_at DESC"
)->fetchAll();

$pageTitle='Validation Report';$activeMenu='validation';$extraCss=[appUrl('assets/css/vqdss-final-ops.css')];
include __DIR__.'/../../layouts/header.php';
?>
<div class="app-wrapper"><?php include __DIR__.'/../../layouts/sidebar.php'; ?><main class="main-content"><div class="vqdf-head"><div><div class="vqdf-eyebrow">Operational Report</div><h1>Result Validation Report</h1><p>Integrity, risk, findings and certification status for final VQDSS results.</p></div><a href="index.php" class="btn btn-outline-secondary">Validation</a></div><div class="card vqdf-card"><div class="table-responsive"><table class="table vqdf-table mb-0"><thead><tr><th>Validation</th><th>Decision</th><th>Item</th><th>Integrity</th><th>Risk</th><th>Challenge</th><th>Outcome</th><th>Open Findings</th><th>Status</th></tr></thead><tbody><?php foreach($rows as $r): ?><tr><td><?= e($r['validation_reference']) ?></td><td><?= e($r['decision_reference']?:'—') ?></td><td><?= e($r['legislative_reference']?:'—') ?></td><td><?= round((float)$r['integrity_score'],2) ?>%</td><td><?= e($r['risk_level']) ?></td><td><?= e($r['challenge_status']) ?></td><td><?= e($r['validation_outcome']) ?></td><td><?= (int)$r['open_findings'] ?></td><td><?= e($r['validation_status']) ?></td></tr><?php endforeach; ?></tbody></table></div></div></main></div>
<?php include __DIR__.'/../../layouts/footer.php'; ?>
