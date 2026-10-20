<?php
declare(strict_types=1);

require_once __DIR__.'/../../includes/lacms_notification_helpers.php';
requireLacmsPermission('lacms.deadlines.view');

$pdo=db();lacmsRefreshDeadlineStatuses($pdo);$id=(int)($_GET['id']??0);
$d=lacmsDeadlineContext($pdo,$id);
if(!$d){setFlash('warning','Deadline not found.');redirect(appUrl('modules/deadlines/index.php'));}

$assign=$pdo->prepare(
 "SELECT a.*,u.full_name user_name,o.name office_name,c.name committee_name
  FROM lacms_deadline_assignments a
  LEFT JOIN users u ON u.id=a.user_id
  LEFT JOIN offices o ON o.id=a.office_id
  LEFT JOIN committees c ON c.id=a.committee_id
  WHERE a.deadline_id=:id ORDER BY a.assigned_at,a.id"
);$assign->execute([':id'=>$id]);$assignments=$assign->fetchAll();

$rem=$pdo->prepare(
 "SELECT * FROM lacms_deadline_reminders
  WHERE deadline_id=:id ORDER BY remind_at,id"
);$rem->execute([':id'=>$id]);$reminders=$rem->fetchAll();

$esc=$pdo->prepare(
 "SELECT x.*,u.full_name created_name,ru.full_name resolved_name
  FROM lacms_deadline_escalations x
  LEFT JOIN users u ON u.id=x.created_by
  LEFT JOIN users ru ON ru.id=x.resolved_by
  WHERE x.deadline_id=:id ORDER BY x.created_at DESC,x.id DESC"
);$esc->execute([':id'=>$id]);$escalations=$esc->fetchAll();

$hist=$pdo->prepare(
 "SELECT h.*,u.full_name changed_name
  FROM lacms_deadline_history h
  LEFT JOIN users u ON u.id=h.changed_by
  WHERE h.deadline_id=:id ORDER BY h.created_at DESC,h.id DESC"
);$hist->execute([':id'=>$id]);$history=$hist->fetchAll();

$docs=$pdo->prepare(
 "SELECT d.*,u.full_name uploaded_name
  FROM lacms_deadline_documents d
  LEFT JOIN users u ON u.id=d.uploaded_by
  WHERE d.deadline_id=:id ORDER BY d.uploaded_at DESC,d.id DESC"
);$docs->execute([':id'=>$id]);$documents=$docs->fetchAll();

$users=$pdo->query("SELECT id,full_name FROM users WHERE status='Active' AND deleted_at IS NULL ORDER BY full_name")->fetchAll();
$offices=$pdo->query("SELECT id,name FROM offices WHERE status='Active' ORDER BY name")->fetchAll();
$committees=$pdo->query("SELECT id,name FROM committees WHERE status='Active' ORDER BY name")->fetchAll();
$agendas=$pdo->query("SELECT id,agenda_reference,title FROM lacms_agendas ORDER BY created_at DESC LIMIT 250")->fetchAll();
$events=$pdo->query("SELECT id,event_reference,title FROM lacms_calendar_events ORDER BY start_datetime DESC LIMIT 250")->fetchAll();
$meetings=$pdo->query("SELECT id,meeting_reference,title FROM lacms_meetings ORDER BY start_datetime DESC LIMIT 250")->fetchAll();
$legislative=$pdo->query("SELECT id,reference_number,title FROM legislative_items WHERE deleted_at IS NULL ORDER BY updated_at DESC LIMIT 300")->fetchAll();

$pageTitle=$d['deadline_reference'];$activeMenu='deadlines';
$extraCss=[appUrl('assets/css/lacms-operational.css')];
include __DIR__.'/../../layouts/header.php';
?>
<div class="lacms-app-wrapper"><?php include __DIR__.'/../../layouts/sidebar.php'; ?><main class="lacms-main-content">

<div class="lo-head"><div><a class="small text-decoration-none" href="index.php"><i class="bi bi-arrow-left"></i> Deadline Registry</a><div class="lo-eyebrow mt-2"><?= e($d['deadline_reference']) ?> · <?= e($d['deadline_type']) ?></div><h1><?= e($d['title']) ?></h1><p>Due <?= formatDateTime($d['due_datetime']) ?> · <?= e($d['priority_level']) ?> priority · <?= e($d['responsible_name']?:'Unassigned') ?></p></div><div class="d-flex gap-2 flex-wrap"><span class="lo-status <?= e(lacmsDeadlineStatusClass($d['status'])) ?>"><?= e($d['status']) ?></span><a class="btn btn-outline-secondary btn-sm" target="_blank" href="print.php?id=<?= $id ?>"><i class="bi bi-printer"></i></a></div></div>

<div class="row g-3">
<div class="col-xl-8">
<div class="card lo-card mb-3"><div class="card-header d-flex justify-content-between"><span>Deadline Information</span><?php if(lacmsOperationalManager()&&!in_array($d['status'],['Completed','Cancelled'],true)): ?><button class="btn btn-sm btn-primary" id="btnEdit"><i class="bi bi-pencil"></i> Edit</button><?php endif; ?></div><div class="card-body">
<div class="lo-grid"><div><small>Due</small><strong><?= formatDateTime($d['due_datetime']) ?></strong></div><div><small>Reminder Policy</small><strong><?= e($d['reminder_policy']) ?></strong></div><div><small>Responsible</small><strong><?= e($d['responsible_name']?:'—') ?></strong></div><div><small>Office</small><strong><?= e($d['office_name']?:'—') ?></strong></div><div><small>Committee</small><strong><?= e($d['committee_name']?:'—') ?></strong></div><div><small>Legislative Item</small><strong><?= e($d['legislative_reference']?:'—') ?></strong></div><div><small>Agenda</small><strong><?= e($d['agenda_reference']?:'—') ?></strong></div><div><small>Calendar / Meeting</small><strong><?= e($d['event_reference']?:$d['meeting_reference']?:'—') ?></strong></div></div>
<?php if($d['description']): ?><div class="lo-alert mt-3"><?= nl2br(e($d['description'])) ?></div><?php endif; ?>
<?php if($d['completion_notes']): ?><div class="alert alert-success mt-3 mb-0"><strong>Closure Notes</strong><br><?= nl2br(e($d['completion_notes'])) ?></div><?php endif; ?>
</div></div>

<div class="card lo-card mb-3"><div class="card-header d-flex justify-content-between"><span>Assignments</span><?php if(lacmsOperationalManager()&&!in_array($d['status'],['Completed','Cancelled'],true)): ?><button class="btn btn-sm btn-primary" id="btnAssignment"><i class="bi bi-person-plus"></i> Add Assignment</button><?php endif; ?></div><div class="card-body d-grid gap-2">
<?php if(!$assignments): ?><div class="text-muted small">No additional assignments.</div><?php endif; ?>
<?php foreach($assignments as $a): ?><div class="lo-participant"><div><strong><?= e($a['user_name']?:$a['office_name']?:$a['committee_name']?:'Assignment') ?></strong><small><?= e($a['assignment_role'].' · '.$a['status']) ?> · Assigned <?= formatDateTime($a['assigned_at']) ?></small></div><?php if(lacmsOperationalManager()&&!in_array($d['status'],['Completed','Cancelled'],true)): ?><button class="btn btn-sm btn-outline-danger delete-assignment" data-id="<?= (int)$a['id'] ?>">Remove</button><?php endif; ?></div><?php endforeach; ?>
</div></div>

<div class="card lo-card mb-3"><div class="card-header d-flex justify-content-between"><span>Reminder Schedule</span><?php if(lacmsOperationalManager()&&!in_array($d['status'],['Completed','Cancelled'],true)): ?><div><button class="btn btn-sm btn-primary" id="btnReminder">Add Reminder</button><button class="btn btn-sm btn-primary" id="btnRegenerate">Regenerate Defaults</button></div><?php endif; ?></div><div class="table-responsive"><table class="table lo-table mb-0"><thead><tr><th>Reminder</th><th>Type</th><th>Status</th><th>Content Source</th></tr></thead><tbody><?php if(!$reminders): ?><tr><td colspan="4" class="text-center text-muted py-4">No reminders scheduled.</td></tr><?php endif; ?><?php foreach($reminders as $r): ?><tr><td><?= formatDateTime($r['remind_at']) ?></td><td><?= e($r['reminder_type']) ?></td><td><?= e($r['status']) ?></td><td><?= $r['generated_by_ai']?'AI-assisted':'Template / system' ?></td></tr><?php endforeach; ?></tbody></table></div></div>

<div class="card lo-card mb-3"><div class="card-header">Escalations</div><div class="card-body d-grid gap-2"><?php if(!$escalations): ?><div class="text-muted small">No escalations recorded.</div><?php endif; ?><?php foreach($escalations as $x): ?><div class="lo-conflict <?= $x['status']==='Resolved'?'warning':'' ?>"><strong><?= e($x['escalation_level'].' · '.$x['status']) ?></strong><small><?= e($x['reason']) ?><br>Raised <?= formatDateTime($x['created_at']) ?> by <?= e($x['created_name']?:'System') ?></small></div><?php endforeach; ?></div></div>

<div class="card lo-card mb-3"><div class="card-header">Documents & Evidence</div><div class="card-body"><div class="list-group mb-3"><?php if(!$documents): ?><div class="list-group-item text-muted">No deadline documents.</div><?php endif; ?><?php foreach($documents as $doc): ?><a class="list-group-item list-group-item-action" target="_blank" href="<?= e(UPLOAD_URL.$doc['file_path']) ?>"><strong><?= e($doc['file_name']) ?></strong><div class="small text-muted"><?= e($doc['document_type'].' · '.$doc['visibility']) ?> · <?= formatDateTime($doc['uploaded_at']) ?></div></a><?php endforeach; ?></div><?php if(lacmsOperationalManager()): ?><form id="docForm" class="row g-2" enctype="multipart/form-data"><?= csrfField() ?><input type="hidden" name="deadline_id" value="<?= $id ?>"><div class="col-md-6"><input type="file" class="form-control form-control-sm" name="document" required></div><div class="col-md-3"><input class="form-control form-control-sm" name="document_type" value="Deadline Evidence"></div><div class="col-md-3"><button class="btn btn-outline-primary btn-sm w-100">Upload</button></div></form><?php endif; ?></div></div>
</div>

<div class="col-xl-4">
<?php if(lacmsOperationalManager()): ?><div class="card lo-card mb-3"><div class="card-header">Deadline Workflow</div><div class="card-body d-grid gap-2">
<?php if(in_array($d['status'],['Pending','Overdue'],true)): ?><button class="btn btn-primary deadline-action" data-action="start">Start Work</button><?php endif; ?>
<?php if(!in_array($d['status'],['Completed','Cancelled'],true)): ?><a class="btn btn-warning" href="<?= e(appUrl('pages/ai_email_reminders.php?deadline_id='.$id)) ?>"><i class="bi bi-stars"></i> Generate Reminder</a><button class="btn btn-outline-danger deadline-action" data-action="escalate">Escalate</button><button class="btn btn-success deadline-action" data-action="complete">Complete Deadline</button><button class="btn btn-outline-secondary deadline-action" data-action="cancel">Cancel Deadline</button><?php endif; ?>
<?php if($d['status']==='Completed'&&isAdmin()): ?><button class="btn btn-outline-warning deadline-action" data-action="reopen">Reopen</button><?php endif; ?>
</div></div><?php endif; ?>

<div class="card lo-card"><div class="card-header">Deadline History</div><div class="card-body lo-history"><?php if(!$history): ?><div class="text-muted small">No history yet.</div><?php endif; ?><?php foreach($history as $h): ?><div><strong><?= e($h['action']) ?> · <?= e($h['changed_name']?:'System') ?></strong><small><?= e(($h['previous_status']?:'—').' → '.($h['new_status']?:'—')) ?><?= $h['details']?'<br>'.e($h['details']):'' ?><br><?= formatDateTime($h['created_at']) ?></small></div><?php endforeach; ?></div></div>
</div>
</div>
</main></div>

<?php if(lacmsOperationalManager()): ?>
<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" style="max-width: 680px;">
    <div class="modal-content shadow-lg border-0 rounded-3 overflow-hidden">
      <form id="editForm">
        <?= csrfField() ?>
        <input type="hidden" name="id" value="<?= $id ?>">
        <div class="modal-header py-2 px-3 border-0" style="background: linear-gradient(135deg, #0f172a, #1e293b); color: #fff;">
          <div class="d-flex align-items-center gap-2">
            <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 28px; height: 28px; background: rgba(59,130,246,0.25); color: #60a5fa; font-size: 12px;">
              <i class="bi bi-pencil-square"></i>
            </div>
            <h6 class="modal-title fw-bold m-0" style="font-size: 0.95rem;">Edit Deadline</h6>
          </div>
          <button class="btn-close btn-close-white" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body p-3">
          <div class="row g-2">
            <div class="col-md-8">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Title</label>
              <input class="form-control form-control-sm" name="title" value="<?= e($d['title']) ?>" required>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Type</label>
              <input class="form-control form-control-sm" name="deadline_type" value="<?= e($d['deadline_type']) ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Due</label>
              <input type="datetime-local" class="form-control form-control-sm" name="due_datetime" value="<?= e(date('Y-m-d\TH:i',strtotime($d['due_datetime']))) ?>" required>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Priority</label>
              <select class="form-select form-select-sm" name="priority_level">
                <?php foreach(['Low','Normal','High','Urgent'] as $x): ?>
                  <option <?= $d['priority_level']===$x?'selected':'' ?>><?= e($x) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Reminder Policy</label>
              <select class="form-select form-select-sm" name="reminder_policy">
                <?php foreach(['None','Minimal','Standard','Extended','Urgent'] as $x): ?>
                  <option <?= $d['reminder_policy']===$x?'selected':'' ?>><?= e($x) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Responsible</label>
              <select class="form-select form-select-sm" name="responsible_user_id">
                <option value="">Unassigned</option>
                <?php foreach($users as $u): ?>
                  <option value="<?= (int)$u['id'] ?>" <?= (int)$d['responsible_user_id']===(int)$u['id']?'selected':'' ?>><?= e($u['full_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Office</label>
              <select class="form-select form-select-sm" name="office_id">
                <option value="">None</option>
                <?php foreach($offices as $o): ?>
                  <option value="<?= (int)$o['id'] ?>" <?= (int)$d['office_id']===(int)$o['id']?'selected':'' ?>><?= e($o['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Committee</label>
              <select class="form-select form-select-sm" name="committee_id">
                <option value="">None</option>
                <?php foreach($committees as $c): ?>
                  <option value="<?= (int)$c['id'] ?>" <?= (int)$d['committee_id']===(int)$c['id']?'selected':'' ?>><?= e($c['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Legislative Item</label>
              <select class="form-select form-select-sm" name="legislative_item_id">
                <option value="">None</option>
                <?php foreach($legislative as $x): ?>
                  <option value="<?= (int)$x['id'] ?>" <?= (int)$d['legislative_item_id']===(int)$x['id']?'selected':'' ?>><?= e($x['reference_number'].' · '.$x['title']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Agenda</label>
              <select class="form-select form-select-sm" name="agenda_id">
                <option value="">None</option>
                <?php foreach($agendas as $x): ?>
                  <option value="<?= (int)$x['id'] ?>" <?= (int)$d['agenda_id']===(int)$x['id']?'selected':'' ?>><?= e($x['agenda_reference'].' · '.$x['title']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Calendar Event</label>
              <select class="form-select form-select-sm" name="calendar_event_id">
                <option value="">None</option>
                <?php foreach($events as $x): ?>
                  <option value="<?= (int)$x['id'] ?>" <?= (int)$d['calendar_event_id']===(int)$x['id']?'selected':'' ?>><?= e($x['event_reference'].' · '.$x['title']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Meeting</label>
              <select class="form-select form-select-sm" name="meeting_id">
                <option value="">None</option>
                <?php foreach($meetings as $x): ?>
                  <option value="<?= (int)$x['id'] ?>" <?= (int)$d['meeting_id']===(int)$x['id']?'selected':'' ?>><?= e($x['meeting_reference'].' · '.$x['title']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Description</label>
              <textarea class="form-control form-control-sm" name="description" rows="2" style="resize: vertical; min-height: 50px;"><?= e($d['description']?:'') ?></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer py-2 px-3 bg-light border-top">
          <button class="btn btn-sm btn-outline-secondary px-3" type="button" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-sm btn-primary px-4 fw-semibold"><i class="bi bi-check2 me-1"></i>Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="assignmentModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" style="max-width: 560px;">
    <div class="modal-content shadow-lg border-0 rounded-3 overflow-hidden">
      <form id="assignmentForm">
        <?= csrfField() ?>
        <input type="hidden" name="deadline_id" value="<?= $id ?>">
        <input type="hidden" name="assignment_id" value="0">
        <input type="hidden" name="mode" value="save">
        <div class="modal-header py-2 px-3 border-0" style="background: linear-gradient(135deg, #0f172a, #1e293b); color: #fff;">
          <div class="d-flex align-items-center gap-2">
            <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 28px; height: 28px; background: rgba(59,130,246,0.25); color: #60a5fa; font-size: 12px;">
              <i class="bi bi-person-check"></i>
            </div>
            <h6 class="modal-title fw-bold m-0" style="font-size: 0.95rem;">Deadline Assignment</h6>
          </div>
          <button class="btn-close btn-close-white" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body p-3">
          <div class="row g-2">
            <div class="col-12">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">User</label>
              <select class="form-select form-select-sm" name="user_id">
                <option value="">None</option>
                <?php foreach($users as $u): ?>
                  <option value="<?= (int)$u['id'] ?>"><?= e($u['full_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Office</label>
              <select class="form-select form-select-sm" name="office_id">
                <option value="">None</option>
                <?php foreach($offices as $o): ?>
                  <option value="<?= (int)$o['id'] ?>"><?= e($o['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Committee</label>
              <select class="form-select form-select-sm" name="committee_id">
                <option value="">None</option>
                <?php foreach($committees as $c): ?>
                  <option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Assignment Role</label>
              <input class="form-control form-control-sm" name="assignment_role" value="Responsible">
            </div>
          </div>
        </div>
        <div class="modal-footer py-2 px-3 bg-light border-top">
          <button class="btn btn-sm btn-outline-secondary px-3" type="button" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-sm btn-primary px-4 fw-semibold"><i class="bi bi-check2 me-1"></i>Save Assignment</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="reminderModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" style="max-width: 460px;">
    <div class="modal-content shadow-lg border-0 rounded-3 overflow-hidden">
      <form id="reminderForm">
        <?= csrfField() ?>
        <input type="hidden" name="deadline_id" value="<?= $id ?>">
        <input type="hidden" name="mode" value="add">
        <div class="modal-header py-2 px-3 border-0" style="background: linear-gradient(135deg, #0f172a, #1e293b); color: #fff;">
          <div class="d-flex align-items-center gap-2">
            <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 28px; height: 28px; background: rgba(245,158,11,0.25); color: #fbbf24; font-size: 12px;">
              <i class="bi bi-bell"></i>
            </div>
            <h6 class="modal-title fw-bold m-0" style="font-size: 0.95rem;">Manual Reminder</h6>
          </div>
          <button class="btn-close btn-close-white" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body p-3">
          <div class="row g-2">
            <div class="col-12">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Remind At</label>
              <input type="datetime-local" class="form-control form-control-sm" name="remind_at" required>
            </div>
          </div>
        </div>
        <div class="modal-footer py-2 px-3 bg-light border-top">
          <button class="btn btn-sm btn-outline-secondary px-3" type="button" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-sm btn-primary px-4 fw-semibold"><i class="bi bi-bell me-1"></i>Schedule Reminder</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded',function(){
 const editForm=document.getElementById('editForm'),assignForm=document.getElementById('assignmentForm'),remForm=document.getElementById('reminderForm'),docForm=document.getElementById('docForm');
 const em=new bootstrap.Modal(document.getElementById('editModal')),am=new bootstrap.Modal(document.getElementById('assignmentModal')),rm=new bootstrap.Modal(document.getElementById('reminderModal'));
 document.getElementById('btnEdit')?.addEventListener('click',()=>em.show());
 document.getElementById('btnAssignment')?.addEventListener('click',()=>{assignForm.reset();am.show();});
 document.getElementById('btnReminder')?.addEventListener('click',()=>{remForm.reset();rm.show();});
 if(editForm)editForm.onsubmit=async e=>{e.preventDefault();const r=await fetch('ajax_save.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:new FormData(editForm)}).then(x=>x.json());if(r.success)location.reload();else Swal.fire('Deadline Error',r.message,'error');};
 if(assignForm)assignForm.onsubmit=async e=>{e.preventDefault();const r=await fetch('ajax_assignment.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:new FormData(assignForm)}).then(x=>x.json());if(r.success)location.reload();else Swal.fire('Assignment Error',r.message,'error');};
 document.querySelectorAll('.delete-assignment').forEach(b=>b.onclick=async function(){const c=await Swal.fire({title:'Remove assignment?',showCancelButton:true});if(!c.isConfirmed)return;const fd=new FormData();fd.append('csrf_token','<?= e(csrfToken()) ?>');fd.append('deadline_id','<?= $id ?>');fd.append('assignment_id',this.dataset.id);fd.append('mode','delete');const r=await fetch('ajax_assignment.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:fd}).then(x=>x.json());if(r.success)location.reload();else Swal.fire('Assignment Error',r.message,'error');});
 if(remForm)remForm.onsubmit=async e=>{e.preventDefault();const r=await fetch('ajax_reminder.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:new FormData(remForm)}).then(x=>x.json());if(r.success)location.reload();else Swal.fire('Reminder Error',r.message,'error');};
 document.getElementById('btnRegenerate')?.addEventListener('click',async()=>{const c=await Swal.fire({title:'Regenerate default reminders?',text:'Existing scheduled reminders will be replaced.',showCancelButton:true});if(!c.isConfirmed)return;const fd=new FormData();fd.append('csrf_token','<?= e(csrfToken()) ?>');fd.append('deadline_id','<?= $id ?>');fd.append('mode','regenerate');const r=await fetch('ajax_reminder.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:fd}).then(x=>x.json());if(r.success)location.reload();else Swal.fire('Reminder Error',r.message,'error');});
 document.querySelectorAll('.deadline-action').forEach(b=>b.onclick=async function(){let notes='';if(['complete','reopen','cancel','escalate'].includes(this.dataset.action)){const c=await Swal.fire({title:this.dataset.action.charAt(0).toUpperCase()+this.dataset.action.slice(1)+' deadline?',input:'textarea',inputLabel:'Notes / reason',showCancelButton:true,inputValidator:v=>!v?'Notes are required':undefined});if(!c.isConfirmed)return;notes=c.value;}else{const c=await Swal.fire({title:'Start work on this deadline?',showCancelButton:true});if(!c.isConfirmed)return;}const fd=new FormData();fd.append('csrf_token','<?= e(csrfToken()) ?>');fd.append('deadline_id','<?= $id ?>');fd.append('action',this.dataset.action);fd.append('notes',notes);const r=await fetch('ajax_transition.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:fd}).then(x=>x.json());if(r.success)location.reload();else Swal.fire('Deadline Workflow',r.message,'error');});
 if(docForm)docForm.onsubmit=async e=>{e.preventDefault();const r=await fetch('ajax_upload_document.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:new FormData(docForm)}).then(x=>x.json());if(r.success)location.reload();else Swal.fire('Upload Error',r.message,'error');};
});
</script>
<?php endif; ?>

<?php include __DIR__.'/../../layouts/footer.php'; ?>
