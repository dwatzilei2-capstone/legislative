<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/orlms_amendment_helpers.php';
requirePermission('orlms.amendments.view');

$pdo=db();$id=(int)($_GET['id']??0);
$a=orlmsGetAmendment($pdo,$id);
if(!$a){setFlash('warning','Amendment record not found.');redirect(APP_URL.'/modules/amendments/index.php');}

$changes=orlmsAmendmentChanges($pdo,$id);
$documents=orlmsAmendmentDocuments($pdo,$id);
$history=orlmsAmendmentHistoryRows($pdo,$id);
$revisions=orlmsRevisionHistoryRows($pdo,(int)$a['legislative_item_id']);
$currentRevision=orlmsCurrentRevision($pdo,(int)$a['legislative_item_id']);
$sourceRevision=orlmsRevisionByVersion($pdo,(int)$a['legislative_item_id'],(int)$a['source_revision_version']);

$relatedItems=$pdo->query(
 "SELECT li.id,li.reference_number,li.title
  FROM legislative_items li
  JOIN legislative_item_types lit ON lit.id=li.item_type_id
  WHERE li.deleted_at IS NULL AND lit.code IN ('ordinance','resolution')
  ORDER BY li.reference_number"
)->fetchAll();

$pageTitle=$a['amendment_reference'];$activeMenu='amendments';
$extraCss=[APP_URL.'/assets/css/orlms-final-ops.css'];
include __DIR__.'/../../layouts/header.php';
?>
<div class="orlms-app-layout"><?php include __DIR__.'/../../layouts/sidebar.php'; ?><main class="orlms-main-content">

<div class="of-head"><div><a href="index.php" class="small text-decoration-none"><i class="bi bi-arrow-left"></i> Amendment Registry</a><div class="of-eyebrow mt-2"><?= e($a['amendment_reference']) ?> · source v<?= (int)$a['source_revision_version'] ?></div><h1><?= e($a['title']) ?></h1><p><?= e($a['official_number']?:$a['reference_number']) ?> · <?= e($a['legislative_title']) ?> · <?= e($a['amendment_type']) ?></p></div><div class="d-flex gap-2 flex-wrap"><span class="of-status <?= $a['status']==='Applied'?'good':(in_array($a['status'],['Rejected','Withdrawn'],true)?'bad':(in_array($a['status'],['Approved','Under Review'],true)?'warn':'')) ?>"><?= e($a['status']) ?></span><a class="btn btn-outline-secondary btn-sm" target="_blank" href="print.php?id=<?= $id ?>"><i class="bi bi-printer"></i></a></div></div>

<div class="row g-3 mb-3"><?php foreach([
 ['Source Revision',(int)$a['source_revision_version'],'bi-file-earmark-lock'],
 ['Current Revision',(int)($currentRevision['version_number']??0),'bi-clock-history'],
 ['Change Entries',count($changes),'bi-file-earmark-diff'],
 ['Documents',count($documents),'bi-paperclip'],
] as [$l,$v,$i]): ?><div class="col-6 col-lg-3"><div class="of-stat"><i class="bi <?= e($i) ?>"></i><div><strong><?= $v ?></strong><small><?= e($l) ?></small></div></div></div><?php endforeach; ?></div>

<?php if((int)$a['source_revision_version']!==(int)($currentRevision['version_number']??0) && $a['status']!=='Applied'): ?><div class="alert alert-danger"><strong>Stale amendment detected.</strong> This amendment was created from revision v<?= (int)$a['source_revision_version'] ?>, but the current official revision is v<?= (int)($currentRevision['version_number']??0) ?>. Approval/application is blocked to protect document integrity.</div><?php endif; ?>
<?php if($a['review_notes']): ?><div class="alert alert-warning"><strong>Review Notes:</strong> <?= nl2br(e($a['review_notes'])) ?></div><?php endif; ?>

<div class="row g-3">
<div class="col-xl-8">
<div class="card of-card mb-3"><div class="card-header d-flex justify-content-between align-items-center"><span>Amendment Details</span><?php if(in_array($a['status'],['Draft','Revision Required'],true)): ?><button class="btn btn-sm btn-primary" id="btnEditAmendment"><i class="bi bi-pencil-square me-1"></i> Edit Draft</button><?php endif; ?></div><div class="card-body">
<div class="of-grid mb-3"><div><small>Original Item</small><strong><?= e($a['reference_number']) ?></strong></div><div><small>Official Number</small><strong><?= e($a['official_number']?:'—') ?></strong></div><div><small>Amendment Type</small><strong><?= e($a['amendment_type']) ?></strong></div><div><small>Source Status</small><strong><?= e($a['source_status']?:'—') ?></strong></div><div><small>Relationship</small><strong><?= e(($a['relationship_type']?:'—').($a['related_reference']?' · '.$a['related_reference']:'')) ?></strong></div><div><small>Effective Date</small><strong><?= $a['effective_date']?formatDate($a['effective_date']):'—' ?></strong></div></div>
<?php if($a['summary']): ?><p class="small"><strong>Summary</strong><br><?= nl2br(e($a['summary'])) ?></p><?php endif; ?>
<?php if($a['rationale']): ?><div class="of-alert"><strong>Rationale</strong><br><?= nl2br(e($a['rationale'])) ?></div><?php endif; ?>
</div></div>

<div class="card of-card mb-3"><div class="card-header">Source vs Proposed Revised Text</div><div class="card-body"><div class="of-compare"><div><h6>Official Source Revision v<?= (int)$a['source_revision_version'] ?></h6><div class="of-codebody"><?= e(($sourceRevision['body_snapshot']??$a['current_body'])?:'No source text available.') ?></div></div><div><h6>Proposed Revised Text</h6><div class="of-codebody"><?= e($a['revised_body_text']?:'No complete revised text encoded yet.') ?></div></div></div></div></div>

<div class="card of-card mb-3"><div class="card-header d-flex justify-content-between align-items-center"><span>Affected Sections / Provision Changes</span><?php if(in_array($a['status'],['Draft','Revision Required'],true)): ?><button class="btn btn-sm btn-primary" id="btnAddChange"><i class="bi bi-plus-lg me-1"></i> Add Change</button><?php endif; ?></div><div class="card-body d-grid gap-2">
<?php if(!$changes): ?><div class="text-muted small">No structured section changes recorded.</div><?php endif; ?>
<?php foreach($changes as $c): ?><div class="of-change"><div class="d-flex justify-content-between gap-2"><strong><?= e(($c['section_reference']?:'General Provision').' · '.$c['change_type']) ?></strong><?php if(hasPermission('orlms.amendments.manage')&&in_array($a['status'],['Draft','Revision Required'],true)): ?><div class="btn-group btn-group-sm"><button class="btn btn-outline-primary edit-change" data-row='<?= e(json_encode($c,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)) ?>'>Edit</button><button class="btn btn-outline-danger delete-change" data-id="<?= (int)$c['id'] ?>">Delete</button></div><?php endif; ?></div><?php if($c['previous_text']): ?><p><strong>Previous:</strong> <?= nl2br(e($c['previous_text'])) ?></p><?php endif; ?><?php if($c['new_text']): ?><p><strong>Revised:</strong> <?= nl2br(e($c['new_text'])) ?></p><?php endif; ?><?php if($c['rationale']): ?><small><strong>Reason:</strong> <?= e($c['rationale']) ?></small><?php endif; ?></div><?php endforeach; ?>
</div></div>

<div class="card of-card mb-3"><div class="card-header">Amendment Documents</div><div class="card-body"><div class="list-group mb-3"><?php if(!$documents): ?><div class="list-group-item text-muted">No amendment evidence uploaded.</div><?php endif; ?><?php foreach($documents as $d): ?><a target="_blank" class="list-group-item list-group-item-action" href="<?= e(UPLOAD_URL.$d['file_path']) ?>"><strong><?= e($d['file_name']) ?></strong><div class="small text-muted"><?= e($d['document_type'].' · '.$d['visibility']) ?> · <?= formatDateTime($d['uploaded_at']) ?></div></a><?php endforeach; ?></div><?php if(hasPermission('orlms.amendments.manage')): ?><form id="docForm" class="row g-2" enctype="multipart/form-data"><?= csrfField() ?><input type="hidden" name="amendment_id" value="<?= $id ?>"><div class="col-md-5"><input type="file" class="form-control form-control-sm" name="document" required></div><div class="col-md-3"><input class="form-control form-control-sm" name="document_type" value="Amendment Evidence"></div><div class="col-md-2"><input class="form-control form-control-sm" name="description" placeholder="Description"></div><div class="col-md-2"><select class="form-select form-select-sm" name="visibility"><option>Internal</option><option>Restricted</option><option>Public</option></select></div><div class="col-12"><button class="btn btn-outline-primary btn-sm">Upload Amendment Document</button></div></form><?php endif; ?></div></div>

<div class="card of-card"><div class="card-header">Official Revision History</div><div class="table-responsive"><table class="table of-table mb-0"><thead><tr><th>Version</th><th>Source Amendment</th><th>Change Summary</th><th>Created</th><th>Current</th></tr></thead><tbody><?php foreach($revisions as $r): ?><tr><td><strong>v<?= (int)$r['version_number'] ?></strong></td><td><?= e($r['amendment_reference']?:'Baseline / Existing') ?></td><td><?= e($r['change_summary']?:'—') ?></td><td><?= formatDateTime($r['created_at']) ?> · <?= e($r['created_name']?:'System') ?></td><td><?= $r['is_current']?'<span class="badge text-bg-success">Current</span>':'—' ?></td></tr><?php endforeach; ?></tbody></table></div></div>
</div>

<div class="col-xl-4">
<?php if(hasPermission('orlms.amendments.manage')): ?><div class="card of-card mb-3"><div class="card-header">Amendment Workflow</div><div class="card-body d-grid gap-2">
<?php if(in_array($a['status'],['Draft','Revision Required'],true)): ?><button class="btn btn-primary amendment-action" data-action="submit"><i class="bi bi-send"></i> Submit for Review</button><button class="btn btn-outline-danger amendment-action" data-action="withdraw">Withdraw</button><?php elseif($a['status']==='Under Review'): ?><button class="btn btn-warning amendment-action" data-action="return">Return for Revision</button><button class="btn btn-success amendment-action" data-action="approve">Approve Amendment</button><button class="btn btn-outline-danger amendment-action" data-action="reject">Reject Amendment</button><?php elseif($a['status']==='Approved'): ?><button class="btn btn-success amendment-action" data-action="apply"><i class="bi bi-check2-circle"></i> Apply as Official Revision</button><?php endif; ?>
</div></div><?php endif; ?>

<div class="card of-card mb-3"><div class="card-header">Review / Approval</div><div class="card-body"><div class="of-grid"><div><small>Proposed By</small><strong><?= e($a['proposed_name']?:'—') ?></strong></div><div><small>Proposed At</small><strong><?= $a['proposed_at']?formatDateTime($a['proposed_at']):'—' ?></strong></div><div><small>Reviewed By</small><strong><?= e($a['reviewed_name']?:'—') ?></strong></div><div><small>Reviewed At</small><strong><?= $a['reviewed_at']?formatDateTime($a['reviewed_at']):'—' ?></strong></div><div><small>Approved By</small><strong><?= e($a['approved_name']?:'—') ?></strong></div><div><small>Applied By</small><strong><?= e($a['applied_name']?:'—') ?></strong></div></div><?php if($a['approval_remarks']): ?><div class="of-alert mt-3"><?= nl2br(e($a['approval_remarks'])) ?></div><?php endif; ?></div></div>

<div class="card of-card"><div class="card-header">Amendment Audit History</div><div class="card-body of-history"><?php foreach($history as $h): ?><div><strong><?= e($h['action']) ?> · <?= e($h['changed_name']?:'System') ?></strong><small><?= e(($h['previous_status']?:'—').' → '.($h['new_status']?:'—')) ?><?= $h['details']?'<br>'.e($h['details']):'' ?><br><?= formatDateTime($h['created_at']) ?></small></div><?php endforeach; ?></div></div>
</div>
</div>
</main></div>

<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" style="max-width: 680px;">
    <div class="modal-content shadow-lg border-0 rounded-3 overflow-hidden">
      <form id="editForm">
        <?= csrfField() ?>
        <input type="hidden" name="id" value="<?= $id ?>">
        <input type="hidden" name="legislative_item_id" value="<?= (int)$a['legislative_item_id'] ?>">
        <div class="modal-header py-2 px-3 border-0" style="background: linear-gradient(135deg, #0f172a, #1e293b); color: #fff;">
          <div class="d-flex align-items-center gap-2">
            <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 28px; height: 28px; background: rgba(59,130,246,0.25); color: #60a5fa; font-size: 12px;">
              <i class="bi bi-pencil-square"></i>
            </div>
            <h6 class="modal-title fw-bold m-0" style="font-size: 0.95rem;">Edit Amendment Draft</h6>
          </div>
          <button class="btn-close btn-close-white" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body p-3">
          <div class="row g-2">
            <div class="col-md-4">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Type</label>
              <select class="form-select form-select-sm" name="amendment_type">
                <?php foreach(['Amendment','Revision','Technical Correction','Repeal','Superseding Measure'] as $x): ?>
                  <option <?= $a['amendment_type']===$x?'selected':'' ?>><?= e($x) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Related Item</label>
              <select class="form-select form-select-sm" name="related_legislative_item_id">
                <option value="">None</option>
                <?php foreach($relatedItems as $x): if((int)$x['id']===(int)$a['legislative_item_id'])continue; ?>
                  <option value="<?= (int)$x['id'] ?>" <?= (int)$a['related_legislative_item_id']===(int)$x['id']?'selected':'' ?>><?= e($x['reference_number'].' · '.$x['title']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Relationship</label>
              <select class="form-select form-select-sm" name="relationship_type">
                <option value="">Not specified</option>
                <?php foreach(['Amends','Repeals','Supersedes','Related Measure','Technical Correction'] as $x): ?>
                  <option <?= $a['relationship_type']===$x?'selected':'' ?>><?= e($x) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Title</label>
              <input class="form-control form-control-sm" name="title" value="<?= e($a['title']) ?>" required>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Summary</label>
              <textarea class="form-control form-control-sm" name="summary" rows="3" style="resize: vertical; min-height: 65px;"><?= e($a['summary']?:'') ?></textarea>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Rationale</label>
              <textarea class="form-control form-control-sm" name="rationale" rows="3" style="resize: vertical; min-height: 65px;"><?= e($a['rationale']?:'') ?></textarea>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Effectivity Date</label>
              <input type="date" class="form-control form-control-sm" name="effective_date" value="<?= e($a['effective_date']?:'') ?>">
            </div>
            <div class="col-12">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Complete Revised Legislative Text</label>
              <textarea class="form-control form-control-sm font-monospace" name="revised_body_text" rows="6" style="min-height: 110px; max-height: 200px; resize: vertical;"><?= e($a['revised_body_text']?:'') ?></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer py-2 px-3 bg-light border-top">
          <button class="btn btn-sm btn-outline-secondary px-3" type="button" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-sm btn-primary px-4 fw-semibold"><i class="bi bi-check-circle me-1"></i>Save Draft</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="changeModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" style="max-width: 620px;">
    <div class="modal-content shadow-lg border-0 rounded-3 overflow-hidden">
      <form id="changeForm">
        <?= csrfField() ?>
        <input type="hidden" name="amendment_id" value="<?= $id ?>">
        <input type="hidden" name="change_id" id="c_id" value="0">
        <input type="hidden" name="mode" value="save">
        <div class="modal-header py-2 px-3 border-0" style="background: linear-gradient(135deg, #0f172a, #1e293b); color: #fff;">
          <div class="d-flex align-items-center gap-2">
            <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 28px; height: 28px; background: rgba(14,165,233,0.25); color: #38bdf8; font-size: 12px;">
              <i class="bi bi-file-diff"></i>
            </div>
            <h6 class="modal-title fw-bold m-0" style="font-size: 0.95rem;">Provision Change</h6>
          </div>
          <button class="btn-close btn-close-white" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body p-3">
          <div class="row g-2">
            <div class="col-md-7">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Section / Provision Reference</label>
              <input class="form-control form-control-sm" name="section_reference" id="c_section">
            </div>
            <div class="col-md-5">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Change Type</label>
              <select class="form-select form-select-sm" name="change_type" id="c_type">
                <option>Add</option>
                <option selected>Modify</option>
                <option>Delete</option>
                <option>Replace</option>
                <option>Repeal</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Previous Text</label>
              <textarea class="form-control form-control-sm" name="previous_text" id="c_previous" rows="4" style="resize: vertical; min-height: 80px; max-height: 140px;"></textarea>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">New / Revised Text</label>
              <textarea class="form-control form-control-sm" name="new_text" id="c_new" rows="4" style="resize: vertical; min-height: 80px; max-height: 140px;"></textarea>
            </div>
            <div class="col-12">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Rationale</label>
              <textarea class="form-control form-control-sm" name="rationale" id="c_rationale" rows="2" style="resize: vertical; min-height: 50px;"></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer py-2 px-3 bg-light border-top">
          <button class="btn btn-sm btn-outline-secondary px-3" type="button" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-sm btn-primary px-4 fw-semibold"><i class="bi bi-check2 me-1"></i>Save Change</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded',function(){
 const editForm=document.getElementById('editForm'),changeForm=document.getElementById('changeForm'),docForm=document.getElementById('docForm');
 const editModalEl=document.getElementById('editModal'),changeModalEl=document.getElementById('changeModal');
 const editModal=editModalEl?new bootstrap.Modal(editModalEl):null,changeModal=changeModalEl?new bootstrap.Modal(changeModalEl):null;
 const c_id=document.getElementById('c_id'),c_section=document.getElementById('c_section'),
       c_type=document.getElementById('c_type'),c_previous=document.getElementById('c_previous'),
       c_new=document.getElementById('c_new'),c_rationale=document.getElementById('c_rationale');
 const editBtn=document.getElementById('btnEditAmendment');if(editBtn&&editModal)editBtn.onclick=()=>editModal.show();
 if(editForm)editForm.onsubmit=async e=>{e.preventDefault();const r=await fetch('ajax_save.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:new FormData(editForm)}).then(x=>x.json());if(r.success)location.reload();else Swal.fire('Amendment Error',r.message,'error');};

 const add=document.getElementById('btnAddChange');if(add&&changeModal)add.onclick=()=>{changeForm.reset();c_id.value='0';c_type.value='Modify';changeModal.show();};
 document.querySelectorAll('.edit-change').forEach(b=>b.onclick=function(){if(!changeModal)return;const x=JSON.parse(this.dataset.row);changeForm.reset();c_id.value=x.id;c_section.value=x.section_reference||'';c_type.value=x.change_type||'Modify';c_previous.value=x.previous_text||'';c_new.value=x.new_text||'';c_rationale.value=x.rationale||'';changeModal.show();});
 if(changeForm)changeForm.onsubmit=async e=>{e.preventDefault();const r=await fetch('ajax_change.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:new FormData(changeForm)}).then(x=>x.json());if(r.success)location.reload();else Swal.fire('Change Error',r.message,'error');};
 document.querySelectorAll('.delete-change').forEach(b=>b.onclick=async function(){const ask=await Swal.fire({title:'Delete this change entry?',icon:'warning',showCancelButton:true});if(!ask.isConfirmed)return;const fd=new FormData();fd.append('csrf_token','<?= e(csrfToken()) ?>');fd.append('amendment_id','<?= $id ?>');fd.append('change_id',this.dataset.id);fd.append('mode','delete');const r=await fetch('ajax_change.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:fd}).then(x=>x.json());if(r.success)location.reload();else Swal.fire('Change Error',r.message,'error');});

 document.querySelectorAll('.amendment-action').forEach(b=>b.onclick=async function(){let notes='',effective='';if(['return','reject','withdraw','approve','apply'].includes(this.dataset.action)){const p=await Swal.fire({title:this.dataset.action.charAt(0).toUpperCase()+this.dataset.action.slice(1)+' amendment?',input:'textarea',inputLabel:this.dataset.action==='approve'?'Approval remarks (optional)':'Notes / reason',showCancelButton:true,inputValidator:v=>['return','reject','withdraw'].includes(this.dataset.action)&&!v?'Notes are required':undefined});if(!p.isConfirmed)return;notes=p.value||'';}else{const p=await Swal.fire({title:'Submit amendment for review?',showCancelButton:true});if(!p.isConfirmed)return;}const fd=new FormData();fd.append('csrf_token','<?= e(csrfToken()) ?>');fd.append('amendment_id','<?= $id ?>');fd.append('action',this.dataset.action);fd.append('notes',notes);fd.append('effective_date',effective);const r=await fetch('ajax_transition.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:fd}).then(x=>x.json());if(r.success)location.reload();else Swal.fire('Workflow Error',r.message,'error');});
 if(docForm)docForm.onsubmit=async e=>{e.preventDefault();const r=await fetch('ajax_upload_document.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:new FormData(docForm)}).then(x=>x.json());if(r.success)location.reload();else Swal.fire('Upload Error',r.message,'error');};
});
</script>
<?php include __DIR__.'/../../layouts/footer.php'; ?>
