<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
requirePermission('vqd.decisions.view');

$pdo=db();$id=(int)($_GET['id']??0);
$q=$pdo->prepare(
 "SELECT d.*,t.reference_number tally_reference,ve.reference_number voting_reference,
         ve.motion_text,li.reference_number legislative_reference,li.title legislative_title
  FROM vqd_decisions d
  LEFT JOIN vqd_vote_tallies t ON t.id=d.tally_id
  LEFT JOIN vqd_voting_events ve ON ve.id=d.voting_event_id
  LEFT JOIN legislative_items li ON li.id=d.legislative_item_id
  WHERE d.id=:id"
);$q->execute([':id'=>$id]);$d=$q->fetch();if(!$d)exit('Decision not found.');

$a=$pdo->prepare(
 "SELECT a.*,o.name office_name,u.full_name user_name
  FROM vqd_decision_actions a
  LEFT JOIN offices o ON o.id=a.responsible_office_id
  LEFT JOIN users u ON u.id=a.responsible_user_id
  WHERE a.decision_id=:id ORDER BY a.id"
);$a->execute([':id'=>$id]);$actions=$a->fetchAll();
?>
<!doctype html><html><head><meta charset="utf-8"><title><?= e($d['reference_number']) ?></title><style>body{font:12px Arial;margin:28px;color:#111827}.head{text-align:center;border-bottom:3px solid #0f2137;padding-bottom:12px;margin-bottom:15px}.box{border:1px solid #ddd;padding:9px;margin:8px 0}.grid{display:grid;grid-template-columns:repeat(4,1fr);gap:8px}table{width:100%;border-collapse:collapse}th,td{border:1px solid #ccc;padding:6px}th{background:#eee}</style></head><body onload="window.print()">
<div class="head"><strong><?= e(APP_NAME) ?></strong><h2>Official Decision Record</h2><div><?= e($d['reference_number'].' · '.($d['decision_record_number']?:'')) ?></div></div>
<p><strong>Legislative Item:</strong> <?= e(($d['legislative_reference']?:'—').' '.($d['legislative_title']?:'')) ?></p>
<p><strong>Source:</strong> <?= e($d['tally_reference']?:'—') ?> · <strong>Status:</strong> <?= e($d['record_status']) ?></p>
<div class="grid"><div class="box">Required<br><strong><?= (int)$d['required_votes'] ?></strong></div><div class="box">Affirmative<br><strong><?= (int)$d['affirmative_votes'] ?></strong></div><div class="box">Negative<br><strong><?= (int)$d['negative_votes'] ?></strong></div><div class="box">Abstain<br><strong><?= (int)$d['abstain_votes'] ?></strong></div></div>
<div class="box"><strong>Official Outcome: <?= e($d['official_outcome']) ?></strong><br><br><?= nl2br(e($d['decision_statement'])) ?></div>
<?php if($d['legal_basis']): ?><div class="box"><strong>Legal / Procedural Basis</strong><br><?= nl2br(e($d['legal_basis'])) ?></div><?php endif; ?>
<?php if($actions): ?><h3>Implementation Actions</h3><table><thead><tr><th>Action</th><th>Responsible</th><th>Priority</th><th>Due</th><th>Status</th></tr></thead><tbody><?php foreach($actions as $a): ?><tr><td><?= e($a['action_title']) ?></td><td><?= e($a['office_name']?:$a['user_name']?:'Unassigned') ?></td><td><?= e($a['priority']) ?></td><td><?= e($a['due_date']?:'—') ?></td><td><?= e($a['status']) ?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?>
</body></html>
