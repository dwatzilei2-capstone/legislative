<?php
declare(strict_types=1);
require_once __DIR__.'/../../includes/lacms_operational_helpers.php';
requireLacmsPermission('lacms.calendar.view');

$rows=db()->query(
 "SELECT e.event_reference,e.title,e.event_type,e.start_datetime,e.end_datetime,e.venue,
         e.conflict_status,e.status,a.agenda_reference,c.name committee_name,
         (SELECT COUNT(*) FROM lacms_calendar_event_participants p WHERE p.calendar_event_id=e.id) participants,
         (SELECT COUNT(*) FROM lacms_calendar_conflicts x WHERE x.calendar_event_id=e.id AND x.status='Open') conflicts
  FROM lacms_calendar_events e
  LEFT JOIN lacms_agendas a ON a.id=e.agenda_id
  LEFT JOIN committees c ON c.id=e.committee_id
  ORDER BY e.start_datetime DESC"
)->fetchAll();
$pageTitle='Calendar Report';$activeMenu='calendar';$extraCss=[appUrl('assets/css/lacms-operational.css')];
include __DIR__.'/../../layouts/header.php';
?>
<div class="lacms-app-wrapper"><?php include __DIR__.'/../../layouts/sidebar.php'; ?><main class="lacms-main-content"><div class="lo-head"><div><div class="lo-eyebrow">Operational Report</div><h1>Calendar Scheduling Report</h1><p>Legislative activity schedule, agenda/committee linkage, participant counts and schedule-conflict status.</p></div><a class="btn btn-outline-secondary" href="index.php">Master Calendar</a></div><div class="card lo-card"><div class="table-responsive"><table class="table lo-table mb-0"><thead><tr><th>Event</th><th>Type</th><th>Schedule</th><th>Venue</th><th>Agenda / Committee</th><th>Participants</th><th>Conflicts</th><th>Status</th></tr></thead><tbody><?php foreach($rows as $r): ?><tr><td><?= e($r['event_reference']) ?><div class="small text-muted"><?= e($r['title']) ?></div></td><td><?= e($r['event_type']) ?></td><td><?= formatDateTime($r['start_datetime']) ?><?= $r['end_datetime']?'<br>'.formatDateTime($r['end_datetime']):'' ?></td><td><?= e($r['venue']?:'—') ?></td><td><?= e($r['agenda_reference']?:'—') ?><div class="small text-muted"><?= e($r['committee_name']?:'') ?></div></td><td><?= (int)$r['participants'] ?></td><td><?= (int)$r['conflicts'] ?> / <?= e($r['conflict_status']) ?></td><td><?= e($r['status']) ?></td></tr><?php endforeach; ?></tbody></table></div></div></main></div>
<?php include __DIR__.'/../../layouts/footer.php'; ?>
