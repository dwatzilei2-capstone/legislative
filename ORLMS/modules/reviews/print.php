<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
requirePermission('orlms.reviews.view');

$pdo=db();$id=(int)($_GET['id']??0);
$q=$pdo->prepare(
 "SELECT r.*,li.reference_number,li.title,li.current_status,lit.name item_type_name,
         d.subject,d.body_text,c.name committee_name,u.full_name reviewer_name,
         e.endorsement_status,e.recommendation endorsement_recommendation,
         e.vote_summary,e.remarks endorsement_remarks
  FROM orlms_reviews r
  JOIN legislative_items li ON li.id=r.legislative_item_id
  JOIN legislative_item_types lit ON lit.id=li.item_type_id
  LEFT JOIN orlms_item_details d ON d.legislative_item_id=li.id
  LEFT JOIN committees c ON c.id=r.committee_id
  LEFT JOIN users u ON u.id=r.reviewer_id
  LEFT JOIN orlms_endorsements e ON e.review_id=r.id
  WHERE r.id=:id"
);$q->execute([':id'=>$id]);$r=$q->fetch();if(!$r)exit('Review not found.');

$c=$pdo->prepare('SELECT * FROM orlms_review_checklist WHERE review_id=:id ORDER BY sequence_number,id');$c->execute([':id'=>$id]);$checks=$c->fetchAll();
$cm=$pdo->prepare('SELECT * FROM orlms_review_comments WHERE review_id=:id ORDER BY created_at,id');$cm->execute([':id'=>$id]);$comments=$cm->fetchAll();
?>
<!doctype html><html><head><meta charset="utf-8"><title><?= e($r['reference_number']) ?> Review</title><link rel="icon" type="image/png" href="../../assets/images/manila.png"><style>body{font:12px Arial;margin:28px;color:#111827}.head{text-align:center;border-bottom:3px solid #0f2137;padding-bottom:12px;margin-bottom:15px}.head img{height:54px;width:54px;object-fit:contain;margin-bottom:6px}.box{border:1px solid #ddd;padding:8px;margin-bottom:10px}table{width:100%;border-collapse:collapse;margin:12px 0}th,td{border:1px solid #ccc;padding:6px;vertical-align:top}th{background:#eee}.body{white-space:pre-wrap;font-family:Georgia,serif;line-height:1.55}</style></head><body onload="window.print()">
<div class="head"><img src="../../assets/images/manila.png" alt="City of Manila Seal"><br><strong><?= e(APP_NAME) ?></strong><h2>Committee Review & Endorsement Record</h2><div><?= e($r['reference_number'].' · Review Round '.$r['review_round']) ?></div></div>
<p><strong><?= e($r['item_type_name']) ?>:</strong> <?= e($r['title']) ?></p><p><strong>Committee:</strong> <?= e($r['committee_name']?:'—') ?> · <strong>Reviewer:</strong> <?= e($r['reviewer_name']?:'Committee Queue') ?> · <strong>Status:</strong> <?= e($r['status']) ?></p>
<div class="box"><strong>Submitted Draft</strong><div class="body"><?= e($r['body_text']?:'No draft body') ?></div></div>
<h3>Review Checklist</h3><table><thead><tr><th>Check</th><th>Status</th><th>Note</th></tr></thead><tbody><?php foreach($checks as $x): ?><tr><td><?= e($x['check_name']) ?></td><td><?= e($x['check_status']) ?></td><td><?= e($x['reviewer_note']?:'') ?></td></tr><?php endforeach; ?></tbody></table>
<?php if($comments): ?><h3>Review Comments</h3><table><thead><tr><th>Type / Section</th><th>Comment</th><th>Status / Resolution</th></tr></thead><tbody><?php foreach($comments as $x): ?><tr><td><?= e($x['comment_type'].' '.($x['section_reference']?:'')) ?></td><td><?= e($x['comment_text']) ?></td><td><?= e($x['status']) ?><?= $x['resolution_text']?'<br>'.e($x['resolution_text']):'' ?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?>
<p><strong>Review Recommendation:</strong> <?= e($r['recommendation']?:'Pending') ?></p><p><?= nl2br(e($r['comments']?:'')) ?></p>
<p><strong>Committee Endorsement:</strong> <?= e($r['endorsement_status']?:'Pending') ?><?= $r['vote_summary']?' · Vote '.$r['vote_summary']:'' ?></p><p><?= nl2br(e($r['endorsement_remarks']?:'')) ?></p>
</body></html>
