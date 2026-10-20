<?php
declare(strict_types=1);

require_once __DIR__.'/../includes/lacms_report_helpers.php';
requireLacmsPermission('lacms.reports.view');

$pdo=db();$agendaId=(int)($_GET['agenda_id']??0)?:null;
$rows=lacmsWorkflowRows($pdo,$agendaId);
$agendas=$pdo->query("SELECT id,agenda_reference,title FROM lacms_agendas ORDER BY created_at DESC")->fetchAll();

$pageTitle='Cross-Module Workflow Trace';$activeMenu='reports';
$extraCss=[appUrl('assets/css/lacms-operational.css')];
include __DIR__.'/../layouts/header.php';
?>
<div class="lacms-app-wrapper"><?php include __DIR__.'/../layouts/sidebar.php'; ?><main class="lacms-main-content">
<div class="lo-head"><div><div class="lo-eyebrow"><i class="bi bi-diagram-3"></i> Cross-Module Traceability</div><h1>Agenda → Calendar → Meeting → Deadline → Notification</h1><p>Trace the latest linked coordination records associated with each legislative agenda and identify scheduling conflicts, overdue obligations, completed deadlines and notification activity.</p></div><div class="d-flex gap-2"><a class="btn btn-outline-secondary" target="_blank" href="print.php?report=workflow<?= $agendaId?'&agenda_id='.$agendaId:'' ?>">Print</a><a class="btn btn-primary" href="export.php?report=workflow<?= $agendaId?'&agenda_id='.$agendaId:'' ?>">CSV</a></div></div>

<div class="card lo-card mb-3"><div class="card-body"><form class="row g-2 align-items-end"><div class="col-md-9"><label class="form-label small">Agenda</label><select class="form-select form-select-sm" name="agenda_id"><option value="">All agendas</option><?php foreach($agendas as $a): ?><option value="<?= (int)$a['id'] ?>" <?= $agendaId===(int)$a['id']?'selected':'' ?>><?= e($a['agenda_reference'].' · '.$a['title']) ?></option><?php endforeach; ?></select></div><div class="col-md-3"><button class="btn btn-outline-primary btn-sm w-100">Trace</button></div></form></div></div>

<div class="card lo-card"><div class="table-responsive"><table class="table lo-table mb-0"><thead><tr><th>Agenda</th><th>Latest Calendar</th><th>Latest Meeting</th><th>Deadlines</th><th>Notifications</th><th>Attention</th></tr></thead><tbody>
<?php if(!$rows): ?><tr><td colspan="6" class="text-center text-muted py-5">No workflow records found.</td></tr><?php endif; ?>
<?php foreach($rows as $r): ?><tr>
<td><a href="<?= e(appUrl('modules/agendas/view.php?id='.$r['agenda_id'])) ?>"><span class="lo-code"><?= e($r['agenda_reference']) ?></span></a><div><strong><?= e($r['agenda_title']) ?></strong></div><div class="small text-muted"><?= e($r['agenda_status']) ?></div></td>
<td><?php if($r['event_id']): ?><a href="<?= e(appUrl('modules/calendar/view.php?id='.$r['event_id'])) ?>"><?= e($r['event_reference']) ?></a><div class="small text-muted"><?= e($r['event_status'].' · '.$r['conflict_status']) ?><br><?= formatDateTime($r['start_datetime']) ?></div><?php else: ?>—<?php endif; ?></td>
<td><?php if($r['meeting_id']): ?><a href="<?= e(appUrl('modules/meetings/view.php?id='.$r['meeting_id'])) ?>"><?= e($r['meeting_reference']) ?></a><div class="small text-muted"><?= e($r['meeting_status']) ?></div><?php else: ?>—<?php endif; ?></td>
<td><?= (int)$r['completed_deadline_count'] ?>/<?= (int)$r['deadline_count'] ?> completed<div class="small <?= (int)$r['overdue_count']?'text-danger fw-bold':'text-muted' ?>"><?= (int)$r['overdue_count'] ?> overdue</div></td>
<td><?= (int)$r['notification_count'] ?> record(s)</td>
<td><?php if($r['conflict_status']==='Detected'||(int)$r['overdue_count']>0): ?><span class="lo-status bad">Attention</span><?php else: ?><span class="lo-status good">Clear</span><?php endif; ?></td>
</tr><?php endforeach; ?>
</tbody></table></div></div>
</main></div>
<?php include __DIR__.'/../layouts/footer.php'; ?>
