<?php
declare(strict_types=1);

require_once __DIR__.'/../../includes/lacms_operational_helpers.php';
requireLacmsPermission('lacms.calendar.view');

$pdo=db();$id=(int)($_GET['id']??0);
$q=$pdo->prepare(
 "SELECT e.*,a.agenda_reference,a.title agenda_title,li.reference_number legislative_reference,
         li.title legislative_title,c.name committee_name,o.name office_name,
         u.full_name created_name,cu.full_name confirmed_name
  FROM lacms_calendar_events e
  LEFT JOIN lacms_agendas a ON a.id=e.agenda_id
  LEFT JOIN legislative_items li ON li.id=e.legislative_item_id
  LEFT JOIN committees c ON c.id=e.committee_id
  LEFT JOIN offices o ON o.id=e.office_id
  LEFT JOIN users u ON u.id=e.created_by
  LEFT JOIN users cu ON cu.id=e.confirmed_by
  WHERE e.id=:id"
);$q->execute([':id'=>$id]);$event=$q->fetch();
if(!$event){setFlash('warning','Calendar event not found.');redirect(appUrl('modules/calendar/index.php'));}

$p=$pdo->prepare(
 "SELECT p.*,u.full_name,u.email
  FROM lacms_calendar_event_participants p
  LEFT JOIN users u ON u.id=p.user_id
  WHERE p.calendar_event_id=:id ORDER BY p.attendance_required DESC,COALESCE(u.full_name,p.external_name)"
);$p->execute([':id'=>$id]);$participants=$p->fetchAll();

$c=$pdo->prepare(
 "SELECT x.*,e.event_reference conflicting_reference,e.title conflicting_title,e.start_datetime conflicting_start
  FROM lacms_calendar_conflicts x
  JOIN lacms_calendar_events e ON e.id=x.conflicting_event_id
  WHERE x.calendar_event_id=:id ORDER BY x.status,x.severity DESC,x.created_at DESC"
);$c->execute([':id'=>$id]);$conflicts=$c->fetchAll();

$history=$pdo->prepare("SELECT h.*,u.full_name changed_name FROM lacms_calendar_history h LEFT JOIN users u ON u.id=h.changed_by WHERE h.calendar_event_id=:id ORDER BY h.created_at DESC,h.id DESC");$history->execute([':id'=>$id]);$historyRows=$history->fetchAll();
$docs=$pdo->prepare("SELECT d.*,u.full_name uploaded_name FROM lacms_calendar_event_documents d LEFT JOIN users u ON u.id=d.uploaded_by WHERE d.calendar_event_id=:id ORDER BY d.uploaded_at DESC,d.id DESC");$docs->execute([':id'=>$id]);$documents=$docs->fetchAll();

$users=$pdo->query("SELECT u.id,u.full_name,u.email FROM users u JOIN roles r ON r.id=u.role_id WHERE u.status='Active' AND u.deleted_at IS NULL AND r.name IN ('Administrator','Legislative Staff','Committee Member') ORDER BY u.full_name")->fetchAll();
$agendas=$pdo->query("SELECT id,agenda_reference,title FROM lacms_agendas WHERE status IN ('Under Review','Finalized') ORDER BY agenda_date DESC,created_at DESC")->fetchAll();
$legislativeItems=$pdo->query("SELECT li.id,li.reference_number,li.title FROM legislative_items li JOIN legislative_item_types t ON t.id=li.item_type_id WHERE li.deleted_at IS NULL AND t.code IN ('ordinance','resolution','proposal','executive_request','policy_matter') ORDER BY li.updated_at DESC LIMIT 300")->fetchAll();
$committees=$pdo->query("SELECT id,name FROM committees WHERE status='Active' ORDER BY name")->fetchAll();$offices=$pdo->query("SELECT id,name FROM offices WHERE status='Active' ORDER BY name")->fetchAll();

$pageTitle=$event['event_reference'];$activeMenu='calendar';$extraCss=[appUrl('assets/css/lacms-operational.css')];
include __DIR__.'/../../layouts/header.php';
?>
<div class="lacms-app-wrapper"><?php include __DIR__.'/../../layouts/sidebar.php'; ?><main class="lacms-main-content">
<div class="lo-head"><div><a class="small text-decoration-none" href="index.php"><i class="bi bi-arrow-left"></i> Master Calendar</a><div class="lo-eyebrow mt-2"><?= e($event['event_reference']) ?> · <?= e($event['event_type']) ?></div><h1><?= e($event['title']) ?></h1><p><?= formatDateTime($event['start_datetime']) ?><?= $event['end_datetime']?' → '.formatDateTime($event['end_datetime']):'' ?> · <?= e($event['venue']?:'Venue TBA') ?></p></div><div class="d-flex gap-2 flex-wrap"><span class="lo-status <?= $event['conflict_status']==='Detected'?'bad':($event['conflict_status']==='Clear'?'good':'warn') ?>"><?= e($event['conflict_status']) ?></span><span class="lo-status <?= $event['status']==='Confirmed'?'good':(in_array($event['status'],['Cancelled','Postponed'],true)?'bad':'') ?>"><?= e($event['status']) ?></span><a class="btn btn-outline-secondary btn-sm" target="_blank" href="print.php?id=<?= $id ?>"><i class="bi bi-printer"></i></a></div></div>

<div class="row g-3">
<div class="col-xl-8">
<div class="card lo-card mb-3"><div class="card-header d-flex justify-content-between"><span>Schedule Information</span><?php if(lacmsOperationalManager()&&!in_array($event['status'],['Completed','Cancelled'],true)): ?><button class="btn btn-sm btn-primary" id="btnEdit"><i class="bi bi-pencil"></i> Edit / Reschedule</button><?php endif; ?></div><div class="card-body"><div class="lo-grid"><div><small>Start</small><strong><?= formatDateTime($event['start_datetime']) ?></strong></div><div><small>End</small><strong><?= $event['end_datetime']?formatDateTime($event['end_datetime']):'—' ?></strong></div><div><small>Venue</small><strong><?= e($event['venue']?:'TBA') ?></strong></div><div><small>Committee</small><strong><?= e($event['committee_name']?:'—') ?></strong></div><div><small>Agenda</small><strong><?= e($event['agenda_reference']?:'—') ?></strong></div><div><small>Legislative Item</small><strong><?= e($event['legislative_reference']?:'—') ?></strong></div></div><?php if($event['description']): ?><div class="lo-alert mt-3"><?= nl2br(e($event['description'])) ?></div><?php endif; ?></div></div>

<div class="card lo-card mb-3"><div class="card-header">Conflict Review</div><div class="card-body d-grid gap-2"><?php if(!$conflicts): ?><div class="alert alert-success mb-0"><i class="bi bi-check-circle"></i> No recorded schedule conflicts.</div><?php endif; ?><?php foreach($conflicts as $x): ?><div class="lo-conflict <?= $x['severity']==='Warning'?'warning':'' ?>"><strong><?= e($x['conflict_type'].' · '.$x['severity'].' · '.$x['status']) ?></strong><small><?= e($x['details']) ?><br>Conflicting event: <?= e($x['conflicting_reference'].' · '.$x['conflicting_title']) ?> · <?= formatDateTime($x['conflicting_start']) ?></small><?php if($x['resolution_notes']): ?><small><strong>Resolution:</strong> <?= e($x['resolution_notes']) ?></small><?php endif; ?></div><?php endforeach; ?></div></div>

<div class="card lo-card mb-3"><div class="card-header d-flex justify-content-between"><span>Participants</span><?php if(lacmsOperationalManager()&&!in_array($event['status'],['Completed','Cancelled'],true)): ?><button class="btn btn-sm btn-primary" id="btnAddParticipant"><i class="bi bi-person-plus"></i> Add</button><?php endif; ?></div><div class="card-body d-grid gap-2"><?php if(!$participants): ?><div class="text-muted small">No participants assigned.</div><?php endif; ?><?php foreach($participants as $x): ?><div class="lo-participant"><div><strong><?= e($x['full_name']?:$x['external_name']?:'Unnamed participant') ?></strong><small><?= e($x['participant_role'].' · '.$x['response_status']) ?><?= $x['attendance_required']?' · Required':'' ?><?= ($x['email']?:$x['external_email'])?' · '.e($x['email']?:$x['external_email']):'' ?></small></div><?php if(lacmsOperationalManager()&&!in_array($event['status'],['Completed','Cancelled'],true)): ?><button class="btn btn-sm btn-outline-danger delete-participant" data-id="<?= (int)$x['id'] ?>">Remove</button><?php endif; ?></div><?php endforeach; ?></div></div>

<div class="card lo-card mb-3"><div class="card-header">Documents</div><div class="card-body"><div class="list-group mb-3"><?php if(!$documents): ?><div class="list-group-item text-muted">No calendar documents uploaded.</div><?php endif; ?><?php foreach($documents as $d): ?><a class="list-group-item list-group-item-action" target="_blank" href="<?= e(UPLOAD_URL.$d['file_path']) ?>"><strong><?= e($d['file_name']) ?></strong><div class="small text-muted"><?= e($d['document_type'].' · '.$d['visibility']) ?> · <?= formatDateTime($d['uploaded_at']) ?></div></a><?php endforeach; ?></div><?php if(lacmsOperationalManager()): ?><form id="docForm" class="row g-2" enctype="multipart/form-data"><?= csrfField() ?><input type="hidden" name="calendar_event_id" value="<?= $id ?>"><div class="col-md-6"><input type="file" class="form-control form-control-sm" name="document" required></div><div class="col-md-3"><input class="form-control form-control-sm" name="document_type" value="Calendar Document"></div><div class="col-md-3"><button class="btn btn-outline-primary btn-sm w-100">Upload</button></div></form><?php endif; ?></div></div>
</div>

<div class="col-xl-4">
<?php if(lacmsOperationalManager()): ?><div class="card lo-card mb-3"><div class="card-header">Schedule Workflow</div><div class="card-body d-grid gap-2"><?php if(in_array($event['status'],['Tentative','Postponed'],true)): ?><button class="btn btn-success event-action" data-action="confirm">Confirm Schedule</button><?php endif; ?><?php if($event['status']==='Confirmed'): ?><button class="btn btn-primary event-action" data-action="start">Start Activity</button><?php endif; ?><?php if(in_array($event['status'],['Confirmed','In Progress'],true)): ?><button class="btn btn-success event-action" data-action="complete">Complete Activity</button><?php endif; ?><?php if(!in_array($event['status'],['Completed','Cancelled'],true)): ?><button class="btn btn-outline-warning event-action" data-action="postpone">Postpone</button><button class="btn btn-outline-danger event-action" data-action="cancel">Cancel</button><?php endif; ?><?php if($event['status']==='Confirmed'): ?><a class="btn btn-outline-primary" href="<?= e(appUrl('modules/meetings/index.php?calendar_event_id='.$id)) ?>">Create / Coordinate Meeting</a><?php endif; ?></div></div><?php endif; ?>

<div class="card lo-card"><div class="card-header">Schedule History</div><div class="card-body lo-history"><?php if(!$historyRows): ?><div class="text-muted small">No history yet.</div><?php endif; ?><?php foreach($historyRows as $h): ?><div><strong><?= e($h['action']) ?> · <?= e($h['changed_name']?:'System') ?></strong><small><?= e(($h['previous_status']?:'—').' → '.($h['new_status']?:'—')) ?><?= $h['details']?'<br>'.e($h['details']):'' ?><br><?= formatDateTime($h['created_at']) ?></small></div><?php endforeach; ?></div></div>
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
            <h6 class="modal-title fw-bold m-0" style="font-size: 0.95rem;">Edit / Reschedule Event</h6>
          </div>
          <button class="btn-close btn-close-white" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body p-3">
          <div class="row g-2">
            <div class="col-md-4">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Event Type</label>
              <input class="form-control form-control-sm" name="event_type" value="<?= e($event['event_type']) ?>">
            </div>
            <div class="col-md-8">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Title</label>
              <input class="form-control form-control-sm" name="title" value="<?= e($event['title']) ?>" required>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Agenda</label>
              <select class="form-select form-select-sm" name="agenda_id">
                <option value="">None</option>
                <?php foreach($agendas as $a): ?>
                  <option value="<?= (int)$a['id'] ?>" <?= (int)$event['agenda_id']===(int)$a['id']?'selected':'' ?>><?= e($a['agenda_reference'].' · '.$a['title']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Legislative Item</label>
              <select class="form-select form-select-sm" name="legislative_item_id">
                <option value="">None</option>
                <?php foreach($legislativeItems as $li): ?>
                  <option value="<?= (int)$li['id'] ?>" <?= (int)$event['legislative_item_id']===(int)$li['id']?'selected':'' ?>><?= e($li['reference_number'].' · '.$li['title']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Start</label>
              <input type="datetime-local" class="form-control form-control-sm" name="start_datetime" value="<?= e(date('Y-m-d\TH:i',strtotime($event['start_datetime']))) ?>" required>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">End</label>
              <input type="datetime-local" class="form-control form-control-sm" name="end_datetime" value="<?= $event['end_datetime']?e(date('Y-m-d\TH:i',strtotime($event['end_datetime']))):'' ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Venue</label>
              <input class="form-control form-control-sm" name="venue" value="<?= e($event['venue']?:'') ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Meeting Link</label>
              <input class="form-control form-control-sm" name="meeting_link" value="<?= e($event['meeting_link']?:'') ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Committee</label>
              <select class="form-select form-select-sm" name="committee_id">
                <option value="">None</option>
                <?php foreach($committees as $c): ?>
                  <option value="<?= (int)$c['id'] ?>" <?= (int)$event['committee_id']===(int)$c['id']?'selected':'' ?>><?= e($c['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Office</label>
              <select class="form-select form-select-sm" name="office_id">
                <option value="">None</option>
                <?php foreach($offices as $o): ?>
                  <option value="<?= (int)$o['id'] ?>" <?= (int)$event['office_id']===(int)$o['id']?'selected':'' ?>><?= e($o['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Description</label>
              <textarea class="form-control form-control-sm" name="description" rows="2" style="resize: vertical; min-height: 50px;"><?= e($event['description']?:'') ?></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer py-2 px-3 bg-light border-top">
          <button class="btn btn-sm btn-outline-secondary px-3" type="button" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-sm btn-primary px-4 fw-semibold"><i class="bi bi-check2 me-1"></i>Save & Recheck Conflicts</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="participantModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" style="max-width: 580px;">
    <div class="modal-content shadow-lg border-0 rounded-3 overflow-hidden">
      <form id="participantForm">
        <?= csrfField() ?>
        <input type="hidden" name="calendar_event_id" value="<?= $id ?>">
        <input type="hidden" name="participant_id" value="0">
        <input type="hidden" name="mode" value="save">
        <div class="modal-header py-2 px-3 border-0" style="background: linear-gradient(135deg, #0f172a, #1e293b); color: #fff;">
          <div class="d-flex align-items-center gap-2">
            <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 28px; height: 28px; background: rgba(59,130,246,0.25); color: #60a5fa; font-size: 12px;">
              <i class="bi bi-person-plus"></i>
            </div>
            <h6 class="modal-title fw-bold m-0" style="font-size: 0.95rem;">Calendar Participant</h6>
          </div>
          <button class="btn-close btn-close-white" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body p-3">
          <div class="row g-2">
            <div class="col-12">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Internal User</label>
              <select class="form-select form-select-sm" name="user_id">
                <option value="">External / Other</option>
                <?php foreach($users as $u): ?>
                  <option value="<?= (int)$u['id'] ?>"><?= e($u['full_name'].' · '.$u['email']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">External Name</label>
              <input class="form-control form-control-sm" name="external_name">
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">External Email</label>
              <input type="email" class="form-control form-control-sm" name="external_email">
            </div>
            <div class="col-md-5">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Role</label>
              <input class="form-control form-control-sm" name="participant_role" value="Participant">
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Response</label>
              <select class="form-select form-select-sm" name="response_status">
                <option>Pending</option>
                <option>Accepted</option>
                <option>Tentative</option>
                <option>Declined</option>
              </select>
            </div>
            <div class="col-md-3 d-flex align-items-end">
              <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" name="attendance_required" value="1" id="required" checked>
                <label class="form-check-label small" for="required">Required</label>
              </div>
            </div>
            <div class="col-12">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Notes</label>
              <textarea class="form-control form-control-sm" name="notes" rows="2" style="resize: vertical; min-height: 50px;"></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer py-2 px-3 bg-light border-top">
          <button class="btn btn-sm btn-outline-secondary px-3" type="button" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-sm btn-primary px-4 fw-semibold"><i class="bi bi-plus-lg me-1"></i>Add Participant</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded',function(){
 const editForm=document.getElementById('editForm'),pForm=document.getElementById('participantForm'),docForm=document.getElementById('docForm');
 const em=new bootstrap.Modal(document.getElementById('editModal')),pm=new bootstrap.Modal(document.getElementById('participantModal'));
 const editBtn=document.getElementById('btnEdit');if(editBtn)editBtn.onclick=()=>em.show();
 const addBtn=document.getElementById('btnAddParticipant');if(addBtn)addBtn.onclick=()=>{pForm.reset();pm.show();};
 if(editForm)editForm.onsubmit=async e=>{e.preventDefault();const r=await fetch('ajax_save.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:new FormData(editForm)}).then(x=>x.json());if(r.success)location.reload();else Swal.fire('Calendar Error',r.message,'error');};
 if(pForm)pForm.onsubmit=async e=>{e.preventDefault();const r=await fetch('ajax_participant.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:new FormData(pForm)}).then(x=>x.json());if(r.success)location.reload();else Swal.fire('Participant Error',r.message,'error');};
 document.querySelectorAll('.delete-participant').forEach(b=>b.onclick=async function(){const x=await Swal.fire({title:'Remove participant?',showCancelButton:true});if(!x.isConfirmed)return;const fd=new FormData();fd.append('csrf_token','<?= e(csrfToken()) ?>');fd.append('calendar_event_id','<?= $id ?>');fd.append('participant_id',this.dataset.id);fd.append('mode','delete');const r=await fetch('ajax_participant.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:fd}).then(x=>x.json());if(r.success)location.reload();else Swal.fire('Participant Error',r.message,'error');});
 document.querySelectorAll('.event-action').forEach(b=>b.onclick=async function(){let notes='',override=0;if(this.dataset.action==='confirm'&&'<?= e($event['conflict_status']) ?>'==='Detected'){const x=await Swal.fire({title:'Schedule conflicts detected',text:'Administrators may confirm with a documented override. Otherwise adjust the schedule first.',input:'textarea',inputLabel:'Conflict override reason (Administrator only)',showCancelButton:true,confirmButtonText:'Confirm / Override'});if(!x.isConfirmed)return;notes=x.value||'';override=notes?1:0;}else if(['postpone','cancel'].includes(this.dataset.action)){const x=await Swal.fire({title:this.dataset.action==='cancel'?'Cancel event?':'Postpone event?',input:'textarea',inputLabel:'Reason',showCancelButton:true,inputValidator:v=>!v?'Reason is required':undefined});if(!x.isConfirmed)return;notes=x.value;}else{const x=await Swal.fire({title:this.dataset.action.charAt(0).toUpperCase()+this.dataset.action.slice(1)+' event?',showCancelButton:true});if(!x.isConfirmed)return;}const fd=new FormData();fd.append('csrf_token','<?= e(csrfToken()) ?>');fd.append('calendar_event_id','<?= $id ?>');fd.append('action',this.dataset.action);fd.append('notes',notes);fd.append('override_conflict',override);const r=await fetch('ajax_transition.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:fd}).then(x=>x.json());if(r.success)location.reload();else Swal.fire('Calendar Workflow',r.message,'error');});
 if(docForm)docForm.onsubmit=async e=>{e.preventDefault();const r=await fetch('ajax_upload_document.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:new FormData(docForm)}).then(x=>x.json());if(r.success)location.reload();else Swal.fire('Upload Error',r.message,'error');};
});
</script>
<?php endif; ?>
<?php include __DIR__.'/../../layouts/footer.php'; ?>
