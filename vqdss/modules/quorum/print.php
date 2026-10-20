<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
requirePermission('vqd.quorum.view');

$pdo=db();$id=(int)($_GET['id']??0);
$q=$pdo->prepare(
 "SELECT qc.*,s.reference_number session_reference,s.session_title,s.session_date,s.start_time,s.venue,
         li.reference_number legislative_reference,li.title legislative_title,c.name committee_name
  FROM vqd_quorum_checks qc JOIN vqd_sessions s ON s.id=qc.session_id
  LEFT JOIN legislative_items li ON li.id=qc.legislative_item_id
  LEFT JOIN committees c ON c.id=s.committee_id WHERE qc.id=:id"
);$q->execute([':id'=>$id]);$check=$q->fetch();if(!$check)exit('Quorum check not found.');

$m=$pdo->prepare(
 "SELECT u.full_name,sm.position_title,qcm.attendance_status,qcm.voting_eligibility,
         qcm.included_in_quorum_count
  FROM vqd_quorum_check_members qcm JOIN users u ON u.id=qcm.user_id
  LEFT JOIN vqd_session_members sm ON sm.id=qcm.session_member_id
  WHERE qcm.quorum_check_id=:id ORDER BY u.full_name"
);$m->execute([':id'=>$id]);$rows=$m->fetchAll();
?>
<!doctype html><html><head><meta charset="utf-8"><title><?= e($check['reference_number']) ?></title><style>body{font:12px Arial;margin:28px;color:#111827}.head{text-align:center;border-bottom:3px solid #0f2137;padding-bottom:12px;margin-bottom:15px}.grid{display:grid;grid-template-columns:repeat(4,1fr);gap:8px}.box{border:1px solid #ddd;padding:8px}table{width:100%;border-collapse:collapse;margin-top:15px}th,td{border:1px solid #ccc;padding:6px}th{background:#eee}</style></head><body onload="window.print()">
<div class="head"><strong><?= e(APP_NAME) ?></strong><h2>Quorum Certification</h2><div><?= e($check['reference_number'].' · '.$check['session_title']) ?></div></div>
<div class="grid"><div class="box">Official<br><strong><?= (int)$check['official_member_count'] ?></strong></div><div class="box">Eligible<br><strong><?= (int)$check['eligible_member_count'] ?></strong></div><div class="box">Present Eligible<br><strong><?= (int)$check['present_eligible_count'] ?></strong></div><div class="box">Required<br><strong><?= (int)$check['required_count'] ?></strong></div></div>
<p><strong>Result:</strong> <?= e($check['quorum_result']) ?> · <strong>Status:</strong> <?= e($check['verification_status']) ?></p>
<p><strong>Session:</strong> <?= formatDate($check['session_date']) ?> <?= formatTime($check['start_time']) ?> · <?= e($check['venue']?:'No venue') ?></p>
<p><strong>Legislative Item:</strong> <?= e(($check['legislative_reference']?:'—').' '.($check['legislative_title']?:'')) ?></p>
<table><thead><tr><th>Member</th><th>Position</th><th>Attendance</th><th>Eligibility</th><th>Counted</th></tr></thead><tbody><?php foreach($rows as $r): ?><tr><td><?= e($r['full_name']) ?></td><td><?= e($r['position_title']?:'Member') ?></td><td><?= e($r['attendance_status']) ?></td><td><?= e($r['voting_eligibility']) ?></td><td><?= $r['included_in_quorum_count']?'Yes':'No' ?></td></tr><?php endforeach; ?></tbody></table>
<p><strong>Certification Notes:</strong> <?= nl2br(e($check['certification_notes']?:'—')) ?></p>
</body></html>
