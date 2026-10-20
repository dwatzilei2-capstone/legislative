<?php
declare(strict_types=1);
$rows=db()->query(
 "SELECT a.id,a.amendment_reference,a.title,a.status,li.reference_number,
         en.official_number
  FROM orlms_amendments a
  JOIN legislative_items li ON li.id=a.legislative_item_id
  LEFT JOIN orlms_enactments en ON en.legislative_item_id=li.id
  WHERE li.deleted_at IS NULL
  ORDER BY a.updated_at DESC LIMIT 100"
)->fetchAll();
?>
<div class="table-responsive"><table class="table of-table mb-0"><thead><tr><th>Amendment</th><th>Original</th><th>Official No.</th><th>Status</th><th></th></tr></thead><tbody><?php foreach($rows as $r): ?><tr><td><?= e($r['amendment_reference'].' · '.$r['title']) ?></td><td><?= e($r['reference_number']) ?></td><td><?= e($r['official_number']?:'—') ?></td><td><?= e($r['status']) ?></td><td><a href="view.php?id=<?= (int)$r['id'] ?>">View</a></td></tr><?php endforeach; ?></tbody></table></div>
