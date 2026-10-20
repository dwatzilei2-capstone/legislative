<?php
declare(strict_types=1);
$rows=db()->query(
 "SELECT p.id,p.plan_reference,p.objective,p.status,p.compliance_percent,
         li.reference_number,li.title FROM orlms_implementation_plans p
  JOIN legislative_items li ON li.id=p.legislative_item_id
  WHERE li.deleted_at IS NULL ORDER BY p.created_at DESC LIMIT 100"
)->fetchAll();
?>
<div class="table-responsive"><table class="table orl-table mb-0"><thead><tr><th>Plan</th><th>Item</th><th>Compliance</th><th>Status</th><th></th></tr></thead><tbody><?php foreach($rows as $r): ?><tr><td><?= e($r['plan_reference']) ?></td><td><?= e($r['reference_number'].' · '.$r['title']) ?></td><td><?= round((float)$r['compliance_percent'],1) ?>%</td><td><?= e($r['status']) ?></td><td><a href="view.php?id=<?= (int)$r['id'] ?>">View</a></td></tr><?php endforeach; ?></tbody></table></div>
