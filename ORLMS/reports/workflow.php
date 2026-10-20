<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/orlms_report_helpers.php';
requirePermission('orlms.reports.view');

$pdo=db();$itemId=(int)($_GET['item_id']??0)?:null;
$rows=orlmsWorkflowTraceRows($pdo,$itemId);

$pageTitle='Cross-Module Workflow Trace';$activeMenu='reports';
$extraCss=[APP_URL.'/assets/css/orlms-final-ops.css'];

include __DIR__.'/../layouts/header.php';
?>
<div class="orlms-app-layout"><?php include __DIR__.'/../layouts/sidebar.php'; ?><main class="orlms-main-content">
<div class="of-head"><div><a href="index.php" class="small text-decoration-none"><i class="bi bi-arrow-left"></i> Reports Center</a><div class="of-eyebrow mt-2">Cross-Module Traceability</div><h1>Legislative Life Cycle Workflow Trace</h1><p>Follow each shared legislative item through its latest review, committee endorsement, approval, enactment, publication, implementation and amendment records.</p></div><div class="d-flex gap-2"><a class="btn btn-outline-secondary" target="_blank" href="print.php?report=workflow<?= $itemId?'&item_id='.$itemId:'' ?>"><i class="bi bi-printer"></i> Print</a><a class="btn btn-outline-success" href="export_excel.php?report=workflow<?= $itemId?'&item_id='.$itemId:'' ?>"><i class="bi bi-file-earmark-excel"></i> Excel</a><a class="btn btn-primary" href="export_pdf.php?report=workflow<?= $itemId?'&item_id='.$itemId:'' ?>"><i class="bi bi-file-earmark-pdf"></i> PDF</a></div></div>

<div class="card of-card"><div class="table-responsive"><table class="table table-hover of-table mb-0"><thead><tr><th>Legislative Item</th><th>Review / Endorsement</th><th>Approval</th><th>Enactment</th><th>Publication</th><th>Implementation</th><th>Amendment / Revision</th><th>Current Status</th></tr></thead><tbody>
<?php foreach($rows as $r): ?><tr>
<td><span class="of-code"><?= e($r['reference_number']) ?></span><div><strong><?= e($r['title']) ?></strong></div><div class="small text-muted"><?= e($r['item_type_name']) ?></div></td>
<td><?php if($r['review_id']): ?><a href="<?= e(APP_URL.'/modules/reviews/view.php?id='.$r['review_id']) ?>">Round <?= (int)$r['review_round'] ?> · <?= e($r['review_status']) ?></a><div class="small text-muted"><?= e($r['committee_name']?:'No committee') ?> · <?= e($r['endorsement_status']?:$r['review_recommendation']?:'Pending') ?></div><?php else: ?>—<?php endif; ?></td>
<td><?php if($r['approval_id']): ?><a href="<?= e(APP_URL.'/modules/approvals/view.php?id='.$r['id']) ?>"><?= e($r['action_reference']) ?></a><div class="small text-muted"><?= e($r['action_status']) ?></div><?php else: ?>—<?php endif; ?></td>
<td><?= e($r['official_number']?:'—') ?><div class="small text-muted"><?= e($r['enactment_status']?:'') ?><?= $r['enactment_date']?' · '.formatDate($r['enactment_date']):'' ?></div></td>
<td><?php if($r['publication_id']): ?><a href="<?= e(APP_URL.'/modules/publications/view.php?id='.$r['publication_id']) ?>"><?= e($r['publication_reference']) ?></a><div class="small text-muted"><?= e($r['publication_status']) ?><?= $r['publication_date']?' · '.formatDateTime($r['publication_date']):'' ?></div><?php else: ?>—<?php endif; ?></td>
<td><?= (int)$r['implementation_completed'] ?> / <?= (int)$r['implementation_count'] ?> plan(s)<div class="small text-muted"><?= $r['implementation_average']!==null?round((float)$r['implementation_average'],1).'% average':'—' ?></div></td>
<td><?php if($r['amendment_id']): ?><a href="<?= e(APP_URL.'/modules/amendments/view.php?id='.$r['amendment_id']) ?>"><?= e($r['amendment_reference']) ?></a><div class="small text-muted"><?= e($r['amendment_status']) ?><?= $r['applied_at']?' · '.formatDateTime($r['applied_at']):'' ?></div><?php else: ?>—<?php endif; ?></td>
<td><span class="of-status"><?= e($r['current_status']) ?></span></td>
</tr><?php endforeach; ?>
</tbody></table></div></div>
</main></div>
<?php include __DIR__.'/../layouts/footer.php'; ?>
