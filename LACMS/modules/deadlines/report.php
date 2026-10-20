<?php
declare(strict_types=1);
require_once __DIR__.'/../../includes/lacms_deadline_helpers.php';
requireLacmsPermission('lacms.deadlines.view');
$pdo=db();lacmsRefreshDeadlineStatuses($pdo);
$rows=$pdo->query(
 "SELECT d.deadline_reference,d.title,d.deadline_type,d.due_datetime,d.priority_level,d.status,
         u.full_name responsible_name,o.name office_name,c.name committee_name,
         (SELECT COUNT(*) FROM lacms_deadline_reminders r WHERE r.deadline_id=d.id AND r.status='Scheduled') reminders,
         (SELECT COUNT(*) FROM lacms_deadline_escalations x WHERE x.deadline_id=d.id AND x.status='Open') escalations
  FROM lacms_deadlines d
  LEFT JOIN users u ON u.id=d.responsible_user_id
  LEFT JOIN offices o ON o.id=d.office_id
  LEFT JOIN committees c ON c.id=d.committee_id
  ORDER BY d.due_datetime DESC"
)->fetchAll();
$pageTitle='Deadline Compliance Report';$activeMenu='deadlines';$extraCss=[appUrl('assets/css/lacms-operational.css')];
include __DIR__.'/../../layouts/header.php';
?>
<div class="lacms-app-wrapper"><?php include __DIR__.'/../../layouts/sidebar.php'; ?><main class="lacms-main-content"><div class="lo-head"><div><div class="lo-eyebrow">Operational Report</div><h1>Deadline Compliance Report</h1><p>Due dates, assignment responsibility, priority, reminder coverage, escalation state and completion/overdue status.</p></div><div class="d-flex gap-2"><a class="btn btn-outline-secondary" href="index.php">Deadline Registry</a><a class="btn btn-primary" href="<?= e(appUrl('reports/export.php?report=deadlines')) ?>">CSV</a></div></div><div class="card lo-card"><div class="table-responsive"><table class="table lo-table mb-0"><thead><tr><th>Deadline</th><th>Type</th><th>Due</th><th>Responsible</th><th>Office / Committee</th><th>Priority</th><th>Reminders</th><th>Escalations</th><th>Status</th></tr></thead><tbody><?php foreach($rows as $r): ?><tr><td><?= e($r['deadline_reference']) ?><div class="small text-muted"><?= e($r['title']) ?></div></td><td><?= e($r['deadline_type']) ?></td><td><?= formatDateTime($r['due_datetime']) ?></td><td><?= e($r['responsible_name']?:'—') ?></td><td><?= e($r['office_name']?:'—') ?><div class="small text-muted"><?= e($r['committee_name']?:'') ?></div></td><td><?= e($r['priority_level']) ?></td><td><?= (int)$r['reminders'] ?></td><td><?= (int)$r['escalations'] ?></td><td><?= e($r['status']) ?></td></tr><?php endforeach; ?></tbody></table></div></div></main></div>
<?php include __DIR__.'/../../layouts/footer.php'; ?>
