<?php
declare(strict_types=1);
$rows=db()->query(
 "SELECT p.id,p.publication_reference,p.publication_status,li.reference_number,li.title,
         en.official_number FROM orlms_publications p
  JOIN legislative_items li ON li.id=p.legislative_item_id
  LEFT JOIN orlms_enactments en ON en.legislative_item_id=li.id
  ORDER BY p.created_at DESC LIMIT 100"
)->fetchAll();
?>
<div class="table-responsive"><table class="table orl-table mb-0"><thead><tr><th>Publication</th><th>Item</th><th>Official Number</th><th>Status</th><th></th></tr></thead><tbody><?php foreach($rows as $r): ?><tr><td><?= e($r['publication_reference']) ?></td><td><?= e($r['reference_number'].' · '.$r['title']) ?></td><td><?= e($r['official_number']?:'—') ?></td><td><?= e($r['publication_status']) ?></td><td><a href="view.php?id=<?= (int)$r['id'] ?>">View</a></td></tr><?php endforeach; ?></tbody></table></div>
