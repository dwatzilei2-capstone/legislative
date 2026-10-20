<?php
declare(strict_types=1);

require_once __DIR__.'/../includes/lacms_report_helpers.php';
requireLacmsPermission('lacms.reports.view');

$pdo=db();$pageTitle='Dashboard & Reports';$activeMenu='reports';
$extraCss=[appUrl('assets/css/lacms-operational.css')];
$stats=lacmsDashboardStats($pdo);

$agendaStatus=$pdo->query("SELECT status label,COUNT(*) total FROM lacms_agendas GROUP BY status ORDER BY total DESC")->fetchAll();
$meetingStatus=$pdo->query("SELECT status label,COUNT(*) total FROM lacms_meetings GROUP BY status ORDER BY total DESC")->fetchAll();

include __DIR__.'/../layouts/header.php';
?>
<div class="lacms-app-wrapper"><?php include __DIR__.'/../layouts/sidebar.php'; ?><main class="lacms-main-content">
<div class="lo-head"><div><div class="lo-eyebrow"><i class="bi bi-bar-chart-line"></i> Step 7 · Operational Reporting</div><h1>Dashboard & Reports Center</h1><p>Consolidated monitoring for agendas, calendar events, meetings, deadline compliance, notification delivery and end-to-end LACMS workflow traceability.</p></div><div class="d-flex gap-2"><a class="btn btn-outline-secondary" target="_blank" href="print.php?report=summary"><i class="bi bi-printer"></i> Print</a><a class="btn btn-primary" href="export.php?report=workflow"><i class="bi bi-filetype-csv"></i> Workflow CSV</a></div></div>

<div class="row g-3 mb-4"><?php foreach([
 [$stats['agendas'],'Active Agendas','bi-list-check'],[$stats['upcoming_events'],'Upcoming Events','bi-calendar-week'],
 [$stats['meetings'],'Open Meetings','bi-people'],[$stats['due_soon'],'Due Soon','bi-clock'],
 [$stats['overdue'],'Overdue','bi-exclamation-triangle'],[$stats['notifications'],'Notification Queue','bi-envelope']
] as [$v,$l,$i]): ?><div class="col-6 col-xl-2"><div class="lo-stat"><i class="bi <?= e($i) ?>"></i><div><strong><?= (int)$v ?></strong><small><?= e($l) ?></small></div></div></div><?php endforeach; ?></div>

<div class="row g-4 mb-4">
<div class="col-xl-8"><div class="card lo-card h-100"><div class="card-header">Operational Reports</div><div class="card-body"><div class="row g-3">
<?php foreach([
 ['Legislative Agenda Report','modules/agendas/report.php','bi-list-check','Agenda schedules, committees, item counts and status.'],
 ['Calendar Activity Report','modules/calendar/report.php','bi-calendar-week','Events, participants, conflicts and schedule state.'],
 ['Meeting Coordination Report','modules/meetings/report.php','bi-people','Meeting leadership, participants and coordination status.'],
 ['Deadline Compliance Report','modules/deadlines/report.php','bi-alarm','Due dates, reminders, escalation and compliance.'],
 ['Executive-Legislative Synchronization','modules/synchronization/report.php','bi-arrow-left-right','Executive and legislative positions, action items and alignment state.'],
 ['Cross-Module Workflow Trace','reports/workflow.php','bi-diagram-3','Agenda → Calendar → Meeting → Deadline → Notification trace.'],
 ['Notification Queue','pages/meeting_notifications.php','bi-envelope-paper','Internal delivery and external-email queue state.'],
] as [$title,$url,$icon,$desc]): ?><div class="col-md-6"><a class="text-decoration-none" href="<?= e(appUrl($url)) ?>"><div class="lo-participant h-100"><div><strong><i class="bi <?= e($icon) ?>"></i> <?= e($title) ?></strong><small><?= e($desc) ?></small></div><i class="bi bi-arrow-right"></i></div></a></div><?php endforeach; ?>
</div></div></div></div>
<div class="col-xl-4"><div class="card lo-card h-100"><div class="card-header">Exports</div><div class="card-body d-grid gap-2"><a class="btn btn-outline-primary" href="export.php?report=agendas">Agenda CSV</a><a class="btn btn-outline-primary" href="export.php?report=calendar">Calendar CSV</a><a class="btn btn-outline-primary" href="export.php?report=meetings">Meeting CSV</a><a class="btn btn-outline-primary" href="export.php?report=deadlines">Deadline CSV</a><a class="btn btn-outline-primary" href="export.php?report=notifications">Notification CSV</a><a class="btn btn-primary" href="export.php?report=workflow">Cross-Module Workflow CSV</a></div></div></div>
</div>

<div class="row g-4">
<div class="col-xl-6"><div class="card lo-card"><div class="card-header">Agenda Status</div><div class="table-responsive"><table class="table lo-table mb-0"><thead><tr><th>Status</th><th>Total</th></tr></thead><tbody><?php foreach($agendaStatus as $x): ?><tr><td><?= e($x['label']) ?></td><td><?= (int)$x['total'] ?></td></tr><?php endforeach; ?></tbody></table></div></div></div>
<div class="col-xl-6"><div class="card lo-card"><div class="card-header">Meeting Status</div><div class="table-responsive"><table class="table lo-table mb-0"><thead><tr><th>Status</th><th>Total</th></tr></thead><tbody><?php foreach($meetingStatus as $x): ?><tr><td><?= e($x['label']) ?></td><td><?= (int)$x['total'] ?></td></tr><?php endforeach; ?></tbody></table></div></div></div>
</div>
</main></div>
<?php include __DIR__.'/../layouts/footer.php'; ?>
