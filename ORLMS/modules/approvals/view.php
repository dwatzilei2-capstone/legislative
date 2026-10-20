<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/orlms_lifecycle_helpers.php';
requirePermission('orlms.approvals.view');

$pdo=db();$itemId=(int)($_GET['id']??0);
$approvalItem=orlmsApprovalItem($pdo,$itemId);
if(!$approvalItem){setFlash('warning','Legislative item not found.');redirect(APP_URL.'/modules/approvals/index.php');}

$actions=$pdo->prepare(
 "SELECT a.*,u.full_name authority_name,o.name authority_office_name
  FROM orlms_approval_actions a
  LEFT JOIN users u ON u.id=a.authority_user_id
  LEFT JOIN offices o ON o.id=a.authority_office_id
  WHERE a.legislative_item_id=:id ORDER BY a.created_at DESC,a.id DESC"
);$actions->execute([':id'=>$itemId]);$approvalRows=$actions->fetchAll();

$enactment=orlmsGetEnactment($pdo,$itemId);
$docs=orlmsDraftDocuments($pdo,$itemId);

$ah=$pdo->prepare(
 "SELECT h.*,u.full_name changed_name FROM orlms_approval_history h
  LEFT JOIN users u ON u.id=h.changed_by
  WHERE h.legislative_item_id=:id ORDER BY h.created_at DESC,h.id DESC"
);$ah->execute([':id'=>$itemId]);$approvalHistory=$ah->fetchAll();

$eh=[];
if($enactment){
    $q=$pdo->prepare(
      "SELECT h.*,u.full_name changed_name FROM orlms_enactment_history h
       LEFT JOIN users u ON u.id=h.changed_by
       WHERE h.enactment_id=:id ORDER BY h.created_at DESC,h.id DESC"
    );$q->execute([':id'=>$enactment['id']]);$eh=$q->fetchAll();
}

$offices=$pdo->query("SELECT id,name FROM offices WHERE status='Active' ORDER BY name")->fetchAll();
$users=$pdo->query(
 "SELECT u.id,u.full_name FROM users u JOIN roles r ON r.id=u.role_id
  WHERE u.status='Active' AND u.deleted_at IS NULL
    AND r.name IN ('Administrator','Legislative Staff')
  ORDER BY u.full_name"
)->fetchAll();

$pageTitle=$approvalItem['reference_number'];$activeMenu='approvals';
$extraCss=[APP_URL.'/assets/css/orlms-lifecycle-ops.css'];
include __DIR__.'/../../layouts/header.php';
?>
<div class="orlms-app-layout"><?php include __DIR__.'/../../layouts/sidebar.php'; ?><main class="orlms-main-content">
<div class="orl-head"><div><a href="index.php" class="small text-decoration-none"><i class="bi bi-arrow-left"></i> Approval & Enactment</a><div class="orl-eyebrow mt-2"><?= e($approvalItem['reference_number'] ?? 'NO-REF') ?></div><h1><?= e($approvalItem['title'] ?? 'Untitled Item') ?></h1><p><?= e(($approvalItem['item_type_name'] ?? 'Legislative Item').' · '.($approvalItem['originating_office'] ?? 'No originating office').' · Committee: '.($approvalItem['committee_name'] ?? '—')) ?></p></div><div class="d-flex gap-2 flex-wrap"><span class="orl-status <?= in_array($approvalItem['current_status'] ?? '',['Approved','Enacted'],true)?'good':(in_array($approvalItem['current_status'] ?? '',['Rejected','Vetoed','Revision Required'],true)?'bad':'') ?>"><?= e($approvalItem['current_status'] ?? 'Pending Approval') ?></span><a class="btn btn-outline-secondary btn-sm" target="_blank" href="print.php?id=<?= $itemId ?>"><i class="bi bi-printer"></i></a><?php if(($approvalItem['current_status'] ?? '')==='Enacted'): ?><a class="btn btn-primary btn-sm" href="<?= e(APP_URL.'/modules/publications/index.php?item_id='.$itemId) ?>"><i class="bi bi-megaphone"></i> Continue to Publication</a><?php endif; ?></div></div>

<div class="row g-3">
<div class="col-xl-8">
<div class="card orl-card mb-3"><div class="card-header">Committee-Endorsed Legislative Item</div><div class="card-body"><div class="orl-grid mb-3"><div><small>Endorsement</small><strong><?= e($approvalItem['endorsement_status'] ?? '—') ?></strong></div><div><small>Committee</small><strong><?= e($approvalItem['committee_name'] ?? '—') ?></strong></div><div><small>Vote Summary</small><strong><?= e($approvalItem['vote_summary'] ?? '—') ?></strong></div><div><small>Recommendation</small><strong><?= e($approvalItem['endorsement_recommendation'] ?? '—') ?></strong></div><div><small>Legal Basis</small><strong><?= e(mb_strimwidth($approvalItem['legal_basis'] ?? 'Not specified',0,100,'…')) ?></strong></div><div><small>Fiscal Impact</small><strong><?= e(mb_strimwidth($approvalItem['fiscal_impact'] ?? 'Not specified',0,100,'…')) ?></strong></div></div><div class="orl-alert"><strong>Subject / Draft Summary</strong><br><?= e(($approvalItem['subject'] ?? '') ?: ($approvalItem['summary'] ?? '') ?: 'No summary.') ?></div></div></div>

<div class="card orl-card mb-3"><div class="card-header d-flex justify-content-between"><span>Approval Actions</span><?php if(hasPermission('orlms.approvals.manage')&&in_array($approvalItem['current_status'] ?? '',['Endorsed','Pending Approval','Vetoed'],true)): ?><button class="btn btn-sm btn-primary" id="btnAddApproval"><i class="bi bi-plus"></i> New Approval Action</button><?php endif; ?></div><div class="table-responsive"><table class="table orl-table mb-0"><thead><tr><th>Reference</th><th>Type</th><th>Authority</th><th>Date</th><th>Status</th><th>Remarks</th><th class="text-end">Control</th></tr></thead><tbody>
<?php if(!$approvalRows): ?><tr><td colspan="7" class="text-center text-muted py-4">No approval action recorded.</td></tr><?php endif; ?>
<?php foreach($approvalRows as $a): ?><tr><td><span class="orl-code"><?= e($a['action_reference']) ?></span></td><td><?= e($a['action_type']) ?></td><td><?= e($a['authority_name']?:$a['authority_office_name']?:'Unassigned') ?></td><td><?= $a['action_date']?formatDateTime($a['action_date']):'—' ?></td><td><?= e($a['action_status']) ?></td><td><?= e(mb_strimwidth($a['remarks']?:'',0,100,'…')) ?></td><td class="text-end"><?php if(hasPermission('orlms.approvals.manage')&&in_array($a['action_status'],['Pending','Returned'],true)): ?><div class="btn-group btn-group-sm"><?php if(($approvalItem['current_status'] ?? '')==='Vetoed'): ?><button class="btn btn-outline-success approval-transition" data-id="<?= (int)$a['id'] ?>" data-action="override">Override</button><?php else: ?><button class="btn btn-outline-success approval-transition" data-id="<?= (int)$a['id'] ?>" data-action="approve">Approve</button><button class="btn btn-outline-warning approval-transition" data-id="<?= (int)$a['id'] ?>" data-action="return">Return</button><button class="btn btn-outline-danger approval-transition" data-id="<?= (int)$a['id'] ?>" data-action="reject">Reject</button><button class="btn btn-outline-dark approval-transition" data-id="<?= (int)$a['id'] ?>" data-action="veto">Veto</button><?php endif; ?></div><?php endif; ?></td></tr><?php endforeach; ?>
</tbody></table></div></div>

<div class="card orl-card mb-3"><div class="card-header">Enactment / Adoption Record</div><div class="card-body">
<?php if($enactment): ?><div class="orl-grid mb-3"><div><small>Reference</small><strong><?= e($enactment['enactment_reference']) ?></strong></div><div><small>Official Number</small><strong><?= e($enactment['official_number']?:'—') ?></strong></div><div><small>Type</small><strong><?= e($enactment['enactment_type']) ?></strong></div><div><small>Status</small><strong><?= e($enactment['enactment_status']) ?></strong></div><div><small>Enactment Date</small><strong><?= $enactment['enactment_date']?formatDate($enactment['enactment_date']):'—' ?></strong></div><div><small>Effectivity</small><strong><?= e($enactment['effectivity_type']?:'—') ?><?= $enactment['effectivity_date']?' · '.formatDate($enactment['effectivity_date']):'' ?></strong></div></div><?php endif; ?>
<?php if(hasPermission('orlms.approvals.manage')&&in_array($approvalItem['current_status'] ?? '',['Approved','Enacted'],true)&&(!$enactment||$enactment['enactment_status']!=='Finalized')): ?><form id="enactmentForm" class="row g-3"><?= csrfField() ?><input type="hidden" name="legislative_item_id" value="<?= $itemId ?>"><input type="hidden" name="mode" value="save"><div class="col-md-6"><label class="form-label">Official Number *</label><input class="form-control" name="official_number" value="<?= e($enactment['official_number']??'') ?>" required placeholder="<?= ($approvalItem['item_type_code'] ?? 'ordinance')==='ordinance'?'Ordinance No. 2026-001':'Resolution No. 2026-001' ?>"></div><div class="col-md-6"><label class="form-label">Enactment Type</label><input class="form-control" name="enactment_type" value="<?= e($enactment['enactment_type']??(($approvalItem['item_type_code'] ?? '')==='resolution'?'Resolution Adoption':'Ordinance Enactment')) ?>"></div><div class="col-md-4"><label class="form-label">Approved Date</label><input type="date" class="form-control" name="approved_date" value="<?= e($enactment['approved_date']??'') ?>"></div><div class="col-md-4"><label class="form-label">Signed Date</label><input type="date" class="form-control" name="signed_date" value="<?= e($enactment['signed_date']??'') ?>"></div><div class="col-md-4"><label class="form-label">Enactment / Adoption Date *</label><input type="date" class="form-control" name="enactment_date" value="<?= e($enactment['enactment_date']??'') ?>" required></div><div class="col-md-4"><label class="form-label">Signing Authority</label><input class="form-control" name="signing_authority" value="<?= e($enactment['signing_authority']??'') ?>"></div><div class="col-md-4"><label class="form-label">Effectivity Type</label><select class="form-select" name="effectivity_type"><?php foreach(['Upon Approval','Upon Publication','Specified Date','Immediate'] as $x): ?><option <?= ($enactment['effectivity_type']??'Upon Approval')===$x?'selected':'' ?>><?= e($x) ?></option><?php endforeach; ?></select></div><div class="col-md-4"><label class="form-label">Effectivity Date</label><input type="date" class="form-control" name="effectivity_date" value="<?= e($enactment['effectivity_date']??'') ?>"></div><div class="col-12"><label class="form-label">Remarks</label><textarea class="form-control" name="remarks" rows="3"><?= e($enactment['remarks']??'') ?></textarea></div><div class="col-12 d-flex gap-2"><button class="btn btn-primary"><i class="bi bi-floppy"></i> Save Enactment</button><?php if($enactment): ?><button type="button" class="btn btn-success" id="btnFinalizeEnactment"><i class="bi bi-patch-check"></i> Finalize Enactment</button><?php endif; ?></div></form><?php endif; ?>
</div></div>

<div class="card orl-card"><div class="card-header">Approval / Enactment Documents</div><div class="card-body"><div class="list-group mb-3"><?php if(!$docs): ?><div class="list-group-item text-muted">No documents attached.</div><?php endif; ?><?php foreach($docs as $d): ?><a target="_blank" class="list-group-item list-group-item-action" href="<?= e(UPLOAD_URL.$d['file_path']) ?>"><strong><?= e($d['title']) ?></strong><div class="small text-muted"><?= e($d['document_type'].' · '.$d['visibility']) ?> · <?= formatDateTime($d['created_at']) ?></div></a><?php endforeach; ?></div><?php if(hasPermission('orlms.approvals.manage')): ?><form id="docForm" class="row g-2" enctype="multipart/form-data"><?= csrfField() ?><input type="hidden" name="legislative_item_id" value="<?= $itemId ?>"><div class="col-md-5"><input type="file" class="form-control form-control-sm" name="document" required></div><div class="col-md-3"><input class="form-control form-control-sm" name="document_type" value="Approval / Enactment Document"></div><div class="col-md-2"><input class="form-control form-control-sm" name="document_title" placeholder="Title"></div><div class="col-md-2"><select class="form-select form-select-sm" name="visibility"><option>Internal</option><option>Restricted</option><option>Public</option></select></div><div class="col-12"><button class="btn btn-outline-primary btn-sm">Upload Document</button></div></form><?php endif; ?></div></div>
</div>

<div class="col-xl-4">
<div class="card orl-card mb-3"><div class="card-header">Approval History</div><div class="card-body orl-history"><?php if(!$approvalHistory): ?><div class="text-muted small">No approval history.</div><?php endif; ?><?php foreach($approvalHistory as $h): ?><div><strong><?= e($h['action']) ?> · <?= e($h['changed_name']?:'System') ?></strong><small><?= e(($h['previous_status']?:'—').' → '.($h['new_status']?:'—')) ?><?= $h['details']?'<br>'.e($h['details']):'' ?><br><?= formatDateTime($h['created_at']) ?></small></div><?php endforeach; ?></div></div>
<div class="card orl-card"><div class="card-header">Enactment History</div><div class="card-body orl-history"><?php if(!$eh): ?><div class="text-muted small">No enactment history.</div><?php endif; ?><?php foreach($eh as $h): ?><div><strong><?= e($h['action']) ?> · <?= e($h['changed_name']?:'System') ?></strong><small><?= e(($h['previous_status']?:'—').' → '.($h['new_status']?:'—')) ?><?= $h['details']?'<br>'.e($h['details']):'' ?><br><?= formatDateTime($h['created_at']) ?></small></div><?php endforeach; ?></div></div>
</div>
</div>
</main></div>

<?php if(hasPermission('orlms.approvals.manage')): ?>
<div class="modal fade" id="approvalModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" style="max-width: 580px;">
    <div class="modal-content shadow-lg border-0 rounded-3 overflow-hidden">
      <form id="approvalForm">
        <?= csrfField() ?>
        <input type="hidden" name="legislative_item_id" value="<?= $itemId ?>">
        <input type="hidden" name="approval_id" value="0">
        <input type="hidden" name="mode" value="save">
        <div class="modal-header py-2 px-3 border-0" style="background: linear-gradient(135deg, #0f172a, #1e293b); color: #fff;">
          <div class="d-flex align-items-center gap-2">
            <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 28px; height: 28px; background: rgba(59,130,246,0.25); color: #60a5fa; font-size: 12px;">
              <i class="bi bi-shield-check"></i>
            </div>
            <h6 class="modal-title fw-bold m-0" style="font-size: 0.95rem;">New Approval Action</h6>
          </div>
          <button class="btn-close btn-close-white" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body p-3">
          <div class="row g-2">
            <div class="col-12">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Action Type</label>
              <select class="form-select form-select-sm" name="action_type">
                <?php foreach(['Final Approval','Executive Approval','Adoption','Veto Review','Veto Override','Return for Clarification'] as $x): ?>
                  <option><?= e($x) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Authority User</label>
              <select class="form-select form-select-sm" name="authority_user_id">
                <option value="">Unassigned</option>
                <?php foreach($users as $u): ?>
                  <option value="<?= (int)$u['id'] ?>"><?= e($u['full_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Authority Office</label>
              <select class="form-select form-select-sm" name="authority_office_id">
                <option value="">Unassigned</option>
                <?php foreach($offices as $o): ?>
                  <option value="<?= (int)$o['id'] ?>"><?= e($o['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Remarks</label>
              <textarea class="form-control form-control-sm" name="remarks" rows="3" style="resize: vertical; min-height: 65px;"></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer py-2 px-3 bg-light border-top">
          <button class="btn btn-sm btn-outline-secondary px-3" type="button" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-sm btn-primary px-4 fw-semibold"><i class="bi bi-check2 me-1"></i>Save Approval Action</button>
        </div>
      </form>
    </div>
  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded',function(){
 const approvalForm=document.getElementById('approvalForm'),docForm=document.getElementById('docForm'),enactmentForm=document.getElementById('enactmentForm');
 const btn=document.getElementById('btnAddApproval');if(btn){const modal=new bootstrap.Modal(document.getElementById('approvalModal'));btn.onclick=()=>modal.show();approvalForm.onsubmit=async e=>{e.preventDefault();const r=await fetch('ajax_approve.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:new FormData(approvalForm)}).then(x=>x.json());if(r.success)location.reload();else Swal.fire('Approval Error',r.message,'error');};}
 document.querySelectorAll('.approval-transition').forEach(b=>b.onclick=async function(){const needs=['return','reject','veto'].includes(this.dataset.action);let note='';if(needs){const p=await Swal.fire({title:this.dataset.action.charAt(0).toUpperCase()+this.dataset.action.slice(1)+' approval?',input:'textarea',inputLabel:'Reason',showCancelButton:true,inputValidator:v=>!v?'Reason is required':undefined});if(!p.isConfirmed)return;note=p.value;}else{const p=await Swal.fire({title:'Confirm '+this.dataset.action+'?',showCancelButton:true});if(!p.isConfirmed)return;}const fd=new FormData();fd.append('csrf_token','<?= e(csrfToken()) ?>');fd.append('legislative_item_id','<?= $itemId ?>');fd.append('approval_id',this.dataset.id);fd.append('mode','transition');fd.append('action',this.dataset.action);fd.append('remarks',note);const r=await fetch('ajax_approve.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:fd}).then(x=>x.json());if(r.success)location.reload();else Swal.fire('Approval Error',r.message,'error');});
 if(enactmentForm)enactmentForm.onsubmit=async e=>{e.preventDefault();const r=await fetch('ajax_enact.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:new FormData(enactmentForm)}).then(x=>x.json());if(r.success)location.reload();else Swal.fire('Enactment Error',r.message,'error');};
 const finalize=document.getElementById('btnFinalizeEnactment');if(finalize)finalize.onclick=async()=>{const ask=await Swal.fire({title:'Finalize enactment?',text:'Official numbering and enactment details will be locked for the publication workflow.',icon:'question',showCancelButton:true});if(!ask.isConfirmed)return;const fd=new FormData();fd.append('csrf_token','<?= e(csrfToken()) ?>');fd.append('legislative_item_id','<?= $itemId ?>');fd.append('mode','finalize');const r=await fetch('ajax_enact.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:fd}).then(x=>x.json());if(r.success)location.reload();else Swal.fire('Enactment Error',r.message,'error');};
 if(docForm)docForm.onsubmit=async e=>{e.preventDefault();const r=await fetch('ajax_upload_document.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:new FormData(docForm)}).then(x=>x.json());if(r.success)location.reload();else Swal.fire('Upload Error',r.message,'error');};
});
</script>
<?php endif; ?>
<?php include __DIR__.'/../../layouts/footer.php'; ?>
