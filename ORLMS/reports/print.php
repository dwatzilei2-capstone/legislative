<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/orlms_report_helpers.php';
requirePermission('orlms.reports.view');

$pdo=db();$report=clean($_GET['report']??'summary');

if($report==='workflow'){
    $rows=orlmsWorkflowTraceRows($pdo,(int)($_GET['item_id']??0)?:null);
}elseif($report==='legislative'){
    $rows=orlmsLegislativeReportRows($pdo,orlmsReportFilterValues());
}else{
    $rows=$pdo->query(
      "SELECT li.reference_number,li.title,li.current_status,lit.name item_type_name,
              en.official_number,li.updated_at
       FROM legislative_items li
       JOIN legislative_item_types lit ON lit.id=li.item_type_id
       LEFT JOIN orlms_enactments en ON en.legislative_item_id=li.id
       WHERE li.deleted_at IS NULL AND lit.code IN ('ordinance','resolution')
       ORDER BY li.updated_at DESC"
    )->fetchAll();
}
?>
<!doctype html><html><head><meta charset="utf-8"><title>ORLMS <?= e(ucfirst($report)) ?> Report</title><link rel="icon" type="image/png" href="../assets/images/manila.png"><style>body{font:11px Arial;margin:25px;color:#111827}.head{text-align:center;border-bottom:3px solid #0f2137;padding-bottom:12px;margin-bottom:15px}.head img{height:54px;width:54px;object-fit:contain;margin-bottom:6px}table{width:100%;border-collapse:collapse}th,td{border:1px solid #d1d5db;padding:5px;vertical-align:top}th{background:#f3f4f6}</style></head><body onload="window.print()"><div class="head"><img src="../assets/images/manila.png" alt="City of Manila Seal"><br><strong><?= e(APP_NAME) ?></strong><h2><?= e(ucwords(str_replace('_',' ',$report))) ?> Report</h2><div>Generated <?= date('F j, Y g:i A') ?></div></div>
<?php if($report==='workflow'): ?><table><thead><tr><th>Item</th><th>Review</th><th>Approval</th><th>Enactment</th><th>Publication</th><th>Implementation</th><th>Amendment</th><th>Status</th></tr></thead><tbody><?php foreach($rows as $r): ?><tr><td><?= e($r['reference_number'].' - '.$r['title']) ?></td><td><?= e($r['review_id']?('R'.$r['review_round'].' '.$r['review_status'].' / '.($r['endorsement_status']?:'')):'—') ?></td><td><?= e($r['action_reference']?($r['action_reference'].' '.$r['action_status']):'—') ?></td><td><?= e($r['official_number']?:'—') ?></td><td><?= e($r['publication_reference']?($r['publication_reference'].' '.$r['publication_status']):'—') ?></td><td><?= (int)$r['implementation_completed'] ?>/<?= (int)$r['implementation_count'] ?></td><td><?= e($r['amendment_reference']?($r['amendment_reference'].' '.$r['amendment_status']):'—') ?></td><td><?= e($r['current_status']) ?></td></tr><?php endforeach; ?></tbody></table>
<?php elseif($report==='legislative'): ?><table><thead><tr><th>Reference</th><th>Title</th><th>Type</th><th>Official No.</th><th>Status</th><th>Review</th><th>Publication</th><th>Implementation</th><th>Amendments</th><th>Revision</th></tr></thead><tbody><?php foreach($rows as $r): ?><tr><td><?= e($r['reference_number']) ?></td><td><?= e($r['title']) ?></td><td><?= e($r['item_type_name']) ?></td><td><?= e($r['official_number']?:'—') ?></td><td><?= e($r['current_status']) ?></td><td><?= (int)$r['review_rounds'] ?></td><td><?= (int)$r['published_count'] ?></td><td><?= (int)$r['implementation_plans'] ?></td><td><?= (int)$r['amendment_count'] ?></td><td>v<?= (int)($r['revision_version']??0) ?></td></tr><?php endforeach; ?></tbody></table>
<?php else: ?><table><thead><tr><th>Reference</th><th>Title</th><th>Type</th><th>Official No.</th><th>Status</th><th>Updated</th></tr></thead><tbody><?php foreach($rows as $r): ?><tr><td><?= e($r['reference_number']) ?></td><td><?= e($r['title']) ?></td><td><?= e($r['item_type_name']) ?></td><td><?= e($r['official_number']?:'—') ?></td><td><?= e($r['current_status']) ?></td><td><?= e($r['updated_at']) ?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?>
</body></html>
