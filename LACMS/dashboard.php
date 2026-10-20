<?php
declare(strict_types=1);

require_once __DIR__.'/includes/auth.php';
require_once __DIR__.'/includes/lacms_report_helpers.php';
requireLacmsPermission('lacms.dashboard.view');

$pdo=db();$pageTitle='Dashboard';$activeMenu='dashboard';
$extraCss=[appUrl('assets/css/lacms-dashboard-v2.css?v='.time()),appUrl('assets/css/lacms-operational.css?v='.time())];

$stats=lacmsDashboardStats($pdo);

$today=$pdo->query(
 "SELECT id,event_reference,title,event_type,start_datetime,end_datetime,venue,status,conflict_status
  FROM lacms_calendar_events
  WHERE DATE(start_datetime)=CURDATE()
    AND status<>'Cancelled'
  ORDER BY start_datetime"
)->fetchAll();

$deadlineWatch=$pdo->query(
 "SELECT id,deadline_reference,title,due_datetime,priority_level,status
  FROM lacms_deadlines
  WHERE status IN ('Overdue','Pending','In Progress')
  ORDER BY FIELD(status,'Overdue','In Progress','Pending'),due_datetime
  LIMIT 8"
)->fetchAll();

$meetingWatch=$pdo->query(
 "SELECT id,meeting_reference,title,start_datetime,venue,status
  FROM lacms_meetings
  WHERE status IN ('Planned','Confirmed','In Progress','Postponed')
  ORDER BY start_datetime LIMIT 8"
)->fetchAll();

$activity=lacmsMonthlyActivity($pdo);
$deadlineDist=lacmsDeadlineStatusDistribution($pdo);

$recent=[];
if(lacmsSystemId()){
    $q=$pdo->prepare(
      "SELECT al.action,al.details,al.created_at,u.full_name
       FROM activity_logs al
       LEFT JOIN users u ON u.id=al.user_id
       WHERE al.system_id=:system
       ORDER BY al.created_at DESC,al.id DESC LIMIT 50"
    );
    $q->execute([':system'=>lacmsSystemId()]);$recent=$q->fetchAll();
}

include __DIR__.'/layouts/header.php';
?>
<div class="lacms-app-wrapper"><?php include __DIR__.'/layouts/sidebar.php'; ?><main class="lacms-main-content">

<section class="lacms-dashboard-hero" style="background: transparent !important; background-color: transparent !important; border: none !important; box-shadow: none !important; padding: 0.5rem 0 1.5rem !important;">
<div><div class="lacms-dashboard-eyebrow"><i class="bi bi-building"></i> Local Government Unit of Manila</div><h1>Legislative Agenda and Calendar Management System</h1><p>Operational monitoring for agendas, calendar schedules, meetings, deadlines, reminders, notifications and legislative coordination.</p><div class="lacms-dashboard-hero-actions"><a href="<?= e(appUrl('modules/agendas/index.php')) ?>" class="btn btn-warning"><i class="bi bi-list-check"></i> Agendas</a><a href="<?= e(appUrl('modules/calendar/index.php')) ?>" class="btn btn-outline-secondary"><i class="bi bi-calendar3"></i> Master Calendar</a><a href="<?= e(appUrl('reports/index.php')) ?>" class="btn btn-outline-secondary"><i class="bi bi-bar-chart"></i> Reports</a></div></div>
<div class="lacms-intelligence-overview"><span><i class="bi bi-stars"></i></span><div><small>Coordination Intelligence</small><strong><?= $stats['overdue'] ?> overdue · <?= $stats['conflicts'] ?> conflict(s)</strong><p><?= $stats['notifications'] ?> reminder/notification record(s) require delivery/follow-up; <?= $stats['sync_attention'] ?> synchronization record(s) need attention.</p></div></div>
</section>

<div class="row g-3 mb-4"><?php foreach([
 [$stats['agendas'],'Active Agendas','bi-list-check'],
 [$stats['upcoming_events'],'Upcoming Events','bi-calendar-event'],
 [$stats['meetings'],'Open Meetings','bi-people'],
 [$stats['deadlines'],'Open Deadlines','bi-alarm'],
 [$stats['overdue'],'Overdue','bi-exclamation-triangle'],
 [$stats['notifications'],'Notification Queue','bi-envelope-paper'],
 [$stats['sync_open'],'Open Sync Records','bi-arrow-left-right'],
] as [$v,$l,$i]): ?><div class="col-6 col-xl-2"><div class="lo-stat h-100"><i class="bi <?= e($i) ?>"></i><div><strong><?= (int)$v ?></strong><small><?= e($l) ?></small></div></div></div><?php endforeach; ?></div>

<div class="row g-4 mb-4">
<div class="col-xl-7"><div class="card lo-card h-100"><div class="card-header">Six-Month Legislative Coordination Activity</div><div class="card-body"><canvas id="activityChart" height="120"></canvas></div></div></div>
<div class="col-xl-5"><div class="card lo-card h-100"><div class="card-header">Deadline Status Distribution</div><div class="card-body"><canvas id="deadlineChart" height="160"></canvas></div></div></div>
</div>

<div class="row g-4 mb-4">
<div class="col-xl-6"><div class="card lo-card h-100"><div class="card-header d-flex justify-content-between"><span>Today's Legislative Calendar</span><a class="btn btn-sm btn-primary" href="<?= e(appUrl('modules/calendar/index.php')) ?>">Calendar</a></div><div class="list-group list-group-flush"><?php if(!$today): ?><div class="list-group-item text-muted">No scheduled activity today.</div><?php endif; ?><?php foreach($today as $e): ?><a class="list-group-item list-group-item-action" href="<?= e(appUrl('modules/calendar/view.php?id='.$e['id'])) ?>"><div class="d-flex justify-content-between"><div><strong><?= e($e['title']) ?></strong><div class="small text-muted"><?= e($e['event_reference'].' · '.$e['event_type'].' · '.($e['venue']?:'TBA')) ?></div></div><div class="text-end small"><?= formatTime($e['start_datetime']) ?><br><?= e($e['status']) ?></div></div></a><?php endforeach; ?></div></div></div>
<div class="col-xl-6"><div class="card lo-card h-100"><div class="card-header d-flex justify-content-between"><span>Deadline Watch</span><a class="btn btn-sm btn-primary" href="<?= e(appUrl('modules/deadlines/index.php')) ?>">Deadlines</a></div><div class="list-group list-group-flush"><?php if(!$deadlineWatch): ?><div class="list-group-item text-muted">No active deadline watch items.</div><?php endif; ?><?php foreach($deadlineWatch as $d): ?><a class="list-group-item list-group-item-action" href="<?= e(appUrl('modules/deadlines/view.php?id='.$d['id'])) ?>"><div class="d-flex justify-content-between"><div><strong><?= e($d['title']) ?></strong><div class="small text-muted"><?= e($d['deadline_reference'].' · '.$d['priority_level']) ?></div></div><div class="text-end small"><?= formatDateTime($d['due_datetime']) ?><br><span class="<?= $d['status']==='Overdue'?'text-danger fw-bold':'' ?>"><?= e($d['status']) ?></span></div></div></a><?php endforeach; ?></div></div></div>
</div>

<div class="row g-4">
<div class="col-xl-6"><div class="card lo-card h-100"><div class="card-header">Meeting Coordination Queue</div><div class="list-group list-group-flush"><?php if(!$meetingWatch): ?><div class="list-group-item text-muted">No open meetings.</div><?php endif; ?><?php foreach($meetingWatch as $m): ?><a class="list-group-item list-group-item-action" href="<?= e(appUrl('modules/meetings/view.php?id='.$m['id'])) ?>"><strong><?= e($m['meeting_reference'].' · '.$m['title']) ?></strong><div class="small text-muted"><?= formatDateTime($m['start_datetime']) ?> · <?= e($m['venue']?:'TBA') ?> · <?= e($m['status']) ?></div></a><?php endforeach; ?></div></div></div>
<div class="col-xl-6"><div class="card lo-card h-100" id="lacmsRecentActivityCard">
    <div class="card-header">Recent LACMS Activity</div>
    <div class="list-group list-group-flush" id="lacmsActivityList">
        <?php if(!$recent): ?>
            <div class="list-group-item text-muted">No recent LACMS activity.</div>
        <?php endif; ?>
        <?php foreach($recent as $idx => $x): ?>
            <div class="list-group-item <?= $idx >= 5 ? 'lacms-activity-extra d-none' : '' ?>">
                <strong class="small"><?= e($x['action']) ?></strong>
                <div class="small text-muted"><?= e($x['full_name']?:'System') ?> · <?= formatDateTime($x['created_at']) ?></div>
                <?php if($x['details']): ?>
                    <div class="small"><?= e(mb_strimwidth($x['details'],0,150,'…')) ?></div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <?php if(count($recent) > 5): ?>
        <div class="card-footer bg-transparent border-top-0 pt-2 pb-3 px-3">
            <button type="button" class="btn btn-sm btn-light border w-100 py-1 fw-semibold text-secondary d-flex align-items-center justify-content-center gap-2" id="btnToggleLacmsActivityBottom" onclick="toggleLacmsActivity()" style="font-size: 0.8rem; border-radius: 8px;">
                <i class="bi bi-chevron-down" id="iconToggleLacmsActivityBottom"></i>
                <span id="textToggleLacmsActivityBottom">See All (<?= count($recent) ?>)</span>
            </button>
        </div>
    <?php endif; ?>
</div></div>
</div>

</main></div>

<script>
document.addEventListener('DOMContentLoaded',function(){
 const activity=<?= json_encode($activity,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
 new Chart(document.getElementById('activityChart'),{
  type:'bar',
  data:{
   labels:activity.map(x=>x.label),
   datasets:[
    {
     label:'Calendar Events',
     data:activity.map(x=>Number(x.events)),
     backgroundColor:'#0f2137',
     borderColor:'#071426',
     borderWidth:1,
     borderRadius:4
    },
    {
     label:'Meetings',
     data:activity.map(x=>Number(x.meetings)),
     backgroundColor:'#b8860b',
     borderColor:'#946c07',
     borderWidth:1,
     borderRadius:4
    },
    {
     label:'Deadlines',
     data:activity.map(x=>Number(x.deadlines)),
     backgroundColor:'#2563eb',
     borderColor:'#1d4ed8',
     borderWidth:1,
     borderRadius:4
    }
   ]
  },
  options:{
   responsive:true,
   scales:{
    y:{
     beginAtZero:true,
     ticks:{precision:0}
    }
   }
  }
 });

 const dd=<?= json_encode($deadlineDist,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
 const goldBluePalette=[
  '#0f2137',
  '#b8860b',
  '#2563eb',
  '#d97706',
  '#3b82f6',
  '#eab308',
  '#1d4ed8',
  '#f59e0b'
 ];
 const bgColors=dd.map((x,i)=>goldBluePalette[i%goldBluePalette.length]);

 new Chart(document.getElementById('deadlineChart'),{
  type:'doughnut',
  data:{
   labels:dd.map(x=>x.label),
   datasets:[{
    data:dd.map(x=>Number(x.total)),
    backgroundColor:bgColors,
    borderColor:'#ffffff',
    borderWidth:2
   }]
  },
  options:{
   responsive:true,
   plugins:{
    legend:{
     position:'bottom'
    }
   }
  }
 });
});
</script>
<style>
.lacms-activity-extra:not(.d-none) {
    animation: fadeInLacmsActivity 0.25s ease-in-out;
}
@keyframes fadeInLacmsActivity {
    from { opacity: 0; transform: translateY(-3px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>
<script>
function toggleLacmsActivity() {
  const extras = document.querySelectorAll('.lacms-activity-extra');
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
  const textBottom = document.getElementById('textToggleLacmsActivityBottom');
  const iconBottom = document.getElementById('iconToggleLacmsActivityBottom');

  if (isHidden) {
    if (textBottom) textBottom.textContent = 'Hide';
    if (iconBottom) iconBottom.className = 'bi bi-chevron-up';
  } else {
    if (textBottom) textBottom.textContent = 'See All (' + totalCount + ')';
    if (iconBottom) iconBottom.className = 'bi bi-chevron-down';

    const card = document.getElementById('lacmsRecentActivityCard');
    if (card) {
      card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
  }
}
</script>
<?php include __DIR__.'/layouts/footer.php'; ?>
