<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
requirePermission('orlms.approvals.view');

$pdo=db();$id=(int)($_GET['id']??0);
$q=$pdo->prepare(
 "SELECT li.reference_number,li.title,li.current_status,lit.name item_type_name,
         d.subject,d.legal_basis,e.endorsement_status,e.vote_summary,
         en.*,a.action_reference,a.action_type,a.action_status,a.action_date,a.remarks approval_remarks
  FROM legislative_items li
  JOIN legislative_item_types lit ON lit.id=li.item_type_id
  LEFT JOIN orlms_item_details d ON d.legislative_item_id=li.id
  LEFT JOIN orlms_endorsements e ON e.id=(SELECT MAX(e2.id) FROM orlms_endorsements e2 WHERE e2.legislative_item_id=li.id)
  LEFT JOIN orlms_approval_actions a ON a.id=(SELECT MAX(a2.id) FROM orlms_approval_actions a2 WHERE a2.legislative_item_id=li.id)
  LEFT JOIN orlms_enactments en ON en.legislative_item_id=li.id
  WHERE li.id=:id"
);$q->execute([':id'=>$id]);$r=$q->fetch();if(!$r)exit('Record not found.');
?>
<!doctype html><html><head><meta charset="utf-8"><title><?= e($r['reference_number']) ?></title><link rel="icon" type="image/png" href="../../assets/images/manila.png"><style>body{font:12px Arial;margin:28px;color:#111827}.head{text-align:center;border-bottom:3px solid #0f2137;padding-bottom:12px;margin-bottom:15px}.head img{height:54px;width:54px;object-fit:contain;margin-bottom:6px}.box{border:1px solid #ccc;padding:9px;margin:8px 0}.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:8px}</style></head><body onload="window.print()"><div class="head"><img src="../../assets/images/manila.png" alt="City of Manila Seal"><br><strong><?= e(APP_NAME) ?></strong><h2>Approval & Enactment Record</h2><div><?= e($r['reference_number']) ?></div></div><p><strong><?= e($r['item_type_name']) ?>:</strong> <?= e($r['title']) ?></p><div class="grid"><div class="box">Committee Endorsement<br><strong><?= e($r['endorsement_status']?:'—') ?></strong></div><div class="box">Approval<br><strong><?= e($r['action_status']?:'—') ?></strong></div><div class="box">Item Status<br><strong><?= e($r['current_status']) ?></strong></div></div><div class="box"><strong>Approval Action</strong><br><?= e(($r['action_reference']?:'—').' · '.($r['action_type']?:'—')) ?><br><?= nl2br(e($r['approval_remarks']?:'')) ?></div><div class="box"><strong>Official Number</strong><br><?= e($r['official_number']?:'—') ?><br><strong>Enactment/Adoption Date:</strong> <?= e($r['enactment_date']?:'—') ?><br><strong>Signing Authority:</strong> <?= e($r['signing_authority']?:'—') ?><br><strong>Effectivity:</strong> <?= e(($r['effectivity_type']?:'—').' '.($r['effectivity_date']?:'')) ?></div></body></html>
