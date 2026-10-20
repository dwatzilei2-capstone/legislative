<?php
declare(strict_types=1);
require_once __DIR__.'/../../includes/lacms_sync_helpers.php';
requireLacmsPermission('lacms.sync.view');
$pdo=db();$id=(int)($_GET['id']??0);$s=lacmsSyncContext($pdo,$id);if(!$s){setFlash('warning','Coordination record not found.');redirect(appUrl('modules/synchronization/index.php'));}
$a=$pdo->prepare("SELECT x.*,u.full_name responsible_name,o.name office_name,cu.full_name completed_name FROM lacms_sync_actions x LEFT JOIN users u ON u.id=x.responsible_user_id LEFT JOIN offices o ON o.id=x.responsible_office_id LEFT JOIN users cu ON cu.id=x.completed_by WHERE x.sync_record_id=:id ORDER BY FIELD(x.status,'In Progress','Pending','Completed','Cancelled'),x.due_datetime,x.id");$a->execute([':id'=>$id]);$actions=$a->fetchAll();
$h=$pdo->prepare("SELECT h.*,u.full_name changed_name FROM lacms_sync_history h LEFT JOIN users u ON u.id=h.changed_by WHERE h.sync_record_id=:id ORDER BY h.created_at DESC,h.id DESC");$h->execute([':id'=>$id]);$history=$h->fetchAll();
$d=$pdo->prepare("SELECT d.*,u.full_name uploaded_name FROM lacms_sync_documents d LEFT JOIN users u ON u.id=d.uploaded_by WHERE d.sync_record_id=:id ORDER BY d.uploaded_at DESC,d.id DESC");$d->execute([':id'=>$id]);$documents=$d->fetchAll();
$users=$pdo->query("SELECT id,full_name FROM users WHERE status='Active' AND deleted_at IS NULL ORDER BY full_name")->fetchAll();$offices=$pdo->query("SELECT id,name FROM offices WHERE status='Active' ORDER BY name")->fetchAll();
$pageTitle=$s['sync_reference'];$activeMenu='synchronization';$extraCss=[appUrl('assets/css/lacms-operational.css')];include __DIR__.'/../../layouts/header.php';
?>
<div class="lacms-app-wrapper"><?php include __DIR__.'/../../layouts/sidebar.php'; ?><main class="lacms-main-content"><div class="lo-head"><div><a href="index.php" class="small text-decoration-none"><i class="bi bi-arrow-left"></i> Synchronization Registry</a><div class="lo-eyebrow mt-2"><?= e($s['sync_reference'].' · '.$s['sync_type']) ?></div><h1><?= e($s['title']) ?></h1><p><?= e($s['source_office_name']?:'Executive office not assigned') ?> ↔ <?= e($s['target_committee_name']?:'Legislative committee not assigned') ?></p></div><div class="d-flex gap-2"><span class="lo-status <?= in_array($s['status'],['Aligned','Resolved','Closed'],true)?'good':($s['status']==='Cancelled'?'bad':'warn') ?>"><?= e($s['status']) ?></span><a target="_blank" class="btn btn-outline-secondary btn-sm" href="print.php?id=<?= $id ?>"><i class="bi bi-printer"></i></a></div></div>
<div class="row g-3"><div class="col-xl-8"><div class="card lo-card mb-3"><div class="card-header">Coordination Context</div><div class="card-body"><div class="lo-grid"><div><small>Legislative Item</small><strong><?= e($s['legislative_reference']?:'—') ?></strong></div><div><small>Agenda</small><strong><?= e($s['agenda_reference']?:'—') ?></strong></div><div><small>Calendar Event</small><strong><?= e($s['event_reference']?:'—') ?></strong></div><div><small>Priority</small><strong><?= e($s['priority_level']) ?></strong></div></div><div class="row g-3 mt-1"><div class="col-md-6"><div class="lo-alert h-100"><strong>Executive Position</strong><br><?= nl2br(e($s['executive_position']?:'Not yet recorded.')) ?></div></div><div class="col-md-6"><div class="lo-alert h-100"><strong>Legislative Position</strong><br><?= nl2br(e($s['legislative_position']?:'Not yet recorded.')) ?></div></div></div><?php if($s['resolution_notes']): ?><div class="alert alert-info mt-3 mb-0"><strong>Coordination / Resolution Notes</strong><br><?= nl2br(e($s['resolution_notes'])) ?></div><?php endif; ?></div></div>
<div class="card lo-card mb-3"><div class="card-header d-flex justify-content-between"><span>Coordination Actions</span><?php if(lacmsHasPermission('lacms.sync.manage')&&!in_array($s['status'],['Closed','Cancelled'],true)): ?><button class="btn btn-sm btn-primary" id="btnAction">Add Action</button><?php endif; ?></div><div class="card-body d-grid gap-2"><?php if(!$actions): ?><div class="text-muted small">No action items recorded.</div><?php endif; ?><?php foreach($actions as $x): ?><div class="lo-participant"><div><strong><?= e($x['action_title']) ?></strong><small><?= e($x['priority_level'].' · '.$x['status']) ?><?= $x['due_datetime']?' · Due '.formatDateTime($x['due_datetime']):'' ?><br><?= e($x['responsible_name']?:$x['office_name']?:'Unassigned') ?><?php if($x['action_description']): ?><br><?= e(mb_strimwidth($x['action_description'],0,160,'…')) ?><?php endif; ?></small></div></div><?php endforeach; ?></div></div>
<div class="card lo-card mb-3"><div class="card-header">Documents</div><div class="card-body"><div class="list-group mb-3"><?php if(!$documents): ?><div class="list-group-item text-muted">No coordination documents.</div><?php endif; ?><?php foreach($documents as $x): ?><a class="list-group-item list-group-item-action" target="_blank" href="<?= e(UPLOAD_URL.$x['file_path']) ?>"><strong><?= e($x['file_name']) ?></strong><div class="small text-muted"><?= e($x['document_type'].' · '.$x['visibility']) ?> · <?= formatDateTime($x['uploaded_at']) ?></div></a><?php endforeach; ?></div><?php if(lacmsHasPermission('lacms.sync.manage')): ?><form id="docForm" class="row g-2" enctype="multipart/form-data"><?= csrfField() ?><input type="hidden" name="sync_record_id" value="<?= $id ?>"><div class="col-md-6"><input type="file" class="form-control form-control-sm" name="document" required></div><div class="col-md-3"><input class="form-control form-control-sm" name="document_type" value="Coordination Document"></div><div class="col-md-3"><button class="btn btn-outline-primary btn-sm w-100">Upload</button></div></form><?php endif; ?></div></div></div>
<div class="col-xl-4"><?php if(lacmsHasPermission('lacms.sync.manage')): ?><div class="card lo-card mb-3"><div class="card-header">Synchronization Workflow</div><div class="card-body d-grid gap-2"><?php if($s['status']==='Draft'): ?><button class="btn btn-primary sync-action" data-action="coordinate">Start Coordination</button><?php endif; ?><?php if(!in_array($s['status'],['Closed','Cancelled'],true)): ?><button class="btn btn-outline-primary sync-action" data-action="await_executive">Await Executive</button><button class="btn btn-outline-primary sync-action" data-action="await_legislative">Await Legislative</button><button class="btn btn-warning sync-action" data-action="align">Mark Aligned</button><button class="btn btn-success sync-action" data-action="resolve">Resolve</button><?php if(in_array($s['status'],['Aligned','Resolved'],true)): ?><button class="btn btn-dark sync-action" data-action="close">Close Record</button><?php endif; ?><button class="btn btn-outline-danger sync-action" data-action="cancel">Cancel</button><?php endif; ?></div></div><?php endif; ?><div class="card lo-card"><div class="card-header">Synchronization History</div><div class="card-body lo-history"><?php if(!$history): ?><div class="text-muted small">No history.</div><?php endif; ?><?php foreach($history as $x): ?><div><strong><?= e($x['action'].' · '.($x['changed_name']?:'System')) ?></strong><small><?= e(($x['previous_status']?:'—').' → '.($x['new_status']?:'—')) ?><?php if($x['details']): ?><br><?= e($x['details']) ?><?php endif; ?><br><?= formatDateTime($x['created_at']) ?></small></div><?php endforeach; ?></div></div></div></div></main></div>
<?php if(lacmsHasPermission('lacms.sync.manage')): ?>
<div class="modal fade" id="actionModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" style="max-width: 580px;">
    <div class="modal-content shadow-lg border-0 rounded-3 overflow-hidden">
      <form id="actionForm">
        <?= csrfField() ?>
        <input type="hidden" name="sync_record_id" value="<?= $id ?>">
        <input type="hidden" name="action_id" value="0">
        <input type="hidden" name="mode" value="save">
        <div class="modal-header py-2 px-3 border-0" style="background: linear-gradient(135deg, #0f172a, #1e293b); color: #fff;">
          <div class="d-flex align-items-center gap-2">
            <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 28px; height: 28px; background: rgba(59,130,246,0.25); color: #60a5fa; font-size: 12px;">
              <i class="bi bi-list-task"></i>
            </div>
            <h6 class="modal-title fw-bold m-0" style="font-size: 0.95rem;">Coordination Action</h6>
          </div>
          <button class="btn-close btn-close-white" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body p-3">
          <div class="row g-2">
            <div class="col-12">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Action Title *</label>
              <input class="form-control form-control-sm" name="action_title" required>
            </div>
            <div class="col-12">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Description</label>
              <textarea class="form-control form-control-sm" name="action_description" rows="2" style="resize: vertical; min-height: 50px;"></textarea>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Responsible User</label>
              <select class="form-select form-select-sm" name="responsible_user_id">
                <option value="">None</option>
                <?php foreach($users as $u): ?>
                  <option value="<?= (int)$u['id'] ?>"><?= e($u['full_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Responsible Office</label>
              <select class="form-select form-select-sm" name="responsible_office_id">
                <option value="">None</option>
                <?php foreach($offices as $o): ?>
                  <option value="<?= (int)$o['id'] ?>"><?= e($o['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Due Date & Time</label>
              <input type="datetime-local" class="form-control form-control-sm" name="due_datetime">
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Priority</label>
              <select class="form-select form-select-sm" name="priority_level">
                <option>Low</option>
                <option selected>Normal</option>
                <option>High</option>
                <option>Urgent</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Status</label>
              <select class="form-select form-select-sm" name="status">
                <?php foreach(lacmsSyncActionStatuses() as $x): ?>
                  <option><?= e($x) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
        </div>
        <div class="modal-footer py-2 px-3 bg-light border-top">
          <button class="btn btn-sm btn-outline-secondary px-3" type="button" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-sm btn-primary px-4 fw-semibold"><i class="bi bi-check2 me-1"></i>Save Action</button>
        </div>
      </form>
    </div>
  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded',()=>{
 const af=document.getElementById('actionForm');
 const amEl=document.getElementById('actionModal');
 const am=amEl?new bootstrap.Modal(amEl):null;
 document.getElementById('btnAction')?.addEventListener('click',()=>{
   if(af)af.reset();
   if(am)am.show();
 });
 if(af) af.onsubmit=async e=>{
   e.preventDefault();
   const r=await fetch('ajax_action.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:new FormData(af)}).then(x=>x.json());
   if(r.success)location.reload();
   else Swal.fire('Action Error',r.message,'error');
 };
 document.querySelectorAll('.sync-action').forEach(b=>b.onclick=async function(){
   let notes='';
   if(['resolve','close','cancel'].includes(this.dataset.action)){
     const x=await Swal.fire({title:'Update coordination status?',input:'textarea',inputLabel:'Notes / reason',showCancelButton:true,inputValidator:v=>!v?'Notes are required':undefined});
     if(!x.isConfirmed)return;
     notes=x.value;
   } else {
     const x=await Swal.fire({title:'Update coordination status?',showCancelButton:true});
     if(!x.isConfirmed)return;
   }
   const fd=new FormData();
   fd.append('csrf_token','<?= e(csrfToken()) ?>');
   fd.append('sync_record_id','<?= $id ?>');
   fd.append('action',this.dataset.action);
   fd.append('notes',notes);
   const r=await fetch('ajax_transition.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:fd}).then(x=>x.json());
   if(r.success)location.reload();
   else Swal.fire('Workflow Error',r.message,'error');
 });
 document.getElementById('docForm')?.addEventListener('submit',async e=>{
   e.preventDefault();
   const r=await fetch('ajax_upload_document.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:new FormData(e.currentTarget)}).then(x=>x.json());
   if(r.success)location.reload();
   else Swal.fire('Upload Error',r.message,'error');
 });
});
</script>
<?php endif; ?>
<?php include __DIR__.'/../../layouts/footer.php'; ?>
