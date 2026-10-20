<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/orlms_lifecycle_helpers.php';
requirePermission('orlms.publications.view');

$pdo=db();$id=(int)($_GET['id']??0);$p=orlmsGetPublication($pdo,$id);
if(!$p){setFlash('warning','Publication not found.');redirect(APP_URL.'/modules/publications/index.php');}

$recipients=$pdo->prepare(
 "SELECT r.*,o.name office_name,u.full_name user_name
  FROM orlms_dissemination_recipients r
  LEFT JOIN offices o ON o.id=r.office_id
  LEFT JOIN users u ON u.id=r.user_id
  WHERE r.publication_id=:id ORDER BY r.created_at,r.id"
);$recipients->execute([':id'=>$id]);$recipientRows=$recipients->fetchAll();

$docs=$pdo->prepare('SELECT * FROM orlms_publication_documents WHERE publication_id=:id ORDER BY uploaded_at DESC,id DESC');
$docs->execute([':id'=>$id]);$documents=$docs->fetchAll();

$history=$pdo->prepare(
 "SELECT h.*,u.full_name changed_name FROM orlms_publication_history h
  LEFT JOIN users u ON u.id=h.changed_by
  WHERE h.publication_id=:id ORDER BY h.created_at DESC,h.id DESC"
);$history->execute([':id'=>$id]);$historyRows=$history->fetchAll();

$offices=$pdo->query("SELECT id,name FROM offices WHERE status='Active' ORDER BY name")->fetchAll();
$users=$pdo->query(
 "SELECT u.id,u.full_name,u.email FROM users u
  WHERE u.status='Active' AND u.deleted_at IS NULL ORDER BY u.full_name"
)->fetchAll();

$pageTitle=$p['publication_reference'];$activeMenu='publications';
$extraCss=[APP_URL.'/assets/css/orlms-lifecycle-ops.css'];
include __DIR__.'/../../layouts/header.php';
?>
<div class="orlms-app-layout"><?php include __DIR__.'/../../layouts/sidebar.php'; ?><main class="orlms-main-content">
<div class="orl-head"><div><a href="index.php" class="small text-decoration-none"><i class="bi bi-arrow-left"></i> Publication Register</a><div class="orl-eyebrow mt-2"><?= e($p['publication_reference']) ?></div><h1><?= e($p['official_number']?:$p['reference_number']) ?></h1><p><?= e($p['title'].' · '.$p['publication_channel'].' · '.$p['release_classification']) ?></p></div><div class="d-flex gap-2 flex-wrap"><span class="orl-status <?= $p['publication_status']==='Published'?'good':($p['publication_status']==='Withdrawn'?'bad':'') ?>"><?= e($p['publication_status']) ?></span><a class="btn btn-outline-secondary btn-sm" target="_blank" href="print.php?id=<?= $id ?>"><i class="bi bi-printer"></i></a><?php if($p['publication_status']==='Published'): ?><a class="btn btn-primary btn-sm" href="<?= e(APP_URL.'/modules/implementation/index.php?item_id='.$p['legislative_item_id']) ?>"><i class="bi bi-activity"></i> Implementation</a><?php endif; ?></div></div>

<div class="row g-3">
<div class="col-xl-8">
<div class="card orl-card mb-3"><div class="card-header">Official Publication</div><div class="card-body"><div class="orl-grid mb-3"><div><small>Legislative Item</small><strong><?= e($p['reference_number']) ?></strong></div><div><small>Official Number</small><strong><?= e($p['official_number']?:'—') ?></strong></div><div><small>Channel</small><strong><?= e($p['publication_channel']) ?></strong></div><div><small>Publication Date</small><strong><?= $p['publication_date']?formatDateTime($p['publication_date']):'Not scheduled' ?></strong></div><div><small>Classification</small><strong><?= e($p['release_classification']) ?></strong></div><div><small>Public URL</small><strong><?= e($p['public_url']?:'—') ?></strong></div></div><?php if($p['publication_text']): ?><div class="orl-alert"><?= nl2br(e($p['publication_text'])) ?></div><?php endif; ?><?php if($p['remarks']): ?><p class="small mt-3 mb-0"><?= nl2br(e($p['remarks'])) ?></p><?php endif; ?></div></div>

<div class="card orl-card mb-3"><div class="card-header d-flex justify-content-between align-items-center"><span>Dissemination Recipients</span><button class="btn btn-sm btn-primary" id="btnRecipient"><i class="bi bi-plus-lg me-1"></i> Add Recipient</button></div><div class="table-responsive"><table class="table orl-table mb-0"><thead><tr><th>Recipient</th><th>Type</th><th>Channel</th><th>Sent</th><th>Acknowledged</th><th>Status</th><th class="text-end">Control</th></tr></thead><tbody>
<?php if(!$recipientRows): ?><tr><td colspan="7" class="text-center text-muted py-4">No dissemination recipients yet.</td></tr><?php endif; ?>
<?php foreach($recipientRows as $r): ?><tr><td><strong><?= e($r['office_name']?:$r['user_name']?:$r['recipient_name']?:'Unnamed') ?></strong><div class="small text-muted"><?= e($r['recipient_email']?:'') ?></div></td><td><?= e($r['recipient_type']) ?></td><td><?= e($r['delivery_channel']) ?></td><td><?= $r['sent_at']?formatDateTime($r['sent_at']):'—' ?></td><td><?= $r['acknowledged_at']?formatDateTime($r['acknowledged_at']):'—' ?></td><td><?= e($r['delivery_status']) ?></td><td class="text-end"><?php if(hasPermission('orlms.publications.manage')): ?><div class="btn-group btn-group-sm"><?php if($r['delivery_status']==='Pending'): ?><button class="btn btn-outline-primary delivery-status" data-id="<?= (int)$r['id'] ?>" data-status="Sent">Mark Sent</button><?php endif; ?><?php if($r['delivery_status']==='Sent'): ?><button class="btn btn-outline-success delivery-status" data-id="<?= (int)$r['id'] ?>" data-status="Acknowledged">Acknowledge</button><button class="btn btn-outline-danger delivery-status" data-id="<?= (int)$r['id'] ?>" data-status="Failed">Failed</button><?php endif; ?></div><?php endif; ?></td></tr><?php endforeach; ?>
</tbody></table></div></div>

<div class="card orl-card"><div class="card-header">Publication Documents</div><div class="card-body"><div class="list-group mb-3"><?php if(!$documents): ?><div class="list-group-item text-muted">No publication documents uploaded.</div><?php endif; ?><?php foreach($documents as $d): ?><a class="list-group-item list-group-item-action" target="_blank" href="<?= e(UPLOAD_URL.$d['file_path']) ?>"><strong><?= e($d['file_name']) ?></strong><div class="small text-muted"><?= e($d['document_type'].' · '.$d['visibility']) ?> · <?= formatDateTime($d['uploaded_at']) ?></div></a><?php endforeach; ?></div><?php if(hasPermission('orlms.publications.manage')): ?><form id="docForm" class="row g-2" enctype="multipart/form-data"><?= csrfField() ?><input type="hidden" name="publication_id" value="<?= $id ?>"><div class="col-md-5"><input type="file" class="form-control form-control-sm" name="document" required></div><div class="col-md-3"><input class="form-control form-control-sm" name="document_type" value="Publication Document"></div><div class="col-md-2"><input class="form-control form-control-sm" name="description" placeholder="Description"></div><div class="col-md-2"><select class="form-select form-select-sm" name="visibility"><option <?= $p['release_classification']==='Public'?'selected':'' ?>>Public</option><option>Internal</option><option>Restricted</option></select></div><div class="col-12"><button class="btn btn-outline-primary btn-sm">Upload Publication Document</button></div></form><?php endif; ?></div></div>
</div>

<div class="col-xl-4">
<?php if(hasPermission('orlms.publications.manage')): ?><div class="card orl-card mb-3"><div class="card-header">Publication Workflow</div><div class="card-body d-grid gap-2"><?php if($p['publication_status']==='Draft'): ?><button class="btn btn-outline-primary publication-action" data-action="schedule">Schedule Publication</button><button class="btn btn-success publication-action" data-action="publish">Publish Now</button><?php elseif($p['publication_status']==='Scheduled'): ?><button class="btn btn-success publication-action" data-action="publish">Release Publication</button><?php elseif($p['publication_status']==='Published'): ?><button class="btn btn-outline-danger publication-action" data-action="withdraw">Withdraw Publication</button><?php endif; ?></div></div><?php endif; ?>

<div class="card orl-card"><div class="card-header">Publication History</div><div class="card-body orl-history"><?php foreach($historyRows as $h): ?><div><strong><?= e($h['action']) ?> · <?= e($h['changed_name']?:'System') ?></strong><small><?= e(($h['previous_status']?:'—').' → '.($h['new_status']?:'—')) ?><?= $h['details']?'<br>'.e($h['details']):'' ?><br><?= formatDateTime($h['created_at']) ?></small></div><?php endforeach; ?></div></div>
</div>
</div>
</main></div>

<div class="modal fade" id="recipientModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" style="max-width: 580px;">
    <div class="modal-content shadow-lg border-0 rounded-3 overflow-hidden">
      <form id="recipientForm">
        <?= csrfField() ?>
        <input type="hidden" name="publication_id" value="<?= $id ?>">
        <input type="hidden" name="recipient_id" value="0">
        <input type="hidden" name="mode" value="save">
        <div class="modal-header py-2 px-3 border-0" style="background: linear-gradient(135deg, #0f172a, #1e293b); color: #fff;">
          <div class="d-flex align-items-center gap-2">
            <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 28px; height: 28px; background: rgba(59,130,246,0.25); color: #60a5fa; font-size: 12px;">
              <i class="bi bi-person-plus"></i>
            </div>
            <h6 class="modal-title fw-bold m-0" style="font-size: 0.95rem;">Dissemination Recipient</h6>
          </div>
          <button class="btn-close btn-close-white" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body p-3">
          <div class="row g-2">
            <div class="col-md-6">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Recipient Type</label>
              <select class="form-select form-select-sm" name="recipient_type" id="r_type">
                <option>Office</option>
                <option>User</option>
                <option>External</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Delivery Channel</label>
              <select class="form-select form-select-sm" name="delivery_channel">
                <option>System Notification</option>
                <option>Email</option>
                <option>Official Memo</option>
                <option>Website</option>
                <option>Physical Copy</option>
                <option>Other</option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Office</label>
              <select class="form-select form-select-sm" name="office_id">
                <option value="">Select office</option>
                <?php foreach($offices as $o): ?>
                  <option value="<?= (int)$o['id'] ?>"><?= e($o['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">User</label>
              <select class="form-select form-select-sm" name="user_id">
                <option value="">Select user</option>
                <?php foreach($users as $u): ?>
                  <option value="<?= (int)$u['id'] ?>"><?= e($u['full_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">External Recipient Name</label>
              <input class="form-control form-control-sm" name="recipient_name">
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Recipient Email</label>
              <input type="email" class="form-control form-control-sm" name="recipient_email">
            </div>
            <div class="col-12">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Delivery Notes</label>
              <textarea class="form-control form-control-sm" name="delivery_notes" rows="2" style="resize: vertical; min-height: 50px;"></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer py-2 px-3 bg-light border-top">
          <button class="btn btn-sm btn-outline-secondary px-3" type="button" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-sm btn-primary px-4 fw-semibold"><i class="bi bi-plus-lg me-1"></i>Add Recipient</button>
        </div>
      </form>
    </div>
  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded',function(){
 const recipientForm=document.getElementById('recipientForm'),docForm=document.getElementById('docForm');
 const modalEl=document.getElementById('recipientModal');
 const modal=modalEl?new bootstrap.Modal(modalEl):null;
 const btnRec=document.getElementById('btnRecipient');
 if(btnRec&&modal&&recipientForm){btnRec.onclick=()=>{recipientForm.reset();modal.show();};}
 if(recipientForm){
  recipientForm.onsubmit=async e=>{e.preventDefault();const r=await fetch('ajax_recipient.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:new FormData(recipientForm)}).then(x=>x.json());if(r.success)location.reload();else Swal.fire('Recipient Error',r.message,'error');};
 }
 document.querySelectorAll('.delivery-status').forEach(b=>b.onclick=async function(){const p=await Swal.fire({title:'Set delivery status to '+this.dataset.status+'?',input:'textarea',inputLabel:'Optional note',showCancelButton:true});if(!p.isConfirmed)return;const fd=new FormData();fd.append('csrf_token','<?= e(csrfToken()) ?>');fd.append('publication_id','<?= $id ?>');fd.append('recipient_id',this.dataset.id);fd.append('mode','status');fd.append('delivery_status',this.dataset.status);fd.append('delivery_notes',p.value||'');const r=await fetch('ajax_recipient.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:fd}).then(x=>x.json());if(r.success)location.reload();else Swal.fire('Delivery Error',r.message,'error');});
 document.querySelectorAll('.publication-action').forEach(b=>b.onclick=async function(){let reason='';if(this.dataset.action==='withdraw'){const p=await Swal.fire({title:'Withdraw publication?',input:'textarea',inputLabel:'Reason',showCancelButton:true,inputValidator:v=>!v?'Reason is required':undefined});if(!p.isConfirmed)return;reason=p.value;}else{const p=await Swal.fire({title:'Confirm '+this.dataset.action+'?',showCancelButton:true});if(!p.isConfirmed)return;}const fd=new FormData();fd.append('csrf_token','<?= e(csrfToken()) ?>');fd.append('publication_id','<?= $id ?>');fd.append('mode','transition');fd.append('action',this.dataset.action);fd.append('reason',reason);const r=await fetch('ajax_publish.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:fd}).then(x=>x.json());if(r.success)location.reload();else Swal.fire('Publication Error',r.message,'error');});
 if(docForm)docForm.onsubmit=async e=>{e.preventDefault();const r=await fetch('ajax_upload_document.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:new FormData(docForm)}).then(x=>x.json());if(r.success)location.reload();else Swal.fire('Upload Error',r.message,'error');};
});
</script>
<?php include __DIR__.'/../../layouts/footer.php'; ?>
