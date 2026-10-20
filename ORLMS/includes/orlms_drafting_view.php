<?php
declare(strict_types=1);

if(!isset($orlmsItemTypeCode,$orlmsActiveMenu)){
    throw new RuntimeException('ORLMS drafting view configuration is missing.');
}

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/orlms_drafting_helpers.php';
requirePermission('orlms.drafting.manage');

$pdo=db();$id=(int)($_GET['id']??0);
$typeCode=$orlmsItemTypeCode;$label=orlmsItemTypeLabel($typeCode);
$item=orlmsGetDraft($pdo,$id,$typeCode);

if(!$item){
    setFlash('warning',$label.' draft not found.');
    redirect(APP_URL.'/modules/'.($typeCode==='resolution'?'resolutions':'ordinances').'/index.php');
}

$authors=orlmsDraftAuthors($pdo,$id);
$documents=orlmsDraftDocuments($pdo,$id);
$versions=orlmsDraftVersions($pdo,$id);
$workflow=orlmsWorkflow($pdo,$id);
$review=orlmsLatestReview($pdo,$id);

$committees=$pdo->query("SELECT id,name FROM committees WHERE status='Active' ORDER BY name")->fetchAll();
$users=$pdo->query(
    "SELECT u.id,u.full_name
     FROM users u JOIN roles r ON r.id=u.role_id
     WHERE u.status='Active' AND u.deleted_at IS NULL
       AND r.name IN ('Administrator','Legislative Staff','Committee Member')
     ORDER BY u.full_name"
)->fetchAll();

$pageTitle=$item['reference_number'];$activeMenu=$orlmsActiveMenu;
$extraCss=[APP_URL.'/assets/css/orlms-drafting-review.css'];
include __DIR__.'/../layouts/header.php';
?>
<div class="orlms-app-layout"><?php include __DIR__.'/../layouts/sidebar.php'; ?><main class="orlms-main-content">

<<div class="orx-head">
<div><a class="small text-decoration-none" href="index.php"><i class="bi bi-arrow-left"></i> <?= e($label) ?> Registry</a><div class="orx-eyebrow mt-2"><?= e($item['reference_number'] ?? 'NO-REF') ?></div><h1><?= e($item['title'] ?? 'Untitled Draft') ?></h1><p><?= e(($item['originating_office'] ?? 'No originating office').' · '.($item['subject'] ?? 'No subject').' · '.($item['priority_level'] ?? 'Normal').' priority') ?></p></div>
<div class="d-flex gap-2 flex-wrap"><span class="orx-status <?= in_array($item['current_status'] ?? '',['Endorsed','Committee Reviewed'],true)?'good':(in_array($item['current_status'] ?? '',['Revision Required','Returned','Not Endorsed'],true)?'bad':'') ?>"><?= e($item['current_status'] ?? 'Draft') ?></span><?php if(orlmsCanEditDraft($item['current_status'] ?? null)): ?><button class="btn btn-success btn-sm" id="btnSubmit"><i class="bi bi-send"></i> Submit for Review</button><?php endif; ?><?php if($review): ?><a class="btn btn-primary btn-sm" href="<?= e(APP_URL.'/modules/reviews/view.php?id='.$review['id']) ?>"><i class="bi bi-clipboard-check"></i> Review Record</a><?php endif; ?></div>
</div>

<div class="row g-3 mb-3">
<?php foreach([
 ['Draft Versions',count($versions),'bi-clock-history'],
 ['Authors / Sponsors',count($authors),'bi-people'],
 ['Documents',count($documents),'bi-paperclip'],
 ['Review Round',$review?$review['review_round']:0,'bi-clipboard-check'],
] as [$l,$v,$i]): ?><div class="col-6 col-lg-3"><div class="orx-stat"><i class="bi <?= e($i) ?>"></i><div><strong><?= (int)$v ?></strong><small><?= e($l) ?></small></div></div></div><?php endforeach; ?>
</div>

<?php if(!empty($item['return_reason'])): ?><div class="alert alert-warning"><strong>Revision requested:</strong> <?= nl2br(e($item['return_reason'])) ?></div><?php endif; ?>

<div class="row g-3">
<div class="col-xl-8">
<div class="card orx-card mb-3"><div class="card-header">Legislative Draft</div><div class="card-body">
<div class="orx-grid mb-3"><div><small>Short Title</small><strong><?= e($item['short_title'] ?? '—') ?></strong></div><div><small>Template</small><strong><?= e($item['template_name'] ?? 'No template') ?></strong></div><div><small>Category</small><strong><?= e($item['legislative_category'] ?? '—') ?></strong></div><div><small>Policy Area</small><strong><?= e($item['policy_area'] ?? '—') ?></strong></div><div><small>Visibility</small><strong><?= e($item['visibility'] ?? 'Internal') ?></strong></div><div><small>Last Updated</small><strong><?= formatDateTime($item['updated_at'] ?? date('Y-m-d H:i:s')) ?></strong></div></div>
<?php if(!empty($item['summary'])): ?><p class="small"><strong>Summary</strong><br><?= nl2br(e($item['summary'])) ?></p><?php endif; ?>
<?php if(!empty($item['purpose'])): ?><p class="small"><strong>Purpose</strong><br><?= nl2br(e($item['purpose'])) ?></p><?php endif; ?>
<?php if(!empty($item['explanatory_note'])): ?><p class="small"><strong>Explanatory Note</strong><br><?= nl2br(e($item['explanatory_note'])) ?></p><?php endif; ?>
<div class="orx-body-preview"><?= e($item['body_text'] ?? 'No draft body encoded.') ?></div>
<?php if(!empty($item['legal_basis']) || !empty($item['fiscal_impact'])): ?><div class="row g-3 mt-1"><div class="col-md-6"><div class="orx-alert"><strong>Legal Basis</strong><br><?= nl2br(e($item['legal_basis'] ?? 'Not specified')) ?></div></div><div class="col-md-6"><div class="orx-alert"><strong>Fiscal / Resource Impact</strong><br><?= nl2br(e($item['fiscal_impact'] ?? 'Not specified')) ?></div></div></div><?php endif; ?>
</div></div>

<div class="card orx-card mb-3"><div class="card-header">Authors / Sponsors</div><div class="table-responsive"><table class="table orx-table mb-0"><thead><tr><th>Name</th><th>Role</th><th>Office</th></tr></thead><tbody><?php if(!$authors): ?><tr><td colspan="3" class="text-center text-muted py-4">No authors encoded.</td></tr><?php endif; ?><?php foreach($authors as $a): ?><tr><td><strong><?= e($a['author_name']) ?></strong><?= $a['user_name']?'<div class="small text-muted">Linked user: '.e($a['user_name']).'</div>':'' ?></td><td><?= e($a['author_type']) ?></td><td><?= e($a['office_name']?:'—') ?></td></tr><?php endforeach; ?></tbody></table></div></div>

<div class="card orx-card"><div class="card-header">Supporting Documents</div><div class="card-body">
<div class="list-group mb-3"><?php if(!$documents): ?><div class="list-group-item text-muted">No supporting documents.</div><?php endif; ?><?php foreach($documents as $doc): ?><div class="list-group-item"><div class="d-flex justify-content-between gap-2"><a target="_blank" class="text-decoration-none" href="<?= e(UPLOAD_URL.$doc['file_path']) ?>"><strong><?= e($doc['title']) ?></strong><div class="small text-muted"><?= e($doc['document_type'].' · v'.$doc['version_number'].' · '.$doc['visibility']) ?> · <?= formatDateTime($doc['created_at']) ?></div></a><?php if(orlmsCanEditDraft($item['current_status'] ?? null)): ?><button class="btn btn-sm btn-outline-danger doc-delete" data-id="<?= (int)$doc['id'] ?>"><i class="bi bi-trash"></i></button><?php endif; ?></div></div><?php endforeach; ?></div>
<?php if(orlmsCanEditDraft($item['current_status'] ?? null)): ?><form id="docForm" class="row g-2 align-items-end" enctype="multipart/form-data"><?= csrfField() ?><input type="hidden" name="id" value="<?= $id ?>"><div class="col-md-5"><label class="form-label small">Document</label><input type="file" class="form-control form-control-sm" name="document" required></div><div class="col-md-3"><label class="form-label small">Type</label><input class="form-control form-control-sm" name="document_type" value="Supporting Document"></div><div class="col-md-2"><label class="form-label small">Title</label><input class="form-control form-control-sm" name="document_title"></div><div class="col-md-2"><label class="form-label small">Visibility</label><select class="form-select form-select-sm" name="visibility"><option>Internal</option><option>Restricted</option><option>Public</option></select></div><div class="col-12"><button class="btn btn-outline-primary btn-sm"><i class="bi bi-upload"></i> Upload Document</button></div></form><?php endif; ?>
</div></div>
</div>

<div class="col-xl-4">
<div class="card orx-card mb-3"><div class="card-header">Latest Review</div><div class="card-body"><?php if(!$review): ?><div class="text-muted small">This draft has not yet entered committee review.</div><?php else: ?><div class="orx-grid"><div><small>Round</small><strong><?= (int)$review['review_round'] ?></strong></div><div><small>Committee</small><strong><?= e($review['committee_name']?:'Unassigned') ?></strong></div><div><small>Reviewer</small><strong><?= e($review['reviewer_name']?:'Committee Queue') ?></strong></div><div><small>Review Status</small><strong><?= e($review['status']) ?></strong></div><div><small>Recommendation</small><strong><?= e($review['recommendation']?:'Pending') ?></strong></div><div><small>Endorsement</small><strong><?= e($review['endorsement_status']?:'Pending') ?></strong></div></div><?php endif; ?></div></div>

<div class="card orx-card mb-3"><div class="card-header">Draft Version History</div><div class="card-body orx-history"><?php if(!$versions): ?><div class="text-muted small">No version snapshots.</div><?php endif; ?><?php foreach($versions as $v): ?><div><strong>Version <?= (int)$v['version_number'] ?> · <?= e($v['created_by_name']?:'System') ?></strong><small><?= e($v['change_summary']?:'Draft snapshot') ?><br><?= formatDateTime($v['created_at']) ?></small></div><?php endforeach; ?></div></div>

<div class="card orx-card"><div class="card-header">Workflow History</div><div class="card-body orx-history"><?php foreach($workflow as $h): ?><div><strong><?= e($h['stage_code'].' · '.$h['action']) ?></strong><small><?= e(($h['previous_status']?:'—').' → '.($h['new_status']?:'—')) ?><?= $h['remarks']?'<br>'.e($h['remarks']):'' ?><br><?= formatDateTime($h['created_at']) ?> · <?= e($h['actor_name']?:'System') ?></small></div><?php endforeach; ?></div></div>
</div>
</div>
</main></div>

<?php if(orlmsCanEditDraft($item['current_status'] ?? null)): ?>
<div class="modal fade" id="submitModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered" style="max-width: 580px;">
    <div class="modal-content border-0 shadow-lg" style="border-radius: 14px; overflow: hidden;">
      <form id="submitForm">
        <?= csrfField() ?>
        <input type="hidden" name="id" value="<?= $id ?>">
        <div class="modal-header py-2.5 px-3.5" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); border-bottom: 2px solid #3b82f6;">
          <div class="d-flex align-items-center gap-2">
            <div class="rounded-circle d-flex align-items-center justify-content-center bg-primary bg-opacity-25 text-info" style="width: 32px; height: 32px;">
              <i class="bi bi-send-check fs-6"></i>
            </div>
            <div>
              <h6 class="modal-title text-white fw-bold mb-0" style="font-size: 0.95rem;">Submit <?= e($label) ?> for Review</h6>
              <small class="text-white-50" style="font-size: 0.72rem;">Refer draft to designated legislative committee</small>
            </div>
          </div>
          <button class="btn-close btn-close-white" type="button" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-3.5">
          <div class="orx-alert mb-2.5 small"><?= e($item['reference_number']) ?> will be versioned and locked while committee review is active.</div>
          <div class="row g-2">
            <div class="col-md-6">
              <label class="form-label small fw-semibold text-secondary mb-1">Committee *</label>
              <select class="form-select form-select-sm" name="committee_id" required>
                <option value="">Select committee</option>
                <?php foreach($committees as $c): ?>
                  <option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold text-secondary mb-1">Reviewer</label>
              <select class="form-select form-select-sm" name="reviewer_id">
                <option value="">Committee queue / unassigned</option>
                <?php foreach($users as $u): ?>
                  <option value="<?= (int)$u['id'] ?>"><?= e($u['full_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label small fw-semibold text-secondary mb-1">Submission Note</label>
              <textarea class="form-control form-control-sm" name="submission_note" rows="3" placeholder="Notes for the committee/reviewer"></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer py-2 px-3.5 bg-light border-top d-flex justify-content-end gap-2">
          <button class="btn btn-sm btn-outline-secondary px-3" type="button" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-sm btn-success px-3 fw-semibold shadow-sm"><i class="bi bi-send-check me-1"></i> Submit</button>
        </div>
      </form>
    </div>
  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded',function(){
 const btnSubmit=document.getElementById('btnSubmit');
 const submitForm=document.getElementById('submitForm');
 const docForm=document.getElementById('docForm');
 const modal=new bootstrap.Modal(document.getElementById('submitModal'));btnSubmit.onclick=()=>modal.show();
 submitForm.onsubmit=async e=>{e.preventDefault();const r=await fetch('ajax_submit.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:new FormData(submitForm)}).then(x=>x.json());if(r.success)location.href='<?= e(APP_URL) ?>/modules/reviews/view.php?id='+r.review_id;else Swal.fire('Submission Error',r.message,'error');};
 docForm.onsubmit=async e=>{e.preventDefault();const r=await fetch('ajax_document_upload.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:new FormData(docForm)}).then(x=>x.json());if(r.success)location.reload();else Swal.fire('Upload Error',r.message,'error');};
 document.querySelectorAll('.doc-delete').forEach(b=>b.onclick=async function(){const ask=await Swal.fire({title:'Delete document?',showCancelButton:true,icon:'warning'});if(!ask.isConfirmed)return;const fd=new FormData();fd.append('csrf_token','<?= e(csrfToken()) ?>');fd.append('document_id',this.dataset.id);const r=await fetch('ajax_document_delete.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:fd}).then(x=>x.json());if(r.success)location.reload();else Swal.fire('Document Error',r.message,'error');});
});
</script>
<?php endif; ?>
<?php include __DIR__.'/../layouts/footer.php'; ?>
