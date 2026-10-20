<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
requirePermission('vqd.tally.view');

$pdo=db();$id=(int)($_GET['id']??0);
$q=$pdo->prepare(
 "SELECT t.*,ve.motion_text,ve.reference_number voting_reference,
         li.reference_number legislative_reference
  FROM vqd_vote_tallies t JOIN vqd_voting_events ve ON ve.id=t.voting_event_id
  LEFT JOIN legislative_items li ON li.id=t.legislative_item_id
  WHERE t.id=:id"
);$q->execute([':id'=>$id]);$t=$q->fetch();if(!$t)exit('Tally not found.');
$o=$pdo->prepare('SELECT * FROM vqd_vote_tally_options WHERE tally_id=:id ORDER BY sequence_number,id');$o->execute([':id'=>$id]);$options=$o->fetchAll();
?>
<!doctype html><html><head><meta charset="utf-8"><title><?= e($t['reference_number']) ?></title><style>body{font:12px Arial;margin:28px;color:#111827}.head{text-align:center;border-bottom:3px solid #0f2137;padding-bottom:12px;margin-bottom:15px}.grid{display:grid;grid-template-columns:repeat(4,1fr);gap:8px}.box{border:1px solid #ddd;padding:8px}table{width:100%;border-collapse:collapse;margin-top:15px}th,td{border:1px solid #ccc;padding:6px}th{background:#eee}</style></head><body onload="window.print()"><div class="head"><strong><?= e(APP_NAME) ?></strong><h2>Certified Vote Tally</h2><div><?= e($t['reference_number'].' · '.$t['voting_reference']) ?></div></div><div class="box"><strong>Motion</strong><br><?= nl2br(e($t['motion_text'])) ?></div><div class="grid"><div class="box">Eligible<br><strong><?= (int)$t['eligible_voters'] ?></strong></div><div class="box">Cast<br><strong><?= (int)$t['votes_cast'] ?></strong></div><div class="box">Valid<br><strong><?= (int)$t['valid_votes'] ?></strong></div><div class="box">Required<br><strong><?= (int)$t['required_votes'] ?></strong></div></div><p><strong>Outcome:</strong> <?= e($t['result_outcome']) ?> · <strong>Status:</strong> <?= e($t['tally_status']) ?> · <strong>Discrepancy:</strong> <?= e($t['discrepancy_classification']) ?></p><table><thead><tr><th>Option</th><th>Category</th><th>Votes</th><th>Percentage</th></tr></thead><tbody><?php foreach($options as $o): ?><tr><td><?= e($o['option_label']) ?></td><td><?= e($o['option_category']) ?></td><td><?= (int)$o['vote_count'] ?></td><td><?= round((float)$o['percentage'],2) ?>%</td></tr><?php endforeach; ?></tbody></table><p><strong>Result Statement:</strong> <?= e($t['result_statement']) ?></p><p><strong>Audit Findings:</strong> <?= e($t['audit_findings']) ?></p></body></html>
