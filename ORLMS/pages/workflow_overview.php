<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/orlms_amendment_helpers.php';
requirePermission('orlms.workflow.view');

$pdo=db();$pageTitle='Life Cycle Workflow';$activeMenu='workflow_overview';
$extraCss=[APP_URL.'/assets/css/orlms-final-ops.css'];

$stageRows=$pdo->query(
 "SELECT
   CASE
    WHEN li.current_status IN ('Draft','Revision Required') THEN 'Drafting'
    WHEN li.current_status IN ('Submitted for Review','Under Review','Committee Reviewed') THEN 'Review'
    WHEN li.current_status='Endorsed' THEN 'Endorsement'
    WHEN li.current_status IN ('Pending Approval','Approved','Vetoed','Rejected') THEN 'Approval'
    WHEN li.current_status='Enacted' THEN 'Enactment'
    WHEN li.current_status='Published' THEN 'Publication'
    WHEN li.current_status IN ('Implementation In Progress','Implemented') THEN 'Implementation'
    WHEN li.current_status IN ('Amended','Repealed') THEN 'Revision'
    ELSE 'Other'
   END stage,
   COUNT(*) total
  FROM legislative_items li
  JOIN legislative_item_types lit ON lit.id=li.item_type_id
  WHERE li.deleted_at IS NULL AND lit.code IN ('ordinance','resolution')
  GROUP BY stage"
)->fetchAll();

$counts=[];
foreach($stageRows as $x)$counts[$x['stage']]=(int)$x['total'];

$recent=$pdo->query(
 "SELECT li.id,li.reference_number,li.title,li.current_status,lit.name item_type_name,
         o.name office_name,li.updated_at,
         (SELECT MAX(r.id) FROM orlms_reviews r WHERE r.legislative_item_id=li.id) review_id,
         (SELECT MAX(p.id) FROM orlms_publications p WHERE p.legislative_item_id=li.id) publication_id,
         (SELECT MAX(ip.id) FROM orlms_implementation_plans ip WHERE ip.legislative_item_id=li.id) implementation_id,
         (SELECT MAX(a.id) FROM orlms_amendments a WHERE a.legislative_item_id=li.id) amendment_id
  FROM legislative_items li
  JOIN legislative_item_types lit ON lit.id=li.item_type_id
  LEFT JOIN offices o ON o.id=li.originating_office_id
  WHERE li.deleted_at IS NULL AND lit.code IN ('ordinance','resolution')
  ORDER BY li.updated_at DESC LIMIT 30"
)->fetchAll();

$steps=[
 ['01','Drafting','Ordinance Drafting & Resolution Submission','bi-file-earmark-text','/modules/ordinances/index.php'],
 ['02','Review','Review & Committee Endorsement','bi-clipboard-check','/modules/reviews/index.php'],
 ['03','Endorsement','Committee Endorsement Handoff','bi-patch-check','/modules/reviews/index.php'],
 ['04','Approval','Approval & Enactment','bi-check2-circle','/modules/approvals/index.php'],
 ['05','Publication','Publication & Dissemination','bi-megaphone','/modules/publications/index.php'],
 ['06','Implementation','Implementation Monitoring','bi-activity','/modules/implementation/index.php'],
 ['07','Revision','Amendment & Revision Tracking','bi-clock-history','/modules/amendments/index.php'],
];

include __DIR__.'/../layouts/header.php';
?>
<div class="orlms-app-layout"><?php include __DIR__.'/../layouts/sidebar.php'; ?><main class="orlms-main-content">

<div class="of-head"><div><div class="of-eyebrow"><i class="bi bi-diagram-3"></i> Live Cross-Module Workflow</div><h1>Ordinance & Resolution Life Cycle</h1><p>This page now reads the actual shared ORLMS records and links every operational stage instead of showing a future/static concept.</p></div><a class="btn btn-primary" href="<?= e(APP_URL.'/reports/workflow.php') ?>"><i class="bi bi-signpost-split"></i> Detailed Workflow Trace</a></div>

<div class="of-flow"><?php foreach($steps as [$n,$stage,$title,$icon,$href]): ?><a href="<?= e(APP_URL.$href) ?>" class="text-decoration-none"><div><strong><?= e($n.' · '.$title) ?></strong><small><?= (int)($counts[$stage]??0) ?> current item(s)</small></div></a><?php endforeach; ?></div>

<div class="card of-card"><div class="card-header">Recent Legislative Workflow Records</div><div class="table-responsive"><table class="table table-hover of-table mb-0"><thead><tr><th>Item</th><th>Type / Office</th><th>Current Status</th><th>Last Updated</th><th class="text-end">Workflow Links</th></tr></thead><tbody>
<?php foreach($recent as $r): ?><tr><td><span class="of-code"><?= e($r['reference_number']) ?></span><div><strong><?= e($r['title']) ?></strong></div></td><td><?= e($r['item_type_name']) ?><div class="small text-muted"><?= e($r['office_name']?:'No office') ?></div></td><td><span class="of-status"><?= e($r['current_status']) ?></span></td><td><?= formatDateTime($r['updated_at']) ?></td><td class="text-end"><div class="btn-group btn-group-sm"><?php if($r['review_id']): ?><a class="btn btn-outline-primary" title="Review" href="<?= e(APP_URL.'/modules/reviews/view.php?id='.$r['review_id']) ?>"><i class="bi bi-clipboard-check"></i></a><?php endif; ?><a class="btn btn-outline-primary" title="Approval" href="<?= e(APP_URL.'/modules/approvals/view.php?id='.$r['id']) ?>"><i class="bi bi-patch-check"></i></a><?php if($r['publication_id']): ?><a class="btn btn-outline-primary" title="Publication" href="<?= e(APP_URL.'/modules/publications/view.php?id='.$r['publication_id']) ?>"><i class="bi bi-megaphone"></i></a><?php endif; ?><?php if($r['implementation_id']): ?><a class="btn btn-outline-primary" title="Implementation" href="<?= e(APP_URL.'/modules/implementation/view.php?id='.$r['implementation_id']) ?>"><i class="bi bi-activity"></i></a><?php endif; ?><?php if($r['amendment_id']): ?><a class="btn btn-outline-primary" title="Amendment" href="<?= e(APP_URL.'/modules/amendments/view.php?id='.$r['amendment_id']) ?>"><i class="bi bi-clock-history"></i></a><?php endif; ?><a class="btn btn-outline-dark" title="Trace" href="<?= e(APP_URL.'/reports/workflow.php?item_id='.$r['id']) ?>"><i class="bi bi-diagram-3"></i></a></div></td></tr><?php endforeach; ?>
</tbody></table></div></div>

</main></div>
<?php include __DIR__.'/../layouts/footer.php'; ?>
