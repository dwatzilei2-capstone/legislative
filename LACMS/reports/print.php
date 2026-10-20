<?php
declare(strict_types=1);

require_once __DIR__.'/../includes/lacms_report_helpers.php';
requireLacmsPermission('lacms.reports.view');

$pdo=db();$report=clean($_GET['report']??'summary');
$stats=lacmsDashboardStats($pdo);
$rows=$report==='workflow'
    ? lacmsWorkflowRows($pdo,(int)($_GET['agenda_id']??0)?:null)
    : [];
?>
<!doctype html><html><head><meta charset="utf-8"><title>LACMS <?= e(ucfirst($report)) ?> Report</title><style>body{font:11px Arial;margin:25px;color:#111827}.head{text-align:center;border-bottom:3px solid #0f2137;padding-bottom:12px;margin-bottom:15px}.cards{display:grid;grid-template-columns:repeat(5,1fr);gap:8px;margin-bottom:15px}.card{border:1px solid #ccc;border-top:4px solid #d4a90b;padding:8px}.card strong{display:block;font-size:18px}table{width:100%;border-collapse:collapse}th,td{border:1px solid #ccc;padding:5px;vertical-align:top}th{background:#eee}</style></head><body onload="window.print()"><div class="head"><strong><?= e(APP_NAME) ?></strong><h2><?= $report==='workflow'?'Cross-Module Workflow Trace':'Operational Summary' ?></h2><div>Generated <?= date('F j, Y g:i A') ?></div></div>
<?php if($report==='workflow'): ?><table><thead><tr><th>Agenda</th><th>Calendar</th><th>Meeting</th><th>Deadlines</th><th>Notifications</th><th>Attention</th></tr></thead><tbody><?php foreach($rows as $r): ?><tr><td><?= e($r['agenda_reference'].' · '.$r['agenda_status']) ?></td><td><?= e(($r['event_reference']?:'—').' · '.($r['event_status']?:'').' · '.($r['conflict_status']?:'')) ?></td><td><?= e(($r['meeting_reference']?:'—').' · '.($r['meeting_status']?:'')) ?></td><td><?= (int)$r['completed_deadline_count'] ?>/<?= (int)$r['deadline_count'] ?> completed; <?= (int)$r['overdue_count'] ?> overdue</td><td><?= (int)$r['notification_count'] ?></td><td><?= ($r['conflict_status']==='Detected'||(int)$r['overdue_count']>0)?'Attention':'Clear' ?></td></tr><?php endforeach; ?></tbody></table>
<?php else: ?><div class="cards"><div class="card"><strong><?= $stats['agendas'] ?></strong>Active Agendas</div><div class="card"><strong><?= $stats['upcoming_events'] ?></strong>Upcoming Events</div><div class="card"><strong><?= $stats['meetings'] ?></strong>Open Meetings</div><div class="card"><strong><?= $stats['overdue'] ?></strong>Overdue</div><div class="card"><strong><?= $stats['notifications'] ?></strong>Notification Queue</div></div>
<table><thead><tr><th>Metric</th><th>Count</th></tr></thead><tbody><?php foreach($stats as $k=>$v): ?><tr><td><?= e(ucwords(str_replace('_',' ',$k))) ?></td><td><?= (int)$v ?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?>
</body></html>
