<?php
declare(strict_types=1);
$rows=db()->query(
 "SELECT li.id,li.reference_number,li.title,li.current_status,
         a.action_reference,a.action_status,en.official_number
  FROM legislative_items li
  LEFT JOIN orlms_approval_actions a ON a.id=(SELECT MAX(a2.id) FROM orlms_approval_actions a2 WHERE a2.legislative_item_id=li.id)
  LEFT JOIN orlms_enactments en ON en.legislative_item_id=li.id
  WHERE li.deleted_at IS NULL AND (a.id IS NOT NULL OR en.id IS NOT NULL)
  ORDER BY li.updated_at DESC LIMIT 100"
)->fetchAll();
?>
<div class="table-responsive"><table class="table orl-table mb-0"><thead><tr><th>Reference</th><th>Title</th><th>Approval</th><th>Official Number</th><th>Status</th><th></th></tr></thead><tbody><?php foreach($rows as $r): ?><tr><td><?= e($r['reference_number']) ?></td><td><?= e($r['title']) ?></td><td><?= e($r['action_reference']?:'—') ?> · <?= e($r['action_status']?:'—') ?></td><td><?= e($r['official_number']?:'—') ?></td><td><?= e($r['current_status']) ?></td><td><a href="view.php?id=<?= (int)$r['id'] ?>">View</a></td></tr><?php endforeach; ?></tbody></table></div>
