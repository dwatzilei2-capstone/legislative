<?php
declare(strict_types=1);

require_once __DIR__.'/../../includes/lacms_operational_helpers.php';
requireLacmsPermission('lacms.meetings.view');

$pdo=db();$id=(int)($_GET['id']??0);
$q=$pdo->prepare(
 "SELECT m.*,e.event_reference,e.conflict_status,e.conflict_notes,
         a.agenda_reference,a.title agenda_title,c.name committee_name,o.name office_name,
         cu.full_name chair_name,su.full_name secretary_name,u.full_name created_name,
         fu.full_name confirmed_name
  FROM lacms_meetings m
  LEFT JOIN lacms_calendar_events e ON e.id=m.calendar_event_id
  LEFT JOIN lacms_agendas a ON a.id=m.agenda_id
  LEFT JOIN committees c ON c.id=m.committee_id
  LEFT JOIN offices o ON o.id=m.office_id
  LEFT JOIN users cu ON cu.id=m.chair_user_id
  LEFT JOIN users su ON su.id=m.secretary_user_id
  LEFT JOIN users u ON u.id=m.created_by
  LEFT JOIN users fu ON fu.id=m.confirmed_by
  WHERE m.id=:id"
);$q->execute([':id'=>$id]);$meeting=$q->fetch();
if(!$meeting){setFlash('warning','Meeting not found.');redirect(appUrl('modules/meetings/index.php'));}

$p=$pdo->prepare("SELECT p.*,u.full_name,u.email FROM lacms_meeting_participants p LEFT JOIN users u ON u.id=p.user_id WHERE p.meeting_id=:id ORDER BY p.attendance_required DESC,COALESCE(u.full_name,p.external_name)");$p->execute([':id'=>$id]);$participants=$p->fetchAll();
$history=$pdo->prepare("SELECT h.*,u.full_name changed_name FROM lacms_meeting_history h LEFT JOIN users u ON u.id=h.changed_by WHERE h.meeting_id=:id ORDER BY h.created_at DESC,h.id DESC");$history->execute([':id'=>$id]);$historyRows=$history->fetchAll();
$docs=$pdo->prepare("SELECT d.*,u.full_name uploaded_name FROM lacms_meeting_documents d LEFT JOIN users u ON u.id=d.uploaded_by WHERE d.meeting_id=:id ORDER BY d.uploaded_at DESC,d.id DESC");$docs->execute([':id'=>$id]);$documents=$docs->fetchAll();

$users=$pdo->query("SELECT u.id,u.full_name,u.email FROM users u JOIN roles r ON r.id=u.role_id WHERE u.status='Active' AND u.deleted_at IS NULL AND r.name IN ('Administrator','Legislative Staff','Committee Member') ORDER BY u.full_name")->fetchAll();
$events=$pdo->query("SELECT id,event_reference,title,start_datetime,status FROM lacms_calendar_events WHERE status NOT IN ('Completed','Cancelled') ORDER BY start_datetime")->fetchAll();
$agendas=$pdo->query("SELECT id,agenda_reference,title FROM lacms_agendas WHERE status IN ('Under Review','Finalized') ORDER BY agenda_date DESC,created_at DESC")->fetchAll();
$committees=$pdo->query("SELECT id,name FROM committees WHERE status='Active' ORDER BY name")->fetchAll();$offices=$pdo->query("SELECT id,name FROM offices WHERE status='Active' ORDER BY name")->fetchAll();

$pageTitle=$meeting['meeting_reference'];$activeMenu='meetings';$extraCss=[appUrl('assets/css/lacms-operational.css')];
include __DIR__.'/../../layouts/header.php';
?>
<div class="lacms-app-wrapper"><?php include __DIR__.'/../../layouts/sidebar.php'; ?><main class="lacms-main-content">
<div class="lo-head"><div><a class="small text-decoration-none" href="index.php"><i class="bi bi-arrow-left"></i> Meeting Registry</a><div class="lo-eyebrow mt-2"><?= e($meeting['meeting_reference']) ?> · <?= e($meeting['meeting_type']) ?></div><h1><?= e($meeting['title']) ?></h1><p><?= formatDateTime($meeting['start_datetime']) ?><?= $meeting['end_datetime']?' → '.formatDateTime($meeting['end_datetime']):'' ?> · <?= e($meeting['venue']?:'Venue TBA') ?></p></div><div class="d-flex gap-2 flex-wrap"><span class="lo-status <?= $meeting['conflict_status']==='Detected'?'bad':($meeting['conflict_status']==='Clear'?'good':'warn') ?>"><?= e($meeting['conflict_status']?:'Unchecked') ?></span><span class="lo-status <?= $meeting['status']==='Confirmed'?'good':(in_array($meeting['status'],['Cancelled','Postponed'],true)?'bad':($meeting['status']==='Planned'?'warn':'')) ?>"><?= e($meeting['status']) ?></span><a class="btn btn-outline-secondary btn-sm" target="_blank" href="print.php?id=<?= $id ?>"><i class="bi bi-printer"></i></a></div></div>

<div class="row g-3">
<div class="col-xl-8">
<div class="card lo-card mb-3"><div class="card-header d-flex justify-content-between"><span>Meeting Coordination Details</span><?php if(lacmsOperationalManager()&&!in_array($meeting['status'],['Completed','Cancelled'],true)): ?><button class="btn btn-sm btn-primary" id="btnEdit"><i class="bi bi-pencil"></i> Edit</button><?php endif; ?></div><div class="card-body"><div class="lo-grid"><div><small>Calendar Event</small><strong><?= e($meeting['event_reference']?:'—') ?></strong></div><div><small>Agenda</small><strong><?= e($meeting['agenda_reference']?:'—') ?></strong></div><div><small>Committee</small><strong><?= e($meeting['committee_name']?:'—') ?></strong></div><div><small>Office</small><strong><?= e($meeting['office_name']?:'—') ?></strong></div><div><small>Chair</small><strong><?= e($meeting['chair_name']?:'—') ?></strong></div><div><small>Secretary</small><strong><?= e($meeting['secretary_name']?:'—') ?></strong></div></div><?php if($meeting['purpose']): ?><div class="lo-alert mt-3"><strong>Purpose</strong><br><?= nl2br(e($meeting['purpose'])) ?></div><?php endif; ?><?php if($meeting['conflict_status']==='Detected'): ?><div class="alert alert-danger mt-3 mb-0"><strong>Schedule conflict detected.</strong><br><?= nl2br(e($meeting['conflict_notes']?:'Open the linked Calendar event to review the conflict.')) ?><br><a href="<?= e(appUrl('modules/calendar/view.php?id='.$meeting['calendar_event_id'])) ?>">Open Calendar Conflict Review</a></div><?php endif; ?></div></div>

<div class="card lo-card mb-3"><div class="card-header d-flex justify-content-between"><span>Participants & Attendance Planning</span><?php if(lacmsOperationalManager()&&!in_array($meeting['status'],['Completed','Cancelled'],true)): ?><button class="btn btn-sm btn-primary" id="btnAddParticipant"><i class="bi bi-person-plus"></i> Add</button><?php endif; ?></div><div class="card-body d-grid gap-2"><?php if(!$participants): ?><div class="text-muted small">No participants assigned.</div><?php endif; ?><?php foreach($participants as $x): ?><div class="lo-participant"><div><strong><?= e($x['full_name']?:$x['external_name']?:'Unnamed participant') ?></strong><small><?= e($x['participant_role'].' · Response: '.$x['response_status'].' · Attendance: '.$x['attendance_status']) ?><?= $x['attendance_required']?' · Required':'' ?><br>Notification: <?= e($x['notification_status']) ?></small></div><?php if(lacmsOperationalManager()&&!in_array($meeting['status'],['Completed','Cancelled'],true)): ?><button class="btn btn-sm btn-outline-danger delete-participant" data-id="<?= (int)$x['id'] ?>">Remove</button><?php endif; ?></div><?php endforeach; ?></div></div>

<div class="card lo-card mb-3"><div class="card-header">Meeting Documents</div><div class="card-body"><div class="list-group mb-3"><?php if(!$documents): ?><div class="list-group-item text-muted">No meeting documents uploaded.</div><?php endif; ?><?php foreach($documents as $d): ?><a class="list-group-item list-group-item-action" target="_blank" href="<?= e(UPLOAD_URL.$d['file_path']) ?>"><strong><?= e($d['file_name']) ?></strong><div class="small text-muted"><?= e($d['document_type'].' · '.$d['visibility']) ?> · <?= formatDateTime($d['uploaded_at']) ?></div></a><?php endforeach; ?></div><?php if(lacmsOperationalManager()): ?><form id="docForm" class="row g-2" enctype="multipart/form-data"><?= csrfField() ?><input type="hidden" name="meeting_id" value="<?= $id ?>"><div class="col-md-6"><input type="file" class="form-control form-control-sm" name="document" required></div><div class="col-md-3"><input class="form-control form-control-sm" name="document_type" value="Meeting Document"></div><div class="col-md-3"><button class="btn btn-outline-primary btn-sm w-100">Upload</button></div></form><?php endif; ?></div></div>
</div>

<div class="col-xl-4">
<?php if(lacmsOperationalManager()): ?><div class="card lo-card mb-3"><div class="card-header">Meeting Workflow</div><div class="card-body d-grid gap-2"><?php if(in_array($meeting['status'],['Planned','Postponed'],true)): ?><button class="btn btn-success meeting-action" data-action="confirm">Confirm & Queue Notices</button><?php endif; ?><?php if($meeting['status']==='Confirmed'): ?><button class="btn btn-primary meeting-action" data-action="start">Start Meeting</button><?php endif; ?><?php if(in_array($meeting['status'],['Confirmed','In Progress'],true)): ?><button class="btn btn-success meeting-action" data-action="complete">Complete Meeting</button><?php endif; ?><?php if(!in_array($meeting['status'],['Completed','Cancelled'],true)): ?><button class="btn btn-outline-warning meeting-action" data-action="postpone">Postpone</button><button class="btn btn-outline-danger meeting-action" data-action="cancel">Cancel</button><?php endif; ?><a class="btn btn-outline-primary" href="<?= e(appUrl('modules/calendar/view.php?id='.$meeting['calendar_event_id'])) ?>">Open Linked Calendar Event</a></div></div><?php endif; ?>

<div class="card lo-card"><div class="card-header">Meeting History</div><div class="card-body lo-history"><?php if(!$historyRows): ?><div class="text-muted small">No history yet.</div><?php endif; ?><?php foreach($historyRows as $h): ?><div><strong><?= e($h['action']) ?> · <?= e($h['changed_name']?:'System') ?></strong><small><?= e(($h['previous_status']?:'—').' → '.($h['new_status']?:'—')) ?><?= $h['details']?'<br>'.e($h['details']):'' ?><br><?= formatDateTime($h['created_at']) ?></small></div><?php endforeach; ?></div></div>
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
            <h6 class="modal-title fw-bold m-0" style="font-size: 0.95rem;">Edit Meeting</h6>
          </div>
          <button class="btn-close btn-close-white" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body p-3">
          <div class="row g-2">
            <div class="col-md-4">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Meeting Type</label>
              <input class="form-control form-control-sm" name="meeting_type" value="<?= e($meeting['meeting_type']) ?>">
            </div>
            <div class="col-md-8">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Title</label>
              <input class="form-control form-control-sm" name="title" value="<?= e($meeting['title']) ?>" required>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Calendar Event</label>
              <select class="form-select form-select-sm" name="calendar_event_id">
                <option value="">Automatic</option>
                <?php foreach($events as $e): ?>
                  <option value="<?= (int)$e['id'] ?>" <?= (int)$meeting['calendar_event_id']===(int)$e['id']?'selected':'' ?>><?= e($e['event_reference'].' · '.$e['title']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Agenda</label>
              <select class="form-select form-select-sm" name="agenda_id">
                <option value="">None</option>
                <?php foreach($agendas as $a): ?>
                  <option value="<?= (int)$a['id'] ?>" <?= (int)$meeting['agenda_id']===(int)$a['id']?'selected':'' ?>><?= e($a['agenda_reference'].' · '.$a['title']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Start</label>
              <input type="datetime-local" class="form-control form-control-sm" name="start_datetime" value="<?= e(date('Y-m-d\TH:i',strtotime($meeting['start_datetime']))) ?>" required>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">End</label>
              <input type="datetime-local" class="form-control form-control-sm" name="end_datetime" value="<?= $meeting['end_datetime']?e(date('Y-m-d\TH:i',strtotime($meeting['end_datetime']))):'' ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Venue</label>
              <input class="form-control form-control-sm" name="venue" value="<?= e($meeting['venue']?:'') ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Meeting Link</label>
              <input class="form-control form-control-sm" name="meeting_link" value="<?= e($meeting['meeting_link']?:'') ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Committee</label>
              <select class="form-select form-select-sm" name="committee_id">
                <option value="">None</option>
                <?php foreach($committees as $c): ?>
                  <option value="<?= (int)$c['id'] ?>" <?= (int)$meeting['committee_id']===(int)$c['id']?'selected':'' ?>><?= e($c['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Office</label>
              <select class="form-select form-select-sm" name="office_id">
                <option value="">None</option>
                <?php foreach($offices as $o): ?>
                  <option value="<?= (int)$o['id'] ?>" <?= (int)$meeting['office_id']===(int)$o['id']?'selected':'' ?>><?= e($o['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Chair</label>
              <select class="form-select form-select-sm" name="chair_user_id">
                <option value="">None</option>
                <?php foreach($users as $u): ?>
                  <option value="<?= (int)$u['id'] ?>" <?= (int)$meeting['chair_user_id']===(int)$u['id']?'selected':'' ?>><?= e($u['full_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Secretary</label>
              <select class="form-select form-select-sm" name="secretary_user_id">
                <option value="">None</option>
                <?php foreach($users as $u): ?>
                  <option value="<?= (int)$u['id'] ?>" <?= (int)$meeting['secretary_user_id']===(int)$u['id']?'selected':'' ?>><?= e($u['full_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Purpose</label>
              <textarea class="form-control form-control-sm" name="purpose" rows="2" style="resize: vertical; min-height: 50px;"><?= e($meeting['purpose']?:'') ?></textarea>
            </div>
            <div class="col-12">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Coordination Notes</label>
              <textarea class="form-control form-control-sm" name="coordination_notes" rows="2" style="resize: vertical; min-height: 50px;"><?= e($meeting['coordination_notes']?:'') ?></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer py-2 px-3 bg-light border-top">
          <button class="btn btn-sm btn-outline-secondary px-3" type="button" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-sm btn-primary px-4 fw-semibold"><i class="bi bi-check2 me-1"></i>Save & Synchronize</button>
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
        <input type="hidden" name="meeting_id" value="<?= $id ?>">
        <input type="hidden" name="participant_id" value="0">
        <input type="hidden" name="mode" value="save">
        <div class="modal-header py-2 px-3 border-0" style="background: linear-gradient(135deg, #0f172a, #1e293b); color: #fff;">
          <div class="d-flex align-items-center gap-2">
            <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 28px; height: 28px; background: rgba(59,130,246,0.25); color: #60a5fa; font-size: 12px;">
              <i class="bi bi-person-plus"></i>
            </div>
            <h6 class="modal-title fw-bold m-0" style="font-size: 0.95rem;">Meeting Participant</h6>
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
            <div class="col-md-4">
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
            <div class="col-md-4">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Attendance</label>
              <select class="form-select form-select-sm" name="attendance_status">
                <option>Not Recorded</option>
                <option>Present</option>
                <option>Remote</option>
                <option>Absent</option>
                <option>Excused</option>
              </select>
            </div>
            <div class="col-12">
              <div class="form-check py-1">
                <input class="form-check-input" type="checkbox" name="attendance_required" value="1" id="mreq" checked>
                <label class="form-check-label small" for="mreq">Required participant</label>
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
 if(editForm)editForm.onsubmit=async e=>{e.preventDefault();const r=await fetch('ajax_save.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:new FormData(editForm)}).then(x=>x.json());if(r.success)location.reload();else Swal.fire('Meeting Error',r.message,'error');};
 if(pForm)pForm.onsubmit=async e=>{e.preventDefault();const r=await fetch('ajax_participant.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:new FormData(pForm)}).then(x=>x.json());if(r.success)location.reload();else Swal.fire('Participant Error',r.message,'error');};
 document.querySelectorAll('.delete-participant').forEach(b=>b.onclick=async function(){const x=await Swal.fire({title:'Remove participant?',showCancelButton:true});if(!x.isConfirmed)return;const fd=new FormData();fd.append('csrf_token','<?= e(csrfToken()) ?>');fd.append('meeting_id','<?= $id ?>');fd.append('participant_id',this.dataset.id);fd.append('mode','delete');const r=await fetch('ajax_participant.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:fd}).then(x=>x.json());if(r.success)location.reload();else Swal.fire('Participant Error',r.message,'error');});
 document.querySelectorAll('.meeting-action').forEach(b=>b.onclick=async function(){let notes='';if(['postpone','cancel'].includes(this.dataset.action)){const x=await Swal.fire({title:this.dataset.action==='cancel'?'Cancel meeting?':'Postpone meeting?',input:'textarea',inputLabel:'Reason',showCancelButton:true,inputValidator:v=>!v?'Reason is required':undefined});if(!x.isConfirmed)return;notes=x.value;}else{const x=await Swal.fire({title:this.dataset.action.charAt(0).toUpperCase()+this.dataset.action.slice(1)+' meeting?',showCancelButton:true});if(!x.isConfirmed)return;}const fd=new FormData();fd.append('csrf_token','<?= e(csrfToken()) ?>');fd.append('meeting_id','<?= $id ?>');fd.append('action',this.dataset.action);fd.append('notes',notes);const r=await fetch('ajax_transition.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:fd}).then(x=>x.json());if(r.success)location.reload();else Swal.fire('Meeting Workflow',r.message,'error');});
 if(docForm)docForm.onsubmit=async e=>{e.preventDefault();const r=await fetch('ajax_upload_document.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:new FormData(docForm)}).then(x=>x.json());if(r.success)location.reload();else Swal.fire('Upload Error',r.message,'error');};
});
</script>
<?php endif; ?>
<?php include __DIR__.'/../../layouts/footer.php'; ?>
