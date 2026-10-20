<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
requirePermission('vqd.voting.view');

$pdo=db();$id=(int)($_GET['id']??0);
$q=$pdo->prepare(
 "SELECT ve.*,s.reference_number session_reference,s.session_title,
         qc.reference_number quorum_reference,li.reference_number legislative_reference
  FROM vqd_voting_events ve
  LEFT JOIN vqd_sessions s ON s.id=ve.session_id
  LEFT JOIN vqd_quorum_checks qc ON qc.id=ve.quorum_check_id
  LEFT JOIN legislative_items li ON li.id=ve.legislative_item_id
  WHERE ve.id=:id"
);$q->execute([':id'=>$id]);$e=$q->fetch();if(!$e)exit('Voting event not found.');
$o=$pdo->prepare("SELECT o.option_label,o.option_category,COUNT(v.id) total FROM vqd_vote_options o LEFT JOIN vqd_votes v ON v.option_id=o.id AND v.is_valid=1 WHERE o.voting_event_id=:id GROUP BY o.id,o.option_label,o.option_category,o.sequence_number ORDER BY o.sequence_number");$o->execute([':id'=>$id]);$options=$o->fetchAll();
?>
<!doctype html><html><head><meta charset="utf-8"><title><?= e($e['reference_number']) ?></title><style>body{font:12px Arial;margin:28px;color:#111827}.head{text-align:center;border-bottom:3px solid #0f2137;padding-bottom:12px;margin-bottom:15px}.box{border:1px solid #ddd;padding:8px;margin-bottom:8px}table{width:100%;border-collapse:collapse}th,td{border:1px solid #ccc;padding:6px}th{background:#eee}</style></head><body onload="window.print()"><div class="head"><strong><?= e(APP_NAME) ?></strong><h2>Voting Event Record</h2><div><?= e($e['reference_number']) ?></div></div><div class="box"><strong>Motion</strong><br><?= nl2br(e($e['motion_text'])) ?></div><p>Session: <?= e($e['session_reference']) ?> · Quorum: <?= e($e['quorum_reference']) ?> · Status: <?= e($e['status']) ?></p><p>Method: <?= e($e['voting_method']) ?> · Visibility: <?= e($e['vote_visibility']) ?> · Threshold: <?= e($e['decision_threshold']) ?></p><?php if($e['status']==='Closed'): ?><table><thead><tr><th>Option</th><th>Category</th><th>Valid Votes</th></tr></thead><tbody><?php foreach($options as $x): ?><tr><td><?= e($x['option_label']) ?></td><td><?= e($x['option_category']) ?></td><td><?= (int)$x['total'] ?></td></tr><?php endforeach; ?></tbody></table><?php else: ?><p>Vote counts are intentionally omitted until the voting event is closed.</p><?php endif; ?></body></html>
