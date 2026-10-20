<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
requirePermission('orlms.publications.view');
$pdo=db();$id=(int)($_GET['id']??0);
$q=$pdo->prepare(
 "SELECT p.*,li.reference_number,li.title,en.official_number,en.enactment_date
  FROM orlms_publications p JOIN legislative_items li ON li.id=p.legislative_item_id
  LEFT JOIN orlms_enactments en ON en.legislative_item_id=li.id WHERE p.id=:id"
);$q->execute([':id'=>$id]);$p=$q->fetch();if(!$p)exit('Publication not found.');
$r=$pdo->prepare(
 "SELECT x.*,o.name office_name,u.full_name user_name FROM orlms_dissemination_recipients x
  LEFT JOIN offices o ON o.id=x.office_id LEFT JOIN users u ON u.id=x.user_id
  WHERE x.publication_id=:id ORDER BY x.id"
);$r->execute([':id'=>$id]);$recipients=$r->fetchAll();
?>
<!doctype html><html><head><meta charset="utf-8"><title><?= e($p['publication_reference']) ?></title><link rel="icon" type="image/png" href="../../assets/images/manila.png"><style>body{font:12px Arial;margin:28px;color:#111827}.head{text-align:center;border-bottom:3px solid #0f2137;padding-bottom:12px;margin-bottom:15px}.head img{height:54px;width:54px;object-fit:contain;margin-bottom:6px}.box{border:1px solid #ccc;padding:9px;margin:8px 0}table{width:100%;border-collapse:collapse;margin-top:12px}th,td{border:1px solid #ccc;padding:6px}th{background:#eee}</style></head><body onload="window.print()"><div class="head"><img src="../../assets/images/manila.png" alt="City of Manila Seal"><br><strong><?= e(APP_NAME) ?></strong><h2>Publication & Dissemination Record</h2><div><?= e($p['publication_reference']) ?></div></div><p><strong><?= e($p['official_number']?:$p['reference_number']) ?>:</strong> <?= e($p['title']) ?></p><div class="box"><strong>Channel:</strong> <?= e($p['publication_channel']) ?> · <strong>Status:</strong> <?= e($p['publication_status']) ?> · <strong>Date:</strong> <?= e($p['publication_date']?:'—') ?><br><strong>Classification:</strong> <?= e($p['release_classification']) ?> · <strong>URL:</strong> <?= e($p['public_url']?:'—') ?></div><?php if($p['publication_text']): ?><div class="box"><?= nl2br(e($p['publication_text'])) ?></div><?php endif; ?><h3>Dissemination Recipients</h3><table><thead><tr><th>Recipient</th><th>Type</th><th>Channel</th><th>Status</th><th>Sent</th><th>Acknowledged</th></tr></thead><tbody><?php foreach($recipients as $x): ?><tr><td><?= e($x['office_name']?:$x['user_name']?:$x['recipient_name']?:'—') ?></td><td><?= e($x['recipient_type']) ?></td><td><?= e($x['delivery_channel']) ?></td><td><?= e($x['delivery_status']) ?></td><td><?= e($x['sent_at']?:'—') ?></td><td><?= e($x['acknowledged_at']?:'—') ?></td></tr><?php endforeach; ?></tbody></table></body></html>
