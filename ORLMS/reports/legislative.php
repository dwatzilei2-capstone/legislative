<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/orlms_report_helpers.php';
requirePermission('orlms.reports.view');

$pdo=db();$f=orlmsReportFilterValues();$rows=orlmsLegislativeReportRows($pdo,$f);
$statuses=orlmsReportStatusList($pdo);
$pageTitle='Legislative Registry Report';$activeMenu='reports';
$extraCss=[APP_URL.'/assets/css/orlms-final-ops.css'];

$query=http_build_query(array_filter($f,fn($x)=>$x!==''));

include __DIR__.'/../layouts/header.php';
?>
<div class="orlms-app-layout"><?php include __DIR__.'/../layouts/sidebar.php'; ?><main class="orlms-main-content">
<div class="of-head"><div><a href="index.php" class="small text-decoration-none"><i class="bi bi-arrow-left"></i> Reports Center</a><div class="of-eyebrow mt-2">Legislative Registry</div><h1>Ordinance & Resolution Registry Report</h1><p>Filter the complete ORLMS master registry and review status, official numbering, publication, implementation, amendment and revision indicators.</p></div><div class="d-flex gap-2"><a class="btn btn-outline-secondary" target="_blank" href="print.php?report=legislative&<?= e($query) ?>"><i class="bi bi-printer"></i> Print</a><a class="btn btn-outline-success" href="export_excel.php?report=legislative&<?= e($query) ?>"><i class="bi bi-file-earmark-excel"></i> Excel</a><a class="btn btn-primary" href="export_pdf.php?report=legislative&<?= e($query) ?>"><i class="bi bi-file-earmark-pdf"></i> PDF</a></div></div>

<div class="card of-card mb-3"><div class="card-body"><form class="row g-2 align-items-end">
<div class="col-xl-4"><label class="form-label small">Search</label><input class="form-control form-control-sm" name="search" value="<?= e($f['search']) ?>" placeholder="Reference, title or official number"></div>
<div class="col-xl-2"><label class="form-label small">Type</label><select class="form-select form-select-sm" name="type"><option value="">Both</option><option value="ordinance" <?= $f['type']==='ordinance'?'selected':'' ?>>Ordinance</option><option value="resolution" <?= $f['type']==='resolution'?'selected':'' ?>>Resolution</option></select></div>
<div class="col-xl-2"><label class="form-label small">Status</label><select class="form-select form-select-sm" name="status"><option value="">All</option><?php foreach($statuses as $s): ?><option <?= $f['status']===$s?'selected':'' ?>><?= e($s) ?></option><?php endforeach; ?></select></div>
<div class="col-xl-1"><label class="form-label small">From</label><input type="date" class="form-control form-control-sm" name="date_from" value="<?= e($f['date_from']) ?>"></div>
<div class="col-xl-1"><label class="form-label small">To</label><input type="date" class="form-control form-control-sm" name="date_to" value="<?= e($f['date_to']) ?>"></div>
<div class="col-xl-2"><button class="btn btn-outline-primary btn-sm w-100"><i class="bi bi-funnel"></i> Apply</button></div>
</form></div></div>

<div class="card of-card"><div class="card-header d-flex justify-content-between"><span>Legislative Registry</span><span><?= count($rows) ?> record(s)</span></div><div class="table-responsive"><table class="table table-hover of-table mb-0"><thead><tr><th>Reference / Title</th><th>Type / Office</th><th>Official No.</th><th>Status</th><th>Review Rounds</th><th>Published</th><th>Implementation Plans</th><th>Amendments</th><th>Revision</th><th>Updated</th></tr></thead><tbody>
<?php if(!$rows): ?><tr><td colspan="10" class="text-center text-muted py-5">No records match the report filters.</td></tr><?php endif; ?>
<?php foreach($rows as $r): ?><tr><td><span class="of-code"><?= e($r['reference_number']) ?></span><div><strong><?= e($r['title']) ?></strong></div><div class="small text-muted"><?= e($r['subject']?:'') ?></div></td><td><?= e($r['item_type_name']) ?><div class="small text-muted"><?= e($r['office_name']?:'No office') ?></div></td><td><?= e($r['official_number']?:'—') ?></td><td><?= e($r['current_status']) ?></td><td><?= (int)$r['review_rounds'] ?></td><td><?= (int)$r['published_count'] ?></td><td><?= (int)$r['implementation_plans'] ?></td><td><?= (int)$r['amendment_count'] ?></td><td>v<?= (int)($r['revision_version']??0) ?></td><td><?= formatDateTime($r['updated_at']) ?></td></tr><?php endforeach; ?>
</tbody></table></div></div>
</main></div>
<?php include __DIR__.'/../layouts/footer.php'; ?>
