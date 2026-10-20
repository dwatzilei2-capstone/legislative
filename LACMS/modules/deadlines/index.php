<?php
declare(strict_types=1);

require_once __DIR__.'/../../includes/lacms_deadline_helpers.php';
requireLacmsPermission('lacms.deadlines.view');

$pdo=db();lacmsRefreshDeadlineStatuses($pdo);
$pageTitle='Deadline Tracking';$activeMenu='deadlines';
$extraCss=[appUrl('assets/css/lacms-operational.css')];

$search=clean($_GET['search']??'');$status=clean($_GET['status']??'');
$priority=clean($_GET['priority']??'');$owner=(int)($_GET['responsible_user_id']??0);
$where=[];$params=[];
if($search!==''){$where[]='(d.deadline_reference LIKE :s1 OR d.title LIKE :s2 OR d.description LIKE :s3)';$like='%'.$search.'%';$params[':s1']=$like;$params[':s2']=$like;$params[':s3']=$like;}
if($status!==''){$where[]='d.status=:status';$params[':status']=$status;}
if($priority!==''){$where[]='d.priority_level=:priority';$params[':priority']=$priority;}
if($owner>0){$where[]='d.responsible_user_id=:owner';$params[':owner']=$owner;}
$sqlWhere=$where?'WHERE '.implode(' AND ',$where):'';

$stmt=$pdo->prepare(
 "SELECT d.*,u.full_name responsible_name,o.name office_name,c.name committee_name,
         li.reference_number legislative_reference,a.agenda_reference,
         e.event_reference,m.meeting_reference,
         (SELECT COUNT(*) FROM lacms_deadline_assignments x WHERE x.deadline_id=d.id) assignment_count,
         (SELECT COUNT(*) FROM lacms_deadline_reminders r WHERE r.deadline_id=d.id AND r.status='Scheduled') reminder_count,
         (SELECT COUNT(*) FROM lacms_deadline_escalations x WHERE x.deadline_id=d.id AND x.status='Open') escalation_count
  FROM lacms_deadlines d
  LEFT JOIN users u ON u.id=d.responsible_user_id
  LEFT JOIN offices o ON o.id=d.office_id
  LEFT JOIN committees c ON c.id=d.committee_id
  LEFT JOIN legislative_items li ON li.id=d.legislative_item_id
  LEFT JOIN lacms_agendas a ON a.id=d.agenda_id
  LEFT JOIN lacms_calendar_events e ON e.id=d.calendar_event_id
  LEFT JOIN lacms_meetings m ON m.id=d.meeting_id
  {$sqlWhere}
  ORDER BY FIELD(d.status,'Overdue','In Progress','Pending','Completed','Cancelled'),
           d.due_datetime,d.id"
);
$stmt->execute($params);$rows=$stmt->fetchAll();

$users=$pdo->query("SELECT id,full_name FROM users WHERE status='Active' AND deleted_at IS NULL ORDER BY full_name")->fetchAll();
$offices=$pdo->query("SELECT id,name FROM offices WHERE status='Active' ORDER BY name")->fetchAll();
$committees=$pdo->query("SELECT id,name FROM committees WHERE status='Active' ORDER BY name")->fetchAll();
$agendas=$pdo->query("SELECT id,agenda_reference,title FROM lacms_agendas ORDER BY created_at DESC LIMIT 250")->fetchAll();
$events=$pdo->query("SELECT id,event_reference,title,start_datetime FROM lacms_calendar_events ORDER BY start_datetime DESC LIMIT 250")->fetchAll();
$meetings=$pdo->query("SELECT id,meeting_reference,title,start_datetime FROM lacms_meetings ORDER BY start_datetime DESC LIMIT 250")->fetchAll();
$legislative=$pdo->query("SELECT id,reference_number,title FROM legislative_items WHERE deleted_at IS NULL ORDER BY updated_at DESC LIMIT 300")->fetchAll();

$stats=$pdo->query(
 "SELECT COUNT(*) total,SUM(status='Pending') pending,SUM(status='In Progress') in_progress,
         SUM(status='Overdue') overdue,SUM(status='Completed') completed,
         SUM(status NOT IN ('Completed','Cancelled') AND due_datetime BETWEEN NOW() AND DATE_ADD(NOW(),INTERVAL 7 DAY)) due_soon
  FROM lacms_deadlines"
)->fetch()?:[];

include __DIR__.'/../../layouts/header.php';
?>
<div class="lacms-app-wrapper"><?php include __DIR__.'/../../layouts/sidebar.php'; ?><main class="lacms-main-content">
<div class="lo-head"><div><div class="lo-eyebrow"><i class="bi bi-alarm"></i> Step 5 · Operational Backend</div><h1>Deadline Tracking</h1><p>Register legislative deadlines, assign responsibility, schedule reminders, detect overdue work, escalate urgent items, preserve evidence and close completed obligations.</p></div><?php if(lacmsOperationalManager()): ?><button class="btn btn-primary" id="btnNewDeadline"><i class="bi bi-plus-circle"></i> New Deadline</button><?php endif; ?></div>

<div class="lo-flow"><div><strong>1. Register</strong><small>Due date and context</small></div><div><strong>2. Assign</strong><small>User / office / committee</small></div><div><strong>3. Remind</strong><small>Automatic schedules</small></div><div><strong>4. Monitor</strong><small>Due soon / overdue</small></div><div><strong>5. Escalate</strong><small>Urgent attention</small></div><div><strong>6. Complete</strong><small>Close with evidence</small></div></div>

<div class="row g-3 mb-3"><?php foreach([
 ['Deadlines',$stats['total']??0,'bi-list-task'],['Pending',$stats['pending']??0,'bi-hourglass'],
 ['In Progress',$stats['in_progress']??0,'bi-arrow-repeat'],['Due Soon',$stats['due_soon']??0,'bi-clock'],
 ['Overdue',$stats['overdue']??0,'bi-exclamation-triangle'],['Completed',$stats['completed']??0,'bi-check2-circle']
] as [$l,$v,$i]): ?><div class="col-6 col-xl-2"><div class="lo-stat"><i class="bi <?= e($i) ?>"></i><div><strong><?= (int)$v ?></strong><small><?= e($l) ?></small></div></div></div><?php endforeach; ?></div>

<div class="card lo-card mb-3"><div class="card-body"><form class="row g-2 align-items-end">
<div class="col-xl-4"><label class="form-label small">Search</label><input class="form-control form-control-sm" name="search" value="<?= e($search) ?>" placeholder="Reference, title or description"></div>
<div class="col-xl-2"><label class="form-label small">Status</label><select class="form-select form-select-sm" name="status"><option value="">All</option><?php foreach(lacmsDeadlineStatuses() as $s): ?><option <?= $status===$s?'selected':'' ?>><?= e($s) ?></option><?php endforeach; ?></select></div>
<div class="col-xl-2"><label class="form-label small">Priority</label><select class="form-select form-select-sm" name="priority"><option value="">All</option><?php foreach(['Low','Normal','High','Urgent'] as $s): ?><option <?= $priority===$s?'selected':'' ?>><?= e($s) ?></option><?php endforeach; ?></select></div>
<div class="col-xl-3"><label class="form-label small">Responsible User</label><select class="form-select form-select-sm" name="responsible_user_id"><option value="">All</option><?php foreach($users as $u): ?><option value="<?= (int)$u['id'] ?>" <?= $owner===(int)$u['id']?'selected':'' ?>><?= e($u['full_name']) ?></option><?php endforeach; ?></select></div>
<div class="col-xl-1"><button class="btn btn-outline-primary btn-sm w-100"><i class="bi bi-funnel"></i></button></div>
</form></div></div>

<div class="card lo-card"><div class="card-header d-flex justify-content-between"><span>Deadline Registry</span><a class="btn btn-sm btn-outline-primary" href="report.php">Report</a></div><div class="table-responsive"><table class="table table-hover lo-table mb-0"><thead><tr><th>Deadline</th><th>Due</th><th>Context</th><th>Responsible</th><th>Priority</th><th>Reminders</th><th>Status</th><th class="text-end">Open</th></tr></thead><tbody>
<?php if(!$rows): ?><tr><td colspan="8" class="text-center text-muted py-5">No deadlines found.</td></tr><?php endif; ?>
<?php foreach($rows as $r): ?><tr><td><span class="lo-code"><?= e($r['deadline_reference']) ?></span><div><strong><?= e($r['title']) ?></strong></div><div class="small text-muted"><?= e($r['deadline_type']) ?></div></td><td><?= formatDateTime($r['due_datetime']) ?><?php if($r['status']==='Overdue'): ?><div class="small text-danger fw-bold">OVERDUE</div><?php endif; ?></td><td><?= e($r['legislative_reference']?:$r['agenda_reference']?:$r['event_reference']?:$r['meeting_reference']?:'General') ?><div class="small text-muted"><?= e($r['committee_name']?:$r['office_name']?:'') ?></div></td><td><?= e($r['responsible_name']?:'Unassigned') ?><div class="small text-muted"><?= (int)$r['assignment_count'] ?> assignment(s)</div></td><td><?= e($r['priority_level']) ?><?php if((int)$r['escalation_count']): ?><div class="small text-danger"><?= (int)$r['escalation_count'] ?> escalation(s)</div><?php endif; ?></td><td><?= (int)$r['reminder_count'] ?> scheduled</td><td><span class="lo-status <?= e(lacmsDeadlineStatusClass($r['status'])) ?>"><?= e($r['status']) ?></span></td><td class="text-end"><a class="btn btn-sm btn-outline-primary" href="view.php?id=<?= (int)$r['id'] ?>"><i class="bi bi-eye"></i></a></td></tr><?php endforeach; ?>
</tbody></table></div></div>
</main></div>

<?php if(lacmsOperationalManager()): ?>
<div class="modal fade" id="deadlineModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" style="max-width: 680px;">
    <div class="modal-content shadow-lg border-0 rounded-3 overflow-hidden">
      <form id="deadlineForm">
        <?= csrfField() ?>
        <input type="hidden" name="id" value="0">
        <div class="modal-header py-2 px-3 border-0" style="background: linear-gradient(135deg, #0f172a, #1e293b); color: #fff;">
          <div class="d-flex align-items-center gap-2">
            <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 28px; height: 28px; background: rgba(59,130,246,0.25); color: #60a5fa; font-size: 12px;">
              <i class="bi bi-clock"></i>
            </div>
            <h6 class="modal-title fw-bold m-0" style="font-size: 0.95rem;">New Legislative Deadline</h6>
          </div>
          <button class="btn-close btn-close-white" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body p-3">
          <div class="row g-2">
            <div class="col-md-8">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Deadline Title *</label>
              <input class="form-control form-control-sm" name="title" required>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Deadline Type</label>
              <select class="form-select form-select-sm" name="deadline_type">
                <option>Legislative Task</option>
                <option>Document Submission</option>
                <option>Committee Action</option>
                <option>Meeting Preparation</option>
                <option>Agenda Submission</option>
                <option>Compliance</option>
                <option>Executive Coordination</option>
                <option>Other</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Due Date & Time *</label>
              <input type="datetime-local" class="form-control form-control-sm" name="due_datetime" required>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Priority</label>
              <select class="form-select form-select-sm" name="priority_level">
                <option>Low</option>
                <option selected>Normal</option>
                <option>High</option>
                <option>Urgent</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Reminder Policy</label>
              <select class="form-select form-select-sm" name="reminder_policy">
                <option>None</option>
                <option>Minimal</option>
                <option selected>Standard</option>
                <option>Extended</option>
                <option>Urgent</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Responsible User</label>
              <select class="form-select form-select-sm" name="responsible_user_id">
                <option value="">Unassigned</option>
                <?php foreach($users as $u): ?>
                  <option value="<?= (int)$u['id'] ?>"><?= e($u['full_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Office</label>
              <select class="form-select form-select-sm" name="office_id">
                <option value="">None</option>
                <?php foreach($offices as $o): ?>
                  <option value="<?= (int)$o['id'] ?>"><?= e($o['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Committee</label>
              <select class="form-select form-select-sm" name="committee_id">
                <option value="">None</option>
                <?php foreach($committees as $c): ?>
                  <option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Legislative Item</label>
              <select class="form-select form-select-sm" name="legislative_item_id">
                <option value="">None</option>
                <?php foreach($legislative as $x): ?>
                  <option value="<?= (int)$x['id'] ?>"><?= e($x['reference_number'].' · '.$x['title']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Agenda</label>
              <select class="form-select form-select-sm" name="agenda_id">
                <option value="">None</option>
                <?php foreach($agendas as $x): ?>
                  <option value="<?= (int)$x['id'] ?>"><?= e($x['agenda_reference'].' · '.$x['title']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Calendar Event</label>
              <select class="form-select form-select-sm" name="calendar_event_id">
                <option value="">None</option>
                <?php foreach($events as $x): ?>
                  <option value="<?= (int)$x['id'] ?>"><?= e($x['event_reference'].' · '.$x['title']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Meeting</label>
              <select class="form-select form-select-sm" name="meeting_id">
                <option value="">None</option>
                <?php foreach($meetings as $x): ?>
                  <option value="<?= (int)$x['id'] ?>"><?= e($x['meeting_reference'].' · '.$x['title']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Description</label>
              <textarea class="form-control form-control-sm" name="description" rows="2" style="resize: vertical; min-height: 50px;"></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer py-2 px-3 bg-light border-top">
          <button class="btn btn-sm btn-outline-secondary px-3" type="button" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-sm btn-primary px-4 fw-semibold"><i class="bi bi-check2 me-1"></i>Create Deadline</button>
        </div>
      </form>
    </div>
  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded',function(){
 const form=document.getElementById('deadlineForm'),modal=new bootstrap.Modal(document.getElementById('deadlineModal'));
 document.getElementById('btnNewDeadline').onclick=()=>{form.reset();modal.show();};
 form.onsubmit=async e=>{e.preventDefault();const r=await fetch('ajax_save.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:new FormData(form)}).then(x=>x.json());if(r.success)location.href='view.php?id='+r.id;else Swal.fire('Deadline Error',r.message,'error');};
});
</script>
<?php endif; ?>
<?php include __DIR__.'/../../layouts/footer.php'; ?>
