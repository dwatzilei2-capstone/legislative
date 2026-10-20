<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/orlms_amendment_helpers.php';
requirePermission('orlms.dashboard.view');

$pdo=db();$pageTitle='Dashboard';$activeMenu='dashboard';
$extraCss=[APP_URL.'/assets/css/orlms-final-ops.css'];

$summary=$pdo->query(
 "SELECT
   COUNT(*) total,
   SUM(lit.code='ordinance') ordinances,
   SUM(lit.code='resolution') resolutions,
   SUM(li.current_status IN ('Draft','Revision Required')) drafting,
   SUM(li.current_status IN ('Submitted for Review','Under Review','Committee Reviewed')) review_stage,
   SUM(li.current_status='Endorsed') endorsed,
   SUM(li.current_status='Pending Approval') pending_approval,
   SUM(li.current_status='Approved') approved,
   SUM(li.current_status='Enacted') enacted,
   SUM(li.current_status='Published') published,
   SUM(li.current_status='Implementation In Progress') implementation,
   SUM(li.current_status='Implemented') implemented,
   SUM(li.current_status IN ('Amended','Repealed')) amended
  FROM legislative_items li
  JOIN legislative_item_types lit ON lit.id=li.item_type_id
  WHERE li.deleted_at IS NULL
    AND lit.code IN ('ordinance','resolution')"
)->fetch()?:[];

$workflow=[
 ['Drafting',(int)($summary['drafting']??0),'bi-pencil-square','ordinances'],
 ['Review',(int)($summary['review_stage']??0),'bi-clipboard-check','reviews'],
 ['Endorsed',(int)($summary['endorsed']??0),'bi-patch-check','reviews'],
 ['Approval',(int)($summary['pending_approval']??0)+(int)($summary['approved']??0),'bi-check2-circle','approvals'],
 ['Enacted',(int)($summary['enacted']??0),'bi-award','approvals'],
 ['Published',(int)($summary['published']??0),'bi-megaphone','publications'],
 ['Implementation',(int)($summary['implementation']??0)+(int)($summary['implemented']??0),'bi-activity','implementation'],
 ['Amendment',(int)($summary['amended']??0),'bi-clock-history','amendments'],
];

$reviewQueue=$pdo->query(
 "SELECT r.id,li.reference_number,li.title,r.status,c.name committee_name,r.created_at
  FROM orlms_reviews r
  JOIN legislative_items li ON li.id=r.legislative_item_id
  LEFT JOIN committees c ON c.id=r.committee_id
  WHERE li.deleted_at IS NULL AND r.status IN ('Pending','In Progress')
  ORDER BY r.created_at LIMIT 6"
)->fetchAll();

$approvalQueue=$pdo->query(
 "SELECT li.id,li.reference_number,li.title,li.current_status,a.action_reference,a.action_status
  FROM legislative_items li
  JOIN legislative_item_types lit ON lit.id=li.item_type_id
  LEFT JOIN orlms_approval_actions a
    ON a.id=(SELECT MAX(a2.id) FROM orlms_approval_actions a2 WHERE a2.legislative_item_id=li.id)
  WHERE li.deleted_at IS NULL
    AND lit.code IN ('ordinance','resolution')
    AND li.current_status IN ('Endorsed','Pending Approval','Approved')
  ORDER BY li.updated_at LIMIT 6"
)->fetchAll();

$scheduledPublications=$pdo->query(
 "SELECT p.id,p.publication_reference,p.publication_date,li.reference_number,li.title
  FROM orlms_publications p
  JOIN legislative_items li ON li.id=p.legislative_item_id
  WHERE p.publication_status='Scheduled'
  ORDER BY p.publication_date LIMIT 6"
)->fetchAll();

$overduePlans=$pdo->query(
 "SELECT p.id,p.plan_reference,p.due_date,p.compliance_percent,
         li.reference_number,li.title
  FROM orlms_implementation_plans p
  JOIN legislative_items li ON li.id=p.legislative_item_id
  WHERE p.due_date<CURDATE()
    AND p.status NOT IN ('Completed','Cancelled')
  ORDER BY p.due_date LIMIT 6"
)->fetchAll();

$activeAmendments=$pdo->query(
 "SELECT a.id,a.amendment_reference,a.title,a.status,li.reference_number
  FROM orlms_amendments a
  JOIN legislative_items li ON li.id=a.legislative_item_id
  WHERE a.status IN ('Draft','Under Review','Revision Required','Approved')
  ORDER BY a.updated_at DESC LIMIT 6"
)->fetchAll();

$monthly=$pdo->query(
 "SELECT DATE_FORMAT(li.created_at,'%Y-%m') ym,
         DATE_FORMAT(li.created_at,'%b %Y') label,
         SUM(lit.code='ordinance') ordinances,
         SUM(lit.code='resolution') resolutions
  FROM legislative_items li
  JOIN legislative_item_types lit ON lit.id=li.item_type_id
  WHERE li.deleted_at IS NULL
    AND lit.code IN ('ordinance','resolution')
    AND li.created_at>=DATE_SUB(CURDATE(),INTERVAL 11 MONTH)
  GROUP BY DATE_FORMAT(li.created_at,'%Y-%m'),DATE_FORMAT(li.created_at,'%b %Y')
  ORDER BY ym"
)->fetchAll();

$activity=$pdo->prepare(
 "SELECT al.action,al.details,al.created_at,u.full_name
  FROM activity_logs al
  LEFT JOIN users u ON u.id=al.user_id
  WHERE al.system_id=:system
  ORDER BY al.created_at DESC,al.id DESC LIMIT 50"
);
$activity->execute([':system'=>orlmsSystemId()]);$recentActivity=$activity->fetchAll();

include __DIR__.'/layouts/header.php';
?>
<div class="orlms-app-layout"><?php include __DIR__.'/layouts/sidebar.php'; ?><main class="orlms-main-content">

<div class="of-head"><div><div class="of-eyebrow"><i class="bi bi-speedometer2"></i> Step 9 · Live Operational Dashboard</div><h1>Ordinance & Resolution Life Cycle Management System Dashboard</h1><p>Real-time overview of ordinances and resolutions from drafting through committee review, approval, enactment, publication, implementation, amendment and official revision history.</p></div><div class="d-flex gap-2"><a class="btn btn-outline-secondary" href="<?= e(APP_URL.'/pages/workflow_overview.php') ?>"><i class="bi bi-diagram-3"></i> Workflow</a><a class="btn btn-primary" href="<?= e(APP_URL.'/reports/index.php') ?>"><i class="bi bi-bar-chart-line"></i> Reports</a></div></div>

<div class="row g-3 mb-3"><?php foreach([
 ['Legislative Items',$summary['total']??0,'bi-files'],
 ['Ordinances',$summary['ordinances']??0,'bi-file-earmark-text'],
 ['Resolutions',$summary['resolutions']??0,'bi-file-earmark-arrow-up'],
 ['Implemented',$summary['implemented']??0,'bi-check2-circle'],
] as [$l,$v,$i]): ?><div class="col-6 col-lg-3"><div class="of-stat"><i class="bi <?= e($i) ?>"></i><div><strong><?= (int)$v ?></strong><small><?= e($l) ?></small></div></div></div><?php endforeach; ?></div>

<div class="card of-card mb-3"><div class="card-header">Life Cycle Workload</div><div class="card-body"><div class="of-stage"><?php foreach($workflow as [$label,$count,$icon,$module]): ?><a href="<?= e(APP_URL.'/modules/'.$module.'/index.php') ?>" class="text-decoration-none"><div><i class="bi <?= e($icon) ?> text-primary"></i><span><?= $count ?></span><strong><?= e($label) ?></strong></div></a><?php endforeach; ?></div></div></div>

<div class="row g-3 mb-3">
<div class="col-xl-7"><div class="card of-card h-100"><div class="card-header">Legislative Items Created - Last 12 Months</div><div class="of-chartbox"><canvas id="monthlyChart"></canvas></div></div></div>
<div class="col-xl-5"><div class="card of-card h-100"><div class="card-header">Current Life Cycle Distribution</div><div class="of-chartbox"><canvas id="stageChart"></canvas></div></div></div>
</div>

<div class="row g-3">
<div class="col-xl-6">
<div class="card of-card mb-3"><div class="card-header d-flex justify-content-between"><span>Committee Review Queue</span><a class="btn btn-sm btn-outline-primary" href="<?= e(APP_URL.'/modules/reviews/index.php') ?>">Open</a></div><div class="table-responsive"><table class="table of-table mb-0"><thead><tr><th>Item</th><th>Committee</th><th>Status</th></tr></thead><tbody><?php if(!$reviewQueue): ?><tr><td colspan="3" class="text-muted text-center py-4">No pending committee review.</td></tr><?php endif; ?><?php foreach($reviewQueue as $r): ?><tr><td><a href="<?= e(APP_URL.'/modules/reviews/view.php?id='.$r['id']) ?>"><?= e($r['reference_number']) ?></a><div class="small text-muted"><?= e(mb_strimwidth($r['title'],0,65,'…')) ?></div></td><td><?= e($r['committee_name']?:'Unassigned') ?></td><td><?= e($r['status']) ?></td></tr><?php endforeach; ?></tbody></table></div></div>

<div class="card of-card mb-3"><div class="card-header d-flex justify-content-between"><span>Approval / Enactment Queue</span><a class="btn btn-sm btn-outline-primary" href="<?= e(APP_URL.'/modules/approvals/index.php') ?>">Open</a></div><div class="table-responsive"><table class="table of-table mb-0"><thead><tr><th>Item</th><th>Approval</th><th>Status</th></tr></thead><tbody><?php if(!$approvalQueue): ?><tr><td colspan="3" class="text-muted text-center py-4">No approval workload.</td></tr><?php endif; ?><?php foreach($approvalQueue as $r): ?><tr><td><a href="<?= e(APP_URL.'/modules/approvals/view.php?id='.$r['id']) ?>"><?= e($r['reference_number']) ?></a><div class="small text-muted"><?= e(mb_strimwidth($r['title'],0,65,'…')) ?></div></td><td><?= e($r['action_reference']?:'Awaiting action') ?></td><td><?= e($r['current_status']) ?></td></tr><?php endforeach; ?></tbody></table></div></div>

<div class="card of-card"><div class="card-header d-flex justify-content-between"><span>Active Amendments</span><a class="btn btn-sm btn-outline-primary" href="<?= e(APP_URL.'/modules/amendments/index.php') ?>">Open</a></div><div class="table-responsive"><table class="table of-table mb-0"><thead><tr><th>Amendment</th><th>Original</th><th>Status</th></tr></thead><tbody><?php if(!$activeAmendments): ?><tr><td colspan="3" class="text-muted text-center py-4">No active amendment workflow.</td></tr><?php endif; ?><?php foreach($activeAmendments as $r): ?><tr><td><a href="<?= e(APP_URL.'/modules/amendments/view.php?id='.$r['id']) ?>"><?= e($r['amendment_reference']) ?></a><div class="small text-muted"><?= e(mb_strimwidth($r['title'],0,60,'…')) ?></div></td><td><?= e($r['reference_number']) ?></td><td><?= e($r['status']) ?></td></tr><?php endforeach; ?></tbody></table></div></div>
</div>


<div class="col-xl-6">
<div class="card of-card mb-3"><div class="card-header d-flex justify-content-between"><span>Scheduled Publications</span><a class="btn btn-sm btn-outline-primary" href="<?= e(APP_URL.'/modules/publications/index.php') ?>">Open</a></div><div class="table-responsive"><table class="table of-table mb-0"><thead><tr><th>Publication</th><th>Item</th><th>Release Date</th></tr></thead><tbody><?php if(!$scheduledPublications): ?><tr><td colspan="3" class="text-muted text-center py-4">No scheduled publication.</td></tr><?php endif; ?><?php foreach($scheduledPublications as $r): ?><tr><td><a href="<?= e(APP_URL.'/modules/publications/view.php?id='.$r['id']) ?>"><?= e($r['publication_reference']) ?></a></td><td><?= e($r['reference_number']) ?><div class="small text-muted"><?= e(mb_strimwidth($r['title'],0,60,'…')) ?></div></td><td><?= formatDateTime($r['publication_date']) ?></td></tr><?php endforeach; ?></tbody></table></div></div>

<div class="card of-card mb-3"><div class="card-header d-flex justify-content-between"><span>Overdue Implementation Plans</span><a class="btn btn-sm btn-outline-primary" href="<?= e(APP_URL.'/modules/implementation/index.php') ?>">Open</a></div><div class="table-responsive"><table class="table of-table mb-0"><thead><tr><th>Plan</th><th>Item</th><th>Due / Progress</th></tr></thead><tbody><?php if(!$overduePlans): ?><tr><td colspan="3" class="text-muted text-center py-4">No overdue implementation plans.</td></tr><?php endif; ?><?php foreach($overduePlans as $r): ?><tr><td><a href="<?= e(APP_URL.'/modules/implementation/view.php?id='.$r['id']) ?>"><?= e($r['plan_reference']) ?></a></td><td><?= e($r['reference_number']) ?><div class="small text-muted"><?= e(mb_strimwidth($r['title'],0,55,'…')) ?></div></td><td><span class="text-danger"><?= formatDate($r['due_date']) ?></span><div class="small"><?= round((float)$r['compliance_percent'],1) ?>%</div></td></tr><?php endforeach; ?></tbody></table></div></div>

<div class="card of-card" id="orlmsRecentActivityCard">
    <div class="card-header">Recent ORLMS Activity</div>
    <div class="card-body of-history" id="orlmsActivityList">
        <?php if(!$recentActivity): ?><div class="text-muted small">No ORLMS activity logged yet.</div><?php endif; ?>
        <?php foreach($recentActivity as $idx => $r): ?>
            <div class="<?= $idx >= 5 ? 'orlms-activity-extra d-none' : '' ?>">
                <strong><?= e($r['action']) ?> · <?= e($r['full_name']?:'System') ?></strong>
                <small><?= e($r['details']?:'') ?><br><?= formatDateTime($r['created_at']) ?></small>
            </div>
        <?php endforeach; ?>
    </div>
    <?php if(count($recentActivity) > 5): ?>
        <div class="card-footer bg-transparent border-top-0 pt-0 pb-3 px-3">
            <button type="button" class="btn btn-sm btn-light border w-100 py-1 fw-semibold text-secondary d-flex align-items-center justify-content-center gap-2" id="btnToggleOrlmsActivityBottom" onclick="toggleOrlmsActivity()" style="font-size: 0.8rem; border-radius: 8px;">
                <i class="bi bi-chevron-down" id="iconToggleOrlmsActivityBottom"></i>
                <span id="textToggleOrlmsActivityBottom">See All (<?= count($recentActivity) ?>)</span>
            </button>
        </div>
    <?php endif; ?>
</div>
</div>
</div>

</main></div>

<script>
document.addEventListener('DOMContentLoaded',function(){
 const monthly=<?= json_encode($monthly,JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
 const stages=<?= json_encode(array_map(fn($x)=>['label'=>$x[0],'value'=>$x[1]],$workflow),JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
 new Chart(document.getElementById('monthlyChart'),{
   type:'bar',
   data:{
     labels:monthly.map(x=>x.label),
     datasets:[
       {label:'Ordinances',data:monthly.map(x=>Number(x.ordinances)),backgroundColor:'#b8860b',borderColor:'#8d6608',borderWidth:1,borderRadius:4},
       {label:'Resolutions',data:monthly.map(x=>Number(x.resolutions)),backgroundColor:'#071426',borderColor:'#0f2137',borderWidth:1,borderRadius:4}
     ]
   },
   options:{responsive:true,maintainAspectRatio:false,scales:{y:{beginAtZero:true,ticks:{precision:0}}}}
 });
 new Chart(document.getElementById('stageChart'),{
   type:'doughnut',
   data:{
     labels:stages.map(x=>x.label),
     datasets:[{
       data:stages.map(x=>Number(x.value)),
       backgroundColor:['#b8860b','#071426','#1a3a5c','#0f2137','#d97706','#0284c7','#3b82f6','#475569'],
       borderWidth:2,
       borderColor:'#ffffff'
     }]
   },
   options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom'}}}
  });
});
</script>
<style>
.orlms-activity-extra:not(.d-none) {
    animation: fadeInOrlmsActivity 0.25s ease-in-out;
}
@keyframes fadeInOrlmsActivity {
    from { opacity: 0; transform: translateY(-3px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>
<script>
function toggleOrlmsActivity() {
  const extras = document.querySelectorAll('.orlms-activity-extra');
  if (!extras.length) return;
  const isHidden = extras[0].classList.contains('d-none');

  extras.forEach(el => {
    if (isHidden) {
      el.classList.remove('d-none');
    } else {
      el.classList.add('d-none');
    }
  });

  const totalCount = extras.length + 5;
  const textBottom = document.getElementById('textToggleOrlmsActivityBottom');
  const iconBottom = document.getElementById('iconToggleOrlmsActivityBottom');

  if (isHidden) {
    if (textBottom) textBottom.textContent = 'Hide';
    if (iconBottom) iconBottom.className = 'bi bi-chevron-up';
  } else {
    if (textBottom) textBottom.textContent = 'See All (' + totalCount + ')';
    if (iconBottom) iconBottom.className = 'bi bi-chevron-down';

    const card = document.getElementById('orlmsRecentActivityCard');
    if (card) {
      card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
  }
}
</script>
<?php include __DIR__.'/layouts/footer.php'; ?>
