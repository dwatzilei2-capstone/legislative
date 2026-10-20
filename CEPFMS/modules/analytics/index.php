<?php
declare(strict_types=1);

require_once __DIR__.'/../../includes/cepfms_operational_helpers.php';
requireCefPermission('cepfms.analytics.view');

$pdo=db();$pageTitle='Citizen Engagement Analytics';$activeMenu='analytics';
$extraCss=[appUrl('assets/css/cepfms-operational.css')];

$summary=cefAnalyticsSummary($pdo);
$monthly=cefMonthlyTrend($pdo,6);

$typeDist=$pdo->query(
 "SELECT submission_type label,COUNT(*) total
  FROM cef_submissions
  WHERE deleted_at IS NULL
  GROUP BY submission_type
  ORDER BY total DESC"
)->fetchAll();

$statusDist=$pdo->query(
 "SELECT status label,COUNT(*) total
  FROM cef_submissions
  WHERE deleted_at IS NULL
  GROUP BY status
  ORDER BY total DESC"
)->fetchAll();

$categories=$pdo->query(
 "SELECT COALESCE(c.name,'Unclassified') label,COUNT(*) total
  FROM cef_submissions s
  LEFT JOIN cef_categories c ON c.id=s.category_id
  WHERE s.deleted_at IS NULL
  GROUP BY COALESCE(c.name,'Unclassified')
  ORDER BY total DESC,label
  LIMIT 10"
)->fetchAll();

$locations=$pdo->query(
 "SELECT COALESCE(NULLIF(barangay,''),NULLIF(district,''),NULLIF(location_text,''),'Unspecified') label,
         COUNT(*) total
  FROM cef_submissions
  WHERE deleted_at IS NULL
  GROUP BY COALESCE(NULLIF(barangay,''),NULLIF(district,''),NULLIF(location_text,''),'Unspecified')
  ORDER BY total DESC,label
  LIMIT 10"
)->fetchAll();

$complaintPerformance=$pdo->query(
 "SELECT
    COUNT(*) total,
    SUM(s.status IN ('Resolved','Closed')) resolved_count,
    SUM(s.status NOT IN ('Resolved','Closed','Rejected','Withdrawn') AND c.resolution_target_at<NOW()) overdue_open,
    SUM(c.resolved_at IS NOT NULL AND c.resolved_at<=c.resolution_target_at) resolved_on_time,
    ROUND(AVG(CASE WHEN c.resolved_at IS NOT NULL THEN TIMESTAMPDIFF(HOUR,s.created_at,c.resolved_at) END),1) avg_resolution_hours
  FROM cef_submissions s
  JOIN cef_complaints c ON c.submission_id=s.id
  WHERE s.deleted_at IS NULL"
)->fetch()?:[];

$responsePerformance=$pdo->query(
 "SELECT COUNT(*) total,
         SUM(status='Delivered') delivered,
         ROUND(AVG(CASE WHEN delivered_at IS NOT NULL THEN TIMESTAMPDIFF(HOUR,created_at,delivered_at) END),1) avg_draft_to_delivery_hours
  FROM cef_responses"
)->fetch()?:[];

$rating=$pdo->query(
 "SELECT
    COUNT(citizen_rating) rated,
    ROUND(AVG(citizen_rating),2) avg_rating,
    SUM(citizen_rating>=4) positive_proxy,
    SUM(citizen_rating=3) neutral_proxy,
    SUM(citizen_rating<=2) negative_proxy
  FROM cef_feedback_submissions
  WHERE citizen_rating IS NOT NULL"
)->fetch()?:[];

$alerts=$pdo->query(
 "SELECT a.*,u.full_name created_name
  FROM cef_analytics_alerts a
  LEFT JOIN users u ON u.id=a.created_by
  WHERE a.status='Open'
  ORDER BY FIELD(a.severity,'High','Attention','Monitor'),a.created_at DESC
  LIMIT 20"
)->fetchAll();

$recent=$pdo->query(
 "SELECT s.reference_number,s.submission_type,s.title,s.status,s.priority_level,s.created_at,
         c.name category_name
  FROM cef_submissions s
  LEFT JOIN cef_categories c ON c.id=s.category_id
  WHERE s.deleted_at IS NULL
  ORDER BY s.created_at DESC
  LIMIT 10"
)->fetchAll();

include __DIR__.'/../../layouts/header.php';
?>
<div class="app-wrapper"><?php include __DIR__.'/../../layouts/sidebar.php'; ?><main class="main-content">

<div class="cef-head"><div><div class="cef-eyebrow"><i class="bi bi-graph-up-arrow"></i> Step 7 · Civic Intelligence</div><h1>Citizen Engagement Analytics</h1><p>Live civic-engagement trends, recurring concerns, complaint service-level performance, response delivery performance, location patterns, category distribution, and rating-based sentiment proxy.</p></div><div class="d-flex gap-2"><?php if(cefHasPermission('cepfms.analytics.manage')): ?><button class="btn btn-primary" id="btnSnapshot"><i class="bi bi-camera"></i> Save Snapshot</button><?php endif; ?><a class="btn btn-outline-secondary" href="report.php">Report</a></div></div>

<div class="row g-3 mb-3"><?php foreach([
 [$summary['total'],'Total Submissions','bi-inboxes'],
 [$summary['pending_moderation'],'Pending Moderation','bi-shield-check'],
 [$summary['open_complaints'],'Open Complaints','bi-exclamation-diamond'],
 [$summary['overdue_complaints'],'Overdue Complaints','bi-alarm'],
 [$summary['delivered_responses'],'Delivered Responses','bi-send-check'],
 [count($alerts),'Open Trend Alerts','bi-bullseye']
] as [$v,$l,$i]): ?><div class="col-6 col-xl-2"><div class="cef-stat"><i class="bi <?= e($i) ?>"></i><div><strong><?= (int)$v ?></strong><small><?= e($l) ?></small></div></div></div><?php endforeach; ?></div>

<div class="row g-4 mb-4">
<div class="col-xl-8"><div class="card cef-card h-100"><div class="card-header card-header-clean">Six-Month Citizen Submission Trend</div><div class="card-body"><canvas id="monthlyChart" height="110"></canvas></div></div></div>
<div class="col-xl-4"><div class="card cef-card h-100"><div class="card-header card-header-clean">Submission Type Distribution</div><div class="card-body"><canvas id="typeChart" height="170"></canvas></div></div></div>
</div>

<div class="row g-4 mb-4">
<div class="col-xl-6"><div class="card cef-card h-100"><div class="card-header card-header-clean">Top Civic Categories</div><div class="card-body"><canvas id="categoryChart" height="150"></canvas></div></div></div>
<div class="col-xl-6"><div class="card cef-card h-100"><div class="card-header card-header-clean">Top Locations</div><div class="card-body"><canvas id="locationChart" height="150"></canvas></div></div></div>
</div>

<div class="row g-4 mb-4">
<div class="col-xl-4"><div class="card cef-card h-100"><div class="card-header">Complaint SLA Performance</div><div class="card-body"><div class="cef-grid" style="grid-template-columns:1fr 1fr"><div><small>Total Complaints</small><strong><?= (int)($complaintPerformance['total']??0) ?></strong></div><div><small>Resolved</small><strong><?= (int)($complaintPerformance['resolved_count']??0) ?></strong></div><div><small>Open Overdue</small><strong><?= (int)($complaintPerformance['overdue_open']??0) ?></strong></div><div><small>Resolved On Time</small><strong><?= (int)($complaintPerformance['resolved_on_time']??0) ?></strong></div><div><small>Avg Resolution</small><strong><?= $complaintPerformance['avg_resolution_hours']!==null?e($complaintPerformance['avg_resolution_hours'].' h'):'—' ?></strong></div></div></div></div></div>
<div class="col-xl-4"><div class="card cef-card h-100"><div class="card-header">Response Performance</div><div class="card-body"><div class="cef-grid" style="grid-template-columns:1fr 1fr"><div><small>Responses</small><strong><?= (int)($responsePerformance['total']??0) ?></strong></div><div><small>Delivered</small><strong><?= (int)($responsePerformance['delivered']??0) ?></strong></div><div><small>Avg Draft → Delivery</small><strong><?= $responsePerformance['avg_draft_to_delivery_hours']!==null?e($responsePerformance['avg_draft_to_delivery_hours'].' h'):'—' ?></strong></div></div></div></div></div>
<div class="col-xl-4"><div class="card cef-card h-100"><div class="card-header">Feedback Rating Proxy</div><div class="card-body"><div class="cef-alert mb-3">This is a rating-based proxy, not AI text sentiment analysis.</div><div class="cef-grid" style="grid-template-columns:1fr 1fr"><div><small>Rated Feedback</small><strong><?= (int)($rating['rated']??0) ?></strong></div><div><small>Average Rating</small><strong><?= $rating['avg_rating']!==null?e($rating['avg_rating'].' / 5'):'—' ?></strong></div><div><small>Positive Proxy</small><strong><?= (int)($rating['positive_proxy']??0) ?></strong></div><div><small>Neutral Proxy</small><strong><?= (int)($rating['neutral_proxy']??0) ?></strong></div><div><small>Negative Proxy</small><strong><?= (int)($rating['negative_proxy']??0) ?></strong></div></div></div></div></div>
</div>

<div class="row g-4">
<div class="col-xl-7"><div class="card cef-card h-100"><div class="card-header">Recurring Civic Concern Alerts</div><div class="card-body d-grid gap-2"><?php if(!$alerts): ?><div class="text-muted small">No open analytics alert. Use Save Snapshot to record recurring categories with at least three submissions in the last 30 days.</div><?php endif; ?><?php foreach($alerts as $a): ?><div class="cef-risk"><div class="d-flex justify-content-between gap-2"><div><strong><?= e($a['severity'].' · '.$a['dimension_value']) ?></strong><small><?= e($a['details']?:'Recurring civic concern detected.') ?><br><?= formatDateTime($a['created_at']) ?></small></div><?php if(cefHasPermission('cepfms.analytics.manage')): ?><button class="btn btn-sm btn-outline-danger resolve-alert" data-id="<?= (int)$a['id'] ?>">Resolve</button><?php endif; ?></div></div><?php endforeach; ?></div></div></div>
<div class="col-xl-5"><div class="card cef-card h-100"><div class="card-header">Recent Citizen Records</div><div class="list-group list-group-flush"><?php foreach($recent as $r): ?><div class="list-group-item"><strong class="small"><?= e($r['reference_number'].' · '.$r['title']) ?></strong><div class="small text-muted"><?= e($r['submission_type'].' · '.($r['category_name']?:'Unclassified').' · '.$r['status']) ?> · <?= formatDateTime($r['created_at']) ?></div></div><?php endforeach; ?></div></div></div>
</div>

</main></div>
<script>
document.addEventListener('DOMContentLoaded',()=>{
 const monthly=<?= json_encode($monthly,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
 new Chart(document.getElementById('monthlyChart'),{
   type:'bar',
   data:{
     labels:monthly.map(x=>x.label),
     datasets:[
       {label:'Feedback',data:monthly.map(x=>Number(x.feedback)),backgroundColor:'#1d6fb8',borderColor:'#1d6fb8',borderWidth:1,borderRadius:4},
       {label:'Proposals',data:monthly.map(x=>Number(x.proposals)),backgroundColor:'#b8860b',borderColor:'#b8860b',borderWidth:1,borderRadius:4},
       {label:'Complaints',data:monthly.map(x=>Number(x.complaints)),backgroundColor:'#1a3a5c',borderColor:'#1a3a5c',borderWidth:1,borderRadius:4}
     ]
   },
   options:{responsive:true,scales:{y:{beginAtZero:true,ticks:{precision:0}}}}
 });
 const types=<?= json_encode($typeDist,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
 new Chart(document.getElementById('typeChart'),{
   type:'doughnut',
   data:{
     labels:types.map(x=>x.label),
     datasets:[{
       data:types.map(x=>Number(x.total)),
       backgroundColor:['#1d6fb8','#b8860b','#1a3a5c']
     }]
   },
   options:{responsive:true}
 });
 const cats=<?= json_encode($categories,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
 new Chart(document.getElementById('categoryChart'),{
   type:'bar',
   data:{
     labels:cats.map(x=>x.label),
     datasets:[{
       label:'Submissions',
       data:cats.map(x=>Number(x.total)),
       backgroundColor:'#b8860b',
       borderColor:'#b8860b',
       borderWidth:1,
       borderRadius:4
     }]
   },
   options:{indexAxis:'y',responsive:true,scales:{x:{beginAtZero:true,ticks:{precision:0}}}}
 });
 const locs=<?= json_encode($locations,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
 new Chart(document.getElementById('locationChart'),{
   type:'bar',
   data:{
     labels:locs.map(x=>x.label),
     datasets:[{
       label:'Submissions',
       data:locs.map(x=>Number(x.total)),
       backgroundColor:'#1d6fb8',
       borderColor:'#1d6fb8',
       borderWidth:1,
       borderRadius:4
     }]
   },
   options:{indexAxis:'y',responsive:true,scales:{x:{beginAtZero:true,ticks:{precision:0}}}}
 });

 document.getElementById('btnSnapshot')?.addEventListener('click',async()=>{const fd=new FormData();fd.append('csrf_token','<?= e(csrfToken()) ?>');const r=await fetch('ajax_snapshot.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:fd}).then(x=>x.json());if(r.success){await Swal.fire('Analytics',r.message+' '+r.alerts_created+' new alert(s).','success');location.reload();}else Swal.fire('Analytics',r.message,'error');});
 document.querySelectorAll('.resolve-alert').forEach(b=>b.onclick=async function(){const c=await Swal.fire({title:'Resolve this trend alert?',input:'textarea',inputLabel:'Resolution / management note',showCancelButton:true,inputValidator:v=>!v?'A resolution note is required':undefined});if(!c.isConfirmed)return;const fd=new FormData();fd.append('csrf_token','<?= e(csrfToken()) ?>');fd.append('alert_id',this.dataset.id);fd.append('resolution_notes',c.value);const r=await fetch('ajax_alert.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:fd}).then(x=>x.json());if(r.success)location.reload();else Swal.fire('Analytics Alert',r.message,'error');});
});
</script>
<?php include __DIR__.'/../../layouts/footer.php'; ?>
