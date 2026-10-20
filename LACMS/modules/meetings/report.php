<?php
declare(strict_types=1);
require_once __DIR__.'/../../includes/lacms_operational_helpers.php';
requireLacmsPermission('lacms.meetings.view');

$rows=db()->query(
 "SELECT m.meeting_reference,m.title,m.meeting_type,m.start_datetime,m.end_datetime,
         m.venue,m.status,a.agenda_reference,e.event_reference,e.conflict_status,
         c.name committee_name,cu.full_name chair_name,su.full_name secretary_name,
         (SELECT COUNT(*) FROM lacms_meeting_participants p WHERE p.meeting_id=m.id) participants,
         (SELECT COUNT(*) FROM lacms_meeting_participants p WHERE p.meeting_id=m.id AND p.attendance_required=1) required_participants
  FROM lacms_meetings m
  LEFT JOIN lacms_agendas a ON a.id=m.agenda_id
  LEFT JOIN lacms_calendar_events e ON e.id=m.calendar_event_id
  LEFT JOIN committees c ON c.id=m.committee_id
  LEFT JOIN users cu ON cu.id=m.chair_user_id
  LEFT JOIN users su ON su.id=m.secretary_user_id
  ORDER BY m.start_datetime DESC"
)->fetchAll();

$pageTitle='Meeting Coordination Report';$activeMenu='meetings';$extraCss=[appUrl('assets/css/lacms-operational.css')];
include __DIR__.'/../../layouts/header.php';
?>
<div class="lacms-app-wrapper"><?php include __DIR__.'/../../layouts/sidebar.php'; ?><main class="lacms-main-content"><div class="lo-head"><div><div class="lo-eyebrow">Operational Report</div><h1>Meeting Coordination Report</h1><p>Meeting schedules, linked agendas/calendar events, committee leadership, participant counts, conflict status and current coordination state.</p></div><a class="btn btn-outline-secondary" href="index.php">Meeting Registry</a></div><div class="card lo-card"><div class="table-responsive"><table class="table lo-table mb-0"><thead><tr><th>Meeting</th><th>Type</th><th>Schedule</th><th>Agenda / Calendar</th><th>Committee</th><th>Chair / Secretary</th><th>Participants</th><th>Conflict</th><th>Status</th></tr></thead><tbody><?php foreach($rows as $r): ?><tr><td><?= e($r['meeting_reference']) ?><div class="small text-muted"><?= e($r['title']) ?></div></td><td><?= e($r['meeting_type']) ?></td><td><?= formatDateTime($r['start_datetime']) ?></td><td><?= e($r['agenda_reference']?:'—') ?><div class="small text-muted"><?= e($r['event_reference']?:'—') ?></div></td><td><?= e($r['committee_name']?:'—') ?></td><td><?= e($r['chair_name']?:'—') ?><div class="small text-muted"><?= e($r['secretary_name']?:'—') ?></div></td><td><?= (int)$r['participants'] ?> / <?= (int)$r['required_participants'] ?> req.</td><td><?= e($r['conflict_status']?:'Unchecked') ?></td><td><?= e($r['status']) ?></td></tr><?php endforeach; ?></tbody></table></div></div></main></div>
<?php include __DIR__.'/../../layouts/footer.php'; ?>
