<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
requirePermission('orlms.implementation.view');

$rows=db()->query(
 "SELECT p.plan_reference,p.objective,p.start_date,p.due_date,p.status,p.compliance_percent,
         li.reference_number,li.title,en.official_number,o.name office_name,u.full_name user_name,
         (SELECT COUNT(*) FROM orlms_implementation_updates x WHERE x.implementation_plan_id=p.id) updates,
         (SELECT COUNT(*) FROM orlms_implementation_issues x WHERE x.implementation_plan_id=p.id AND x.status NOT IN ('Resolved','Accepted Risk')) open_issues
  FROM orlms_implementation_plans p
  JOIN legislative_items li ON li.id=p.legislative_item_id
  LEFT JOIN orlms_enactments en ON en.legislative_item_id=li.id
  LEFT JOIN offices o ON o.id=p.responsible_office_id
  LEFT JOIN users u ON u.id=p.responsible_user_id
  WHERE li.deleted_at IS NULL ORDER BY p.due_date,p.created_at DESC"
)->fetchAll();

$pageTitle='Implementation Monitoring Report';$activeMenu='implementation';
$extraCss=[APP_URL.'/assets/css/orlms-lifecycle-ops.css'];
include __DIR__.'/../../layouts/header.php';
?>
<div class="orlms-app-layout"><?php include __DIR__.'/../../layouts/sidebar.php'; ?><main class="orlms-main-content"><div class="orl-head"><div><div class="orl-eyebrow">Operational Report</div><h1>Implementation Monitoring Report</h1><p>Implementation ownership, timeline, compliance percentage, updates and open implementation risks for published legislative items.</p></div><a class="btn btn-outline-secondary" href="index.php">Implementation Register</a></div><div class="card orl-card"><div class="table-responsive"><table class="table orl-table mb-0"><thead><tr><th>Plan</th><th>Item</th><th>Official No.</th><th>Responsible</th><th>Timeline</th><th>Compliance</th><th>Updates</th><th>Open Issues</th><th>Status</th></tr></thead><tbody><?php foreach($rows as $r): ?><tr><td><?= e($r['plan_reference']) ?><div class="small text-muted"><?= e(mb_strimwidth($r['objective'],0,70,'…')) ?></div></td><td><?= e($r['reference_number']) ?><div class="small text-muted"><?= e(mb_strimwidth($r['title'],0,60,'…')) ?></div></td><td><?= e($r['official_number']?:'—') ?></td><td><?= e($r['office_name']?:$r['user_name']?:'Unassigned') ?></td><td><?= e(($r['start_date']?:'—').' → '.($r['due_date']?:'—')) ?></td><td><?= round((float)$r['compliance_percent'],1) ?>%</td><td><?= (int)$r['updates'] ?></td><td><?= (int)$r['open_issues'] ?></td><td><?= e($r['status']) ?></td></tr><?php endforeach; ?></tbody></table></div></div></main></div>
<?php include __DIR__.'/../../layouts/footer.php'; ?>
