<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
requirePermission('vqd.validation.view');

$pdo=db();$id=(int)($_GET['id']??0);
$q=$pdo->prepare(
 "SELECT v.*,d.official_outcome,li.reference_number legislative_reference,li.title legislative_title
  FROM vqd_result_validations v
  LEFT JOIN vqd_decisions d ON d.id=v.decision_id
  LEFT JOIN legislative_items li ON li.id=v.legislative_item_id
  WHERE v.id=:id"
);$q->execute([':id'=>$id]);$v=$q->fetch();if(!$v)exit('Validation not found.');

$c=$pdo->prepare('SELECT * FROM vqd_validation_checks WHERE validation_id=:id ORDER BY sequence_number,id');
$c->execute([':id'=>$id]);$checks=$c->fetchAll();

$f=$pdo->prepare('SELECT * FROM vqd_validation_findings WHERE validation_id=:id ORDER BY id');
$f->execute([':id'=>$id]);$findings=$f->fetchAll();
?>
<!doctype html><html><head><meta charset="utf-8"><title><?= e($v['validation_reference']) ?></title><style>body{font:12px Arial;margin:28px;color:#111827}.head{text-align:center;border-bottom:3px solid #0f2137;padding-bottom:12px;margin-bottom:15px}.grid{display:grid;grid-template-columns:repeat(4,1fr);gap:8px}.box{border:1px solid #ddd;padding:8px}table{width:100%;border-collapse:collapse;margin-top:12px}th,td{border:1px solid #ccc;padding:6px}th{background:#eee}</style></head><body onload="window.print()">
<div class="head"><strong><?= e(APP_NAME) ?></strong><h2>Result Validation Report</h2><div><?= e($v['validation_reference']) ?></div></div>
<p><strong>Legislative Item:</strong> <?= e(($v['legislative_reference']?:'—').' '.($v['legislative_title']?:'')) ?></p>
<p><strong>Chain:</strong> <?= e(($v['quorum_reference']?:'—').' → '.($v['voting_reference']?:'—').' → '.($v['tally_reference']?:'—').' → '.($v['decision_reference']?:'—')) ?></p>
<div class="grid"><div class="box">Integrity<br><strong><?= round((float)$v['integrity_score'],2) ?>%</strong></div><div class="box">Risk<br><strong><?= e($v['risk_level']) ?></strong></div><div class="box">Outcome<br><strong><?= e($v['validation_outcome']) ?></strong></div><div class="box">Status<br><strong><?= e($v['validation_status']) ?></strong></div></div>
<h3>Integrity Checks</h3><table><thead><tr><th>Check</th><th>Status</th><th>Note</th></tr></thead><tbody><?php foreach($checks as $c): ?><tr><td><?= e($c['check_name']) ?></td><td><?= e($c['check_status']) ?></td><td><?= e($c['check_note']?:'') ?></td></tr><?php endforeach; ?></tbody></table>
<?php if($findings): ?><h3>Findings</h3><table><thead><tr><th>Finding</th><th>Severity</th><th>Status</th><th>Corrective Action</th><th>Resolution</th></tr></thead><tbody><?php foreach($findings as $f): ?><tr><td><?= e($f['finding_title']) ?></td><td><?= e($f['severity']) ?></td><td><?= e($f['status']) ?></td><td><?= e($f['corrective_action']?:'') ?></td><td><?= e($f['resolution']?:'') ?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?>
<?php if($v['executive_summary']): ?><p><strong>Executive Summary:</strong><br><?= nl2br(e($v['executive_summary'])) ?></p><?php endif; ?>
<?php if($v['certification_statement']): ?><p><strong>Certification:</strong><br><?= nl2br(e($v['certification_statement'])) ?></p><?php endif; ?>
</body></html>
