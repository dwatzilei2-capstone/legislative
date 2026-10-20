<?php
declare(strict_types=1);
require_once __DIR__.'/../../includes/lacms_operational_helpers.php';
requireLacmsPermission('lacms.agendas.view');

$rows=db()->query(
 "SELECT a.agenda_reference,a.title,a.agenda_type,a.agenda_date,a.start_time,a.end_time,
         a.venue,a.status,c.name committee_name,o.name office_name,
         (SELECT COUNT(*) FROM lacms_agenda_items i WHERE i.agenda_id=a.id AND i.item_status<>'Removed') item_count,
         (SELECT COUNT(*) FROM lacms_calendar_events e WHERE e.agenda_id=a.id AND e.status<>'Cancelled') event_count
  FROM lacms_agendas a
  LEFT JOIN committees c ON c.id=a.committee_id
  LEFT JOIN offices o ON o.id=a.office_id
  ORDER BY a.agenda_date DESC,a.created_at DESC"
)->fetchAll();
$pageTitle='Agenda Report';$activeMenu='agendas';$extraCss=[appUrl('assets/css/lacms-operational.css')];
include __DIR__.'/../../layouts/header.php';
?>
<div class="lacms-app-wrapper"><?php include __DIR__.'/../../layouts/sidebar.php'; ?><main class="lacms-main-content"><div class="lo-head"><div><div class="lo-eyebrow">Operational Report</div><h1>Legislative Agenda Report</h1><p>Agenda schedule, committee responsibility, item count, linked calendar activities and current agenda status.</p></div><a class="btn btn-outline-secondary" href="index.php">Agenda Registry</a></div><div class="card lo-card"><div class="table-responsive"><table class="table lo-table mb-0"><thead><tr><th>Agenda</th><th>Type</th><th>Schedule</th><th>Committee / Office</th><th>Items</th><th>Events</th><th>Status</th></tr></thead><tbody><?php foreach($rows as $r): ?><tr><td><?= e($r['agenda_reference']) ?><div class="small text-muted"><?= e($r['title']) ?></div></td><td><?= e($r['agenda_type']) ?></td><td><?= $r['agenda_date']?formatDate($r['agenda_date']):'—' ?> <?= $r['start_time']?formatTime($r['start_time']):'' ?></td><td><?= e($r['committee_name']?:'—') ?><div class="small text-muted"><?= e($r['office_name']?:'') ?></div></td><td><?= (int)$r['item_count'] ?></td><td><?= (int)$r['event_count'] ?></td><td><?= e($r['status']) ?></td></tr><?php endforeach; ?></tbody></table></div></div></main></div>
<?php include __DIR__.'/../../layouts/footer.php'; ?>
