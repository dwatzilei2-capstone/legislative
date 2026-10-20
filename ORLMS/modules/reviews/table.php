<?php
declare(strict_types=1);
$pdo=$pdo??db();
$rows=$pdo->query(
 "SELECT r.id,li.reference_number,li.title,r.review_round,r.status,c.name committee_name,
         r.recommendation,e.endorsement_status
  FROM orlms_reviews r
  JOIN legislative_items li ON li.id=r.legislative_item_id
  LEFT JOIN committees c ON c.id=r.committee_id
  LEFT JOIN orlms_endorsements e ON e.review_id=r.id
  WHERE li.deleted_at IS NULL
  ORDER BY r.created_at DESC LIMIT 100"
)->fetchAll();
?>
<div class="table-responsive"><table class="table orx-table mb-0"><thead><tr><th>Reference</th><th>Title</th><th>Committee</th><th>Round</th><th>Review</th><th>Endorsement</th><th></th></tr></thead><tbody><?php foreach($rows as $r): ?><tr><td><?= e($r['reference_number']) ?></td><td><?= e($r['title']) ?></td><td><?= e($r['committee_name']?:'—') ?></td><td><?= (int)$r['review_round'] ?></td><td><?= e($r['status']) ?></td><td><?= e($r['endorsement_status']?:'Pending') ?></td><td><a href="view.php?id=<?= (int)$r['id'] ?>">View</a></td></tr><?php endforeach; ?></tbody></table></div>
