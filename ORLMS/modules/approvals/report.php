<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
requirePermission('orlms.approvals.view');

$rows=db()->query(
 "SELECT li.reference_number,li.title,lit.name item_type_name,li.current_status,
         a.action_reference,a.action_type,a.action_status,a.action_date,
         en.enactment_reference,en.official_number,en.enactment_status,en.enactment_date
  FROM legislative_items li
  JOIN legislative_item_types lit ON lit.id=li.item_type_id
  LEFT JOIN orlms_approval_actions a
    ON a.id=(SELECT MAX(a2.id) FROM orlms_approval_actions a2 WHERE a2.legislative_item_id=li.id)
  LEFT JOIN orlms_enactments en ON en.legislative_item_id=li.id
  WHERE li.deleted_at IS NULL AND lit.code IN ('ordinance','resolution')
    AND (a.id IS NOT NULL OR en.id IS NOT NULL OR li.current_status IN ('Endorsed','Pending Approval','Approved','Enacted','Vetoed','Rejected'))
  ORDER BY li.updated_at DESC"
)->fetchAll();

$pageTitle='Approval & Enactment Report';$activeMenu='approvals';
$extraCss=[APP_URL.'/assets/css/orlms-lifecycle-ops.css'];
include __DIR__.'/../../layouts/header.php';
?>
<div class="orlms-app-layout"><?php include __DIR__.'/../../layouts/sidebar.php'; ?><main class="orlms-main-content"><div class="orl-head"><div><div class="orl-eyebrow">Operational Report</div><h1>Approval & Enactment Report</h1><p>Committee-endorsed items, approval actions, official numbering and finalized enactment/adoption records.</p></div><a class="btn btn-outline-secondary" href="index.php">Approval Register</a></div><div class="card orl-card"><div class="table-responsive"><table class="table orl-table mb-0"><thead><tr><th>Item</th><th>Type</th><th>Approval Reference</th><th>Approval Action</th><th>Approval Status</th><th>Official Number</th><th>Enactment</th><th>Item Status</th></tr></thead><tbody><?php foreach($rows as $r): ?><tr><td><?= e($r['reference_number']) ?><div class="small text-muted"><?= e(mb_strimwidth($r['title'],0,80,'…')) ?></div></td><td><?= e($r['item_type_name']) ?></td><td><?= e($r['action_reference']?:'—') ?></td><td><?= e($r['action_type']?:'—') ?></td><td><?= e($r['action_status']?:'—') ?></td><td><?= e($r['official_number']?:'—') ?></td><td><?= e($r['enactment_status']?:'—') ?><?= $r['enactment_date']?' · '.formatDate($r['enactment_date']):'' ?></td><td><?= e($r['current_status']) ?></td></tr><?php endforeach; ?></tbody></table></div></div></main></div>
<?php include __DIR__.'/../../layouts/footer.php'; ?>
