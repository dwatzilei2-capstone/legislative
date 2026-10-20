<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
requirePermission('orlms.implementation.view');
$pdo=db();$id=(int)($_GET['id']??0);
$q=$pdo->prepare(
 "SELECT p.*,li.reference_number,li.title,en.official_number,o.name office_name,u.full_name user_name
  FROM orlms_implementation_plans p JOIN legislative_items li ON li.id=p.legislative_item_id
  LEFT JOIN orlms_enactments en ON en.legislative_item_id=li.id
  LEFT JOIN offices o ON o.id=p.responsible_office_id
  LEFT JOIN users u ON u.id=p.responsible_user_id WHERE p.id=:id"
);$q->execute([':id'=>$id]);$p=$q->fetch();if(!$p)exit('Plan not found.');
$u=$pdo->prepare('SELECT * FROM orlms_implementation_updates WHERE implementation_plan_id=:id ORDER BY update_date,id');$u->execute([':id'=>$id]);$updates=$u->fetchAll();
$i=$pdo->prepare('SELECT * FROM orlms_implementation_issues WHERE implementation_plan_id=:id ORDER BY created_at,id');$i->execute([':id'=>$id]);$issues=$i->fetchAll();
?>
<!doctype html><html><head><meta charset="utf-8"><title><?= e($p['plan_reference']) ?></title><link rel="icon" type="image/png" href="../../assets/images/manila.png"><style>body{font:12px Arial;margin:28px;color:#111827}.head{text-align:center;border-bottom:3px solid #0f2137;padding-bottom:12px;margin-bottom:15px}.head img{height:54px;width:54px;object-fit:contain;margin-bottom:6px}.box{border:1px solid #ccc;padding:9px;margin:8px 0}table{width:100%;border-collapse:collapse;margin-top:12px}th,td{border:1px solid #ccc;padding:6px;vertical-align:top}th{background:#eee}</style></head><body onload="window.print()"><div class="head"><img src="../../assets/images/manila.png" alt="City of Manila Seal"><br><strong><?= e(APP_NAME) ?></strong><h2>Implementation Monitoring Record</h2><div><?= e($p['plan_reference']) ?></div></div><p><strong><?= e($p['official_number']?:$p['reference_number']) ?>:</strong> <?= e($p['title']) ?></p><div class="box"><strong>Objective</strong><br><?= nl2br(e($p['objective'])) ?></div><p><strong>Responsible:</strong> <?= e($p['office_name']?:$p['user_name']?:'Unassigned') ?> · <strong>Status:</strong> <?= e($p['status']) ?> · <strong>Compliance:</strong> <?= round((float)$p['compliance_percent'],1) ?>%</p><h3>Progress Updates</h3><table><thead><tr><th>Date</th><th>Progress</th><th>Status</th><th>Update</th><th>Issues / Next Steps</th></tr></thead><tbody><?php foreach($updates as $x): ?><tr><td><?= e($x['update_date']) ?></td><td><?= round((float)$x['progress_percent'],1) ?>%</td><td><?= e($x['status']) ?></td><td><?= e($x['update_text']) ?></td><td><?= e(($x['issues_encountered']?:'').' '.($x['next_steps']?:'')) ?></td></tr><?php endforeach; ?></tbody></table><?php if($issues): ?><h3>Implementation Issues</h3><table><thead><tr><th>Issue</th><th>Severity</th><th>Status</th><th>Due</th><th>Resolution</th></tr></thead><tbody><?php foreach($issues as $x): ?><tr><td><?= e($x['issue_title']) ?></td><td><?= e($x['severity']) ?></td><td><?= e($x['status']) ?></td><td><?= e($x['due_date']?:'—') ?></td><td><?= e($x['resolution']?:'') ?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?></body></html>
