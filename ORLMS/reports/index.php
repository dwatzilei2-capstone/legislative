<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/orlms_report_helpers.php';
requirePermission('orlms.reports.view');

$pdo=db();$pageTitle='Reports & Analytics';$activeMenu='reports';
$extraCss=[APP_URL.'/assets/css/orlms-final-ops.css'];

$summary=$pdo->query(
 "SELECT
   COUNT(*) total_items,
   SUM(lit.code='ordinance') ordinances,
   SUM(lit.code='resolution') resolutions,
   SUM(li.current_status='Enacted') enacted,
   SUM(li.current_status='Published') published,
   SUM(li.current_status='Implemented') implemented,
   SUM(li.current_status IN ('Amended','Repealed')) revised
  FROM legislative_items li
  JOIN legislative_item_types lit ON lit.id=li.item_type_id
  WHERE li.deleted_at IS NULL AND lit.code IN ('ordinance','resolution')"
)->fetch()?:[];

$reviewCount=(int)$pdo->query('SELECT COUNT(*) FROM orlms_reviews')->fetchColumn();
$approvalCount=(int)$pdo->query('SELECT COUNT(*) FROM orlms_approval_actions')->fetchColumn();
$publicationCount=(int)$pdo->query('SELECT COUNT(*) FROM orlms_publications')->fetchColumn();
$planCount=(int)$pdo->query('SELECT COUNT(*) FROM orlms_implementation_plans')->fetchColumn();
$amendmentCount=(int)$pdo->query('SELECT COUNT(*) FROM orlms_amendments')->fetchColumn();

$statusRows=$pdo->query(
 "SELECT li.current_status label,COUNT(*) total
  FROM legislative_items li
  JOIN legislative_item_types lit ON lit.id=li.item_type_id
  WHERE li.deleted_at IS NULL AND lit.code IN ('ordinance','resolution')
  GROUP BY li.current_status ORDER BY total DESC"
)->fetchAll();

$typeRows=$pdo->query(
 "SELECT lit.name label,COUNT(*) total
  FROM legislative_items li
  JOIN legislative_item_types lit ON lit.id=li.item_type_id
  WHERE li.deleted_at IS NULL AND lit.code IN ('ordinance','resolution')
  GROUP BY lit.id,lit.name ORDER BY lit.name"
)->fetchAll();

include __DIR__.'/../layouts/header.php';
?>
<div class="orlms-app-layout"><?php include __DIR__.'/../layouts/sidebar.php'; ?><main class="orlms-main-content">

<div class="of-head"><div><div class="of-eyebrow"><i class="bi bi-file-earmark-bar-graph"></i> Step 9 · Consolidated Reports</div><h1>Reports & Analytics Center</h1><p>Consolidated reporting across drafting, review, endorsement, approval, enactment, publication, implementation, amendments and official revision history.</p></div><div class="d-flex gap-2"><a class="btn btn-outline-secondary" target="_blank" href="print.php?report=summary"><i class="bi bi-printer"></i> Print Summary</a><a class="btn btn-primary" href="export_excel.php?report=legislative"><i class="bi bi-file-earmark-excel"></i> Export Registry</a></div></div>

<div class="row g-3 mb-3"><?php foreach([
 ['Legislative Items',$summary['total_items']??0,'bi-files'],
 ['Committee Reviews',$reviewCount,'bi-clipboard-check'],
 ['Approval Actions',$approvalCount,'bi-patch-check'],
 ['Publications',$publicationCount,'bi-megaphone'],
 ['Implementation Plans',$planCount,'bi-activity'],
 ['Amendments',$amendmentCount,'bi-clock-history'],
] as [$l,$v,$i]): ?><div class="col-6 col-xl-2"><div class="of-stat"><i class="bi <?= e($i) ?>"></i><div><strong><?= (int)$v ?></strong><small><?= e($l) ?></small></div></div></div><?php endforeach; ?></div>

<div class="row g-3 mb-3">
<div class="col-lg-4"><a class="text-decoration-none" href="legislative.php"><div class="card of-card h-100"><div class="card-body"><i class="bi bi-journal-text fs-3 text-primary"></i><h5 class="mt-2">Legislative Registry</h5><p class="small text-muted">Search and filter every ordinance/resolution with current status, official number, revision version and module counts.</p></div></div></a></div>
<div class="col-lg-4"><a class="text-decoration-none" href="workflow.php"><div class="card of-card h-100"><div class="card-body"><i class="bi bi-diagram-3 fs-3 text-primary"></i><h5 class="mt-2">Cross-Module Workflow Trace</h5><p class="small text-muted">Trace Draft -> Review -> Approval -> Enactment -> Publication -> Implementation -> Amendment in one record.</p></div></div></a></div>
<div class="col-lg-4"><a class="text-decoration-none" href="<?= e(APP_URL.'/modules/amendments/report.php') ?>"><div class="card of-card h-100"><div class="card-body"><i class="bi bi-file-earmark-diff fs-3 text-primary"></i><h5 class="mt-2">Revision & Amendment Report</h5><p class="small text-muted">Review source versions, amendment states, effectivity and current official revision numbers.</p></div></div></a></div>
</div>

<div class="row g-3">
<div class="col-xl-7"><div class="card of-card h-100"><div class="card-header">Current Status Distribution</div><div class="of-chartbox"><canvas id="statusChart"></canvas></div></div></div>
<div class="col-xl-5"><div class="card of-card h-100"><div class="card-header">Ordinance vs Resolution</div><div class="of-chartbox"><canvas id="typeChart"></canvas></div></div></div>
</div>

</main></div>
<script>
document.addEventListener('DOMContentLoaded',function(){
 const status=<?= json_encode($statusRows,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
 const types=<?= json_encode($typeRows,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
 new Chart(document.getElementById('statusChart'),{
   type:'bar',
   data:{
     labels:status.map(x=>x.label),
     datasets:[{label:'Items',data:status.map(x=>Number(x.total)),backgroundColor:'#b8860b',borderColor:'#8d6608',borderWidth:1,borderRadius:4}]
   },
   options:{responsive:true,maintainAspectRatio:false,scales:{y:{beginAtZero:true,ticks:{precision:0}}},plugins:{legend:{display:false}}}
 });
 new Chart(document.getElementById('typeChart'),{
   type:'doughnut',
   data:{
     labels:types.map(x=>x.label),
     datasets:[{data:types.map(x=>Number(x.total)),backgroundColor:['#b8860b','#071426','#1a3a5c','#0284c7'],borderWidth:2,borderColor:'#ffffff'}]
   },
   options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom'}}}
 });
});
</script>
<?php include __DIR__.'/../layouts/footer.php'; ?>
