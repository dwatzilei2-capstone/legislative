<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
requirePermission('orlms.amendments.view');

$rows=db()->query(
 "SELECT a.amendment_reference,a.amendment_type,a.title amendment_title,a.status,
         a.source_revision_version,a.effective_date,a.proposed_at,a.approved_at,a.applied_at,
         li.reference_number,li.title legislative_title,en.official_number,
         (SELECT COUNT(*) FROM orlms_amendment_changes c WHERE c.amendment_id=a.id) changes,
         (SELECT MAX(rh.version_number) FROM orlms_revision_history rh WHERE rh.legislative_item_id=li.id) current_revision
  FROM orlms_amendments a
  JOIN legislative_items li ON li.id=a.legislative_item_id
  LEFT JOIN orlms_enactments en ON en.legislative_item_id=li.id
  WHERE li.deleted_at IS NULL
  ORDER BY a.created_at DESC"
)->fetchAll();

$pageTitle='Amendment & Revision Report';$activeMenu='amendments';
$extraCss=[APP_URL.'/assets/css/orlms-final-ops.css'];
include __DIR__.'/../../layouts/header.php';
?>
<div class="orlms-app-layout"><?php include __DIR__.'/../../layouts/sidebar.php'; ?><main class="orlms-main-content"><div class="of-head"><div><div class="of-eyebrow">Operational Report</div><h1>Amendment & Revision Report</h1><p>Amendment proposals, source revisions, approval/application status, affected provisions and the resulting official revision number.</p></div><a class="btn btn-outline-secondary" href="index.php">Amendment Registry</a></div><div class="card of-card"><div class="table-responsive"><table class="table of-table mb-0"><thead><tr><th>Amendment</th><th>Original Legislation</th><th>Official No.</th><th>Type</th><th>Source Rev.</th><th>Changes</th><th>Current Rev.</th><th>Effective</th><th>Status</th></tr></thead><tbody><?php foreach($rows as $r): ?><tr><td><?= e($r['amendment_reference']) ?><div class="small text-muted"><?= e(mb_strimwidth($r['amendment_title'],0,70,'…')) ?></div></td><td><?= e($r['reference_number']) ?><div class="small text-muted"><?= e(mb_strimwidth($r['legislative_title'],0,60,'…')) ?></div></td><td><?= e($r['official_number']?:'—') ?></td><td><?= e($r['amendment_type']) ?></td><td>v<?= (int)$r['source_revision_version'] ?></td><td><?= (int)$r['changes'] ?></td><td>v<?= (int)$r['current_revision'] ?></td><td><?= $r['effective_date']?formatDate($r['effective_date']):'—' ?></td><td><?= e($r['status']) ?></td></tr><?php endforeach; ?></tbody></table></div></div></main></div>
<?php include __DIR__.'/../../layouts/footer.php'; ?>
