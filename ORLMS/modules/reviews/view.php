<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/orlms_drafting_helpers.php';

requirePermission('orlms.reviews.view');

$pdo=db();$reviewId=(int)($_GET['id']??0);

$q=$pdo->prepare(
    'SELECT r.*,li.reference_number,li.title,li.current_status,li.priority_level,li.visibility,
            li.originating_office_id,lit.code item_type_code,lit.name item_type_name,
            d.subject,d.short_title,d.policy_area,d.legislative_category,d.purpose,
            d.explanatory_note,d.body_text,d.legal_basis,d.fiscal_impact,d.return_reason,
            c.name committee_name,u.full_name reviewer_name,o.name originating_office,
            e.id endorsement_id,e.endorsement_status,e.recommendation endorsement_recommendation,
            e.vote_summary,e.remarks endorsement_remarks,e.endorsed_by,e.endorsed_at
     FROM orlms_reviews r
     JOIN legislative_items li ON li.id=r.legislative_item_id
     JOIN legislative_item_types lit ON lit.id=li.item_type_id
     LEFT JOIN orlms_item_details d ON d.legislative_item_id=li.id
     LEFT JOIN committees c ON c.id=r.committee_id
     LEFT JOIN users u ON u.id=r.reviewer_id
     LEFT JOIN offices o ON o.id=li.originating_office_id
     LEFT JOIN orlms_endorsements e ON e.review_id=r.id
     WHERE r.id=:id AND li.deleted_at IS NULL'
);
$q->execute([':id'=>$reviewId]);$r=$q->fetch();
if(!$r){setFlash('warning','Review record not found.');redirect(APP_URL.'/modules/reviews/index.php');}

$itemId=(int)$r['legislative_item_id'];
$authors=orlmsDraftAuthors($pdo,$itemId);
$versions=orlmsDraftVersions($pdo,$itemId);
$documents=orlmsDraftDocuments($pdo,$itemId);
$workflow=orlmsWorkflow($pdo,$itemId);

$check=$pdo->prepare(
    'SELECT c.*,u.full_name reviewed_by_name
     FROM orlms_review_checklist c
     LEFT JOIN users u ON u.id=c.reviewed_by
     WHERE c.review_id=:id ORDER BY c.sequence_number,c.id'
);
$check->execute([':id'=>$reviewId]);$checks=$check->fetchAll();

$comments=$pdo->prepare(
    'SELECT c.*,u.full_name created_name,ru.full_name resolved_name
     FROM orlms_review_comments c
     LEFT JOIN users u ON u.id=c.created_by
     LEFT JOIN users ru ON ru.id=c.resolved_by
     WHERE c.review_id=:id ORDER BY c.created_at DESC,c.id DESC'
);
$comments->execute([':id'=>$reviewId]);$commentRows=$comments->fetchAll();

$committees=$pdo->query("SELECT id,name FROM committees WHERE status='Active' ORDER BY name")->fetchAll();
$users=$pdo->query(
    "SELECT u.id,u.full_name
     FROM users u JOIN roles r ON r.id=u.role_id
     WHERE u.status='Active' AND u.deleted_at IS NULL
       AND r.name IN ('Administrator','Legislative Staff','Committee Member')
     ORDER BY u.full_name"
)->fetchAll();

$pageTitle=$r['reference_number'].' Review';$activeMenu='reviews';
$extraCss=[APP_URL.'/assets/css/orlms-drafting-review.css'];
include __DIR__.'/../../layouts/header.php';
?>
<div class="orlms-app-layout"><?php include __DIR__.'/../../layouts/sidebar.php'; ?><main class="orlms-main-content">

<div class="orx-head"><div><a href="index.php" class="small text-decoration-none"><i class="bi bi-arrow-left"></i> Review Queue</a><div class="orx-eyebrow mt-2"><?= e($r['reference_number']) ?> · Review Round <?= (int)$r['review_round'] ?></div><h1><?= e($r['title']) ?></h1><p><?= e($r['item_type_name'].' · '.($r['committee_name']?:'No committee').' · '.($r['reviewer_name']?:'Committee queue')) ?></p></div><div class="d-flex gap-2 flex-wrap"><span class="orx-status <?= $r['status']==='Completed'?'good':'' ?>"><?= e($r['status']) ?></span><a class="btn btn-outline-secondary btn-sm" target="_blank" href="print.php?id=<?= $reviewId ?>"><i class="bi bi-printer"></i></a><?php if($r['item_type_code']==='ordinance'): ?><a class="btn btn-outline-primary btn-sm" href="<?= e(APP_URL.'/modules/ordinances/view.php?id='.$itemId) ?>">Draft Record</a><?php else: ?><a class="btn btn-outline-primary btn-sm" href="<?= e(APP_URL.'/modules/resolutions/view.php?id='.$itemId) ?>">Draft Record</a><?php endif; ?></div></div>

<div class="row g-3 mb-3">
<?php
$pendingChecks=count(array_filter($checks,fn($x)=>$x['check_status']==='Pending'));
$failedChecks=count(array_filter($checks,fn($x)=>$x['check_status']==='Failed'));
$openComments=count(array_filter($commentRows,fn($x)=>$x['status']==='Open'));
foreach([
 ['Checklist Pending',$pendingChecks,'bi-list-check'],['Checklist Failed',$failedChecks,'bi-x-octagon'],
 ['Open Comments',$openComments,'bi-chat-left-text'],['Draft Versions',count($versions),'bi-clock-history'],
] as [$l,$v,$i]): ?><div class="col-6 col-lg-3"><div class="orx-stat"><i class="bi <?= e($i) ?>"></i><div><strong><?= $v ?></strong><small><?= e($l) ?></small></div></div></div><?php endforeach; ?>
</div>

<div class="row g-3">
<div class="col-xl-8">
<div class="card orx-card mb-3"><div class="card-header">Submitted Legislative Draft</div><div class="card-body">
<div class="orx-grid mb-3"><div><small>Subject</small><strong><?= e($r['subject']?:'—') ?></strong></div><div><small>Originating Office</small><strong><?= e($r['originating_office']?:'—') ?></strong></div><div><small>Category</small><strong><?= e($r['legislative_category']?:'—') ?></strong></div><div><small>Policy Area</small><strong><?= e($r['policy_area']?:'—') ?></strong></div><div><small>Priority</small><strong><?= e($r['priority_level']) ?></strong></div><div><small>Current Item Status</small><strong><?= e($r['current_status']) ?></strong></div></div>
<?php if($r['explanatory_note']): ?><p class="small"><strong>Explanatory Note</strong><br><?= nl2br(e($r['explanatory_note'])) ?></p><?php endif; ?>
<div class="orx-body-preview"><?= e($r['body_text']?:'No draft body.') ?></div>
<div class="row g-3 mt-1"><div class="col-md-6"><div class="orx-alert"><strong>Legal Basis</strong><br><?= nl2br(e($r['legal_basis']?:'Not specified')) ?></div></div><div class="col-md-6"><div class="orx-alert"><strong>Fiscal / Implementation Impact</strong><br><?= nl2br(e($r['fiscal_impact']?:'Not specified')) ?></div></div></div>
</div></div>

<div class="card orx-card mb-3"><div class="card-header">Review Checklist</div><div>
<?php foreach($checks as $c): ?><form class="orx-review-check checklist-form"><?= csrfField() ?><input type="hidden" name="review_id" value="<?= $reviewId ?>"><input type="hidden" name="action" value="checklist"><input type="hidden" name="check_id" value="<?= (int)$c['id'] ?>"><div><strong><?= e($c['check_name']) ?></strong><div class="small text-muted"><?= e($c['check_code']) ?><?= $c['reviewed_by_name']?' · '.$c['reviewed_by_name']:'' ?></div></div><div><select class="form-select form-select-sm" name="check_status" <?= $r['status']==='Completed'?'disabled':'' ?>><?php foreach(['Pending','Passed','Warning','Failed','Not Applicable'] as $s): ?><option <?= $c['check_status']===$s?'selected':'' ?>><?= e($s) ?></option><?php endforeach; ?></select></div><div><input class="form-control form-control-sm" name="reviewer_note" value="<?= e($c['reviewer_note']?:'') ?>" placeholder="Reviewer note" <?= $r['status']==='Completed'?'disabled':'' ?>></div><div><?php if($r['status']!=='Completed'): ?><button class="btn btn-sm btn-outline-primary w-100">Save</button><?php else: ?><span class="badge text-bg-light"><?= e($c['check_status']) ?></span><?php endif; ?></div></form><?php endforeach; ?>
</div></div>

<div class="card orx-card mb-3"><div class="card-header d-flex justify-content-between"><span>Review Comments / Revision Requests</span><?php if($r['status']!=='Completed'): ?><button class="btn btn-sm btn-primary" id="btnComment"><i class="bi bi-plus"></i> Add Comment</button><?php endif; ?></div><div class="card-body d-grid gap-2">
<?php if(!$commentRows): ?><div class="text-muted small">No review comments recorded.</div><?php endif; ?>
<?php foreach($commentRows as $c): ?><div class="orx-comment"><div class="d-flex justify-content-between gap-2"><strong><?= e($c['comment_type']) ?><?= $c['section_reference']?' · '.e($c['section_reference']):'' ?></strong><span class="badge text-bg-<?= $c['status']==='Resolved'?'success':'warning' ?>"><?= e($c['status']) ?></span></div><p><?= nl2br(e($c['comment_text'])) ?></p><small><?= e($c['created_name']?:'System') ?> · <?= formatDateTime($c['created_at']) ?></small><?php if($c['resolution_text']): ?><div class="small mt-2"><strong>Resolution:</strong> <?= e($c['resolution_text']) ?><?= $c['resolved_name']?' · '.e($c['resolved_name']):'' ?></div><?php endif; ?><?php if($c['status']==='Open'&&$r['status']!=='Completed'): ?><div class="mt-2"><button class="btn btn-sm btn-outline-success resolve-comment" data-id="<?= (int)$c['id'] ?>">Resolve</button></div><?php endif; ?></div><?php endforeach; ?>
</div></div>

<div class="card orx-card"><div class="card-header">Documents / Review Evidence</div><div class="card-body"><div class="list-group mb-3"><?php if(!$documents): ?><div class="list-group-item text-muted">No documents attached.</div><?php endif; ?><?php foreach($documents as $doc): ?><a class="list-group-item list-group-item-action" target="_blank" href="<?= e(UPLOAD_URL.$doc['file_path']) ?>"><strong><?= e($doc['title']) ?></strong><div class="small text-muted"><?= e($doc['document_type'].' · v'.$doc['version_number'].' · '.$doc['visibility']) ?> · <?= formatDateTime($doc['created_at']) ?></div></a><?php endforeach; ?></div><form id="reviewDocForm" class="row g-2" enctype="multipart/form-data"><?= csrfField() ?><input type="hidden" name="review_id" value="<?= $reviewId ?>"><div class="col-md-5"><input type="file" class="form-control form-control-sm" name="document" required></div><div class="col-md-3"><input class="form-control form-control-sm" name="document_type" value="Review Evidence"></div><div class="col-md-2"><input class="form-control form-control-sm" name="document_title" placeholder="Title"></div><div class="col-md-2"><select class="form-select form-select-sm" name="visibility"><option>Internal</option><option>Restricted</option><option>Public</option></select></div><div class="col-12"><button class="btn btn-outline-primary btn-sm"><i class="bi bi-upload"></i> Upload Review Evidence</button></div></form></div></div>
</div>

<div class="col-xl-4">
<div class="card orx-card mb-3"><div class="card-header">Review Controls</div><div class="card-body">
<?php if($r['status']==='Pending'): ?><button class="btn btn-primary w-100 mb-2" id="btnStart"><i class="bi bi-play-circle"></i> Start Review</button><?php endif; ?>
<?php if($r['status']!=='Completed'): ?><?php if(hasRole([ROLE_ADMIN,ROLE_STAFF])): ?><button class="btn btn-outline-secondary w-100 mb-2" id="btnAssign"><i class="bi bi-person-check"></i> Assignment</button><?php endif; ?><button class="btn btn-success w-100" id="btnComplete"><i class="bi bi-check2-circle"></i> Complete Review</button><?php else: ?><div class="orx-grid"><div><small>Recommendation</small><strong><?= e($r['recommendation']?:'—') ?></strong></div><div><small>Completed</small><strong><?= formatDateTime($r['completed_at']) ?></strong></div></div><?php if($r['comments']): ?><div class="orx-alert mt-3"><?= nl2br(e($r['comments'])) ?></div><?php endif; ?><?php endif; ?>
</div></div>

<div class="card orx-card mb-3"><div class="card-header">Committee Endorsement</div><div class="card-body">
<?php if($r['endorsement_id']): ?><div class="orx-grid"><div><small>Status</small><strong><?= e($r['endorsement_status']) ?></strong></div><div><small>Recommendation</small><strong><?= e($r['endorsement_recommendation']?:'—') ?></strong></div><div><small>Vote Summary</small><strong><?= e($r['vote_summary']?:'—') ?></strong></div><div><small>Date</small><strong><?= formatDateTime($r['endorsed_at']) ?></strong></div></div><?php if($r['endorsement_remarks']): ?><div class="orx-alert mt-3"><?= nl2br(e($r['endorsement_remarks'])) ?></div><?php endif; ?><?php endif; ?>
<?php if($r['status']==='Completed'&&$r['recommendation']!=='Return for Revision'): ?><button class="btn btn-warning w-100 mt-3" id="btnEndorse"><i class="bi bi-patch-check"></i> <?= $r['endorsement_id']?'Update Endorsement':'Record Endorsement' ?></button><?php elseif(!$r['endorsement_id']): ?><div class="small text-muted"><?= $r['status']==='Completed'?'This review returned the draft for revision.':'Complete the review before committee endorsement.' ?></div><?php endif; ?>
</div></div>

<div class="card orx-card mb-3"><div class="card-header">Draft Versions</div><div class="card-body orx-history"><?php foreach($versions as $v): ?><div><strong>Version <?= (int)$v['version_number'] ?></strong><small><?= e($v['change_summary']?:'Draft snapshot') ?><br><?= formatDateTime($v['created_at']) ?></small></div><?php endforeach; ?></div></div>

<div class="card orx-card"><div class="card-header">Workflow History</div><div class="card-body orx-history"><?php foreach($workflow as $h): ?><div><strong><?= e($h['stage_code'].' · '.$h['action']) ?></strong><small><?= e(($h['previous_status']?:'—').' → '.($h['new_status']?:'—')) ?><?= $h['remarks']?'<br>'.e($h['remarks']):'' ?><br><?= formatDateTime($h['created_at']) ?></small></div><?php endforeach; ?></div></div>
</div>
</div>
</main></div>

<div class="modal fade" id="assignModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" style="max-width: 560px;">
    <div class="modal-content shadow-lg border-0 rounded-3 overflow-hidden">
      <form id="assignForm">
        <?= csrfField() ?>
        <input type="hidden" name="review_id" value="<?= $reviewId ?>">
        <input type="hidden" name="action" value="assign">
        <div class="modal-header py-2 px-3 border-0" style="background: linear-gradient(135deg, #0f172a, #1e293b); color: #fff;">
          <div class="d-flex align-items-center gap-2">
            <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 28px; height: 28px; background: rgba(59,130,246,0.25); color: #60a5fa; font-size: 12px;">
              <i class="bi bi-people"></i>
            </div>
            <h6 class="modal-title fw-bold m-0" style="font-size: 0.95rem;">Review Assignment</h6>
          </div>
          <button class="btn-close btn-close-white" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body p-3">
          <div class="row g-2">
            <div class="col-12">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Committee</label>
              <select class="form-select form-select-sm" name="committee_id" required>
                <?php foreach($committees as $c): ?>
                  <option value="<?= (int)$c['id'] ?>" <?= (int)$r['committee_id']===(int)$c['id']?'selected':'' ?>><?= e($c['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Reviewer</label>
              <select class="form-select form-select-sm" name="reviewer_id">
                <option value="">Committee queue</option>
                <?php foreach($users as $u): ?>
                  <option value="<?= (int)$u['id'] ?>" <?= (int)$r['reviewer_id']===(int)$u['id']?'selected':'' ?>><?= e($u['full_name']) ?></option>
                <?php endforeach; ?>
              </select>
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

<div class="modal fade" id="commentModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" style="max-width: 580px;">
    <div class="modal-content shadow-lg border-0 rounded-3 overflow-hidden">
      <form id="commentForm">
        <?= csrfField() ?>
        <input type="hidden" name="review_id" value="<?= $reviewId ?>">
        <input type="hidden" name="action" value="comment">
        <div class="modal-header py-2 px-3 border-0" style="background: linear-gradient(135deg, #0f172a, #1e293b); color: #fff;">
          <div class="d-flex align-items-center gap-2">
            <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 28px; height: 28px; background: rgba(14,165,233,0.25); color: #38bdf8; font-size: 12px;">
              <i class="bi bi-chat-left-text"></i>
            </div>
            <h6 class="modal-title fw-bold m-0" style="font-size: 0.95rem;">Add Review Comment</h6>
          </div>
          <button class="btn-close btn-close-white" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body p-3">
          <div class="row g-2">
            <div class="col-md-6">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Type</label>
              <select class="form-select form-select-sm" name="comment_type">
                <option>Comment</option>
                <option>Revision Request</option>
                <option>Legal Note</option>
                <option>Committee Note</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Section / Reference</label>
              <input class="form-control form-control-sm" name="section_reference" placeholder="e.g. Section 4">
            </div>
            <div class="col-12">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Comment *</label>
              <textarea class="form-control form-control-sm" name="comment_text" rows="3" required style="resize: vertical; min-height: 65px;"></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer py-2 px-3 bg-light border-top">
          <button class="btn btn-sm btn-outline-secondary px-3" type="button" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-sm btn-primary px-4 fw-semibold"><i class="bi bi-plus-lg me-1"></i>Add Comment</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="completeModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" style="max-width: 600px;">
    <div class="modal-content shadow-lg border-0 rounded-3 overflow-hidden">
      <form id="completeForm">
        <?= csrfField() ?>
        <input type="hidden" name="review_id" value="<?= $reviewId ?>">
        <input type="hidden" name="action" value="complete">
        <div class="modal-header py-2 px-3 border-0" style="background: linear-gradient(135deg, #0f172a, #1e293b); color: #fff;">
          <div class="d-flex align-items-center gap-2">
            <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 28px; height: 28px; background: rgba(16,185,129,0.25); color: #34d399; font-size: 12px;">
              <i class="bi bi-check-circle"></i>
            </div>
            <h6 class="modal-title fw-bold m-0" style="font-size: 0.95rem;">Complete Committee Review</h6>
          </div>
          <button class="btn-close btn-close-white" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body p-3">
          <div class="row g-2">
            <div class="col-12">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Recommendation *</label>
              <select class="form-select form-select-sm" name="recommendation" required>
                <option value="">Select recommendation</option>
                <option>Endorse</option>
                <option>Endorse with Conditions</option>
                <option>Return for Revision</option>
                <option>Do Not Endorse</option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Committee / Reviewer Comments</label>
              <textarea class="form-control form-control-sm" name="comments" rows="3" placeholder="Required details, especially when returning for revision" style="resize: vertical; min-height: 65px;"></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer py-2 px-3 bg-light border-top">
          <button class="btn btn-sm btn-outline-secondary px-3" type="button" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-sm btn-success px-4 fw-semibold"><i class="bi bi-check2-all me-1"></i>Complete Review</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="endorseModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" style="max-width: 600px;">
    <div class="modal-content shadow-lg border-0 rounded-3 overflow-hidden">
      <form id="endorseForm">
        <?= csrfField() ?>
        <input type="hidden" name="review_id" value="<?= $reviewId ?>">
        <div class="modal-header py-2 px-3 border-0" style="background: linear-gradient(135deg, #0f172a, #1e293b); color: #fff;">
          <div class="d-flex align-items-center gap-2">
            <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 28px; height: 28px; background: rgba(245,158,11,0.25); color: #fbbf24; font-size: 12px;">
              <i class="bi bi-award"></i>
            </div>
            <h6 class="modal-title fw-bold m-0" style="font-size: 0.95rem;">Committee Endorsement</h6>
          </div>
          <button class="btn-close btn-close-white" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body p-3">
          <div class="row g-2">
            <div class="col-md-6">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Endorsement Status *</label>
              <select class="form-select form-select-sm" name="endorsement_status" required>
                <option>Endorsed</option>
                <option>Not Endorsed</option>
                <option>Deferred</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Vote Summary</label>
              <input class="form-control form-control-sm" name="vote_summary" value="<?= e($r['vote_summary']?:'') ?>" placeholder="e.g. 5-0-1">
            </div>
            <div class="col-12">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Committee Recommendation</label>
              <input class="form-control form-control-sm" name="recommendation" value="<?= e($r['endorsement_recommendation']?:$r['recommendation']?:'') ?>">
            </div>
            <div class="col-12">
              <label class="form-label small fw-bold text-secondary text-uppercase mb-1" style="font-size: 0.72rem;">Remarks</label>
              <textarea class="form-control form-control-sm" name="remarks" rows="2" style="resize: vertical; min-height: 50px;"><?= e($r['endorsement_remarks']?:'') ?></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer py-2 px-3 bg-light border-top">
          <button class="btn btn-sm btn-outline-secondary px-3" type="button" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-sm btn-warning px-4 fw-semibold text-dark"><i class="bi bi-check2 me-1"></i>Save Endorsement</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded',function(){
 const assignForm=document.getElementById('assignForm');
 const commentForm=document.getElementById('commentForm');
 const completeForm=document.getElementById('completeForm');
 const endorseForm=document.getElementById('endorseForm');
 const reviewDocForm=document.getElementById('reviewDocForm');
 const post=async form=>fetch('ajax_review.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:new FormData(form)}).then(x=>x.json());
 const bind=async(form)=>{const r=await post(form);if(r.success)location.reload();else Swal.fire('Review Error',r.message,'error');};
 document.querySelectorAll('.checklist-form').forEach(f=>f.onsubmit=e=>{e.preventDefault();bind(f);});
 const start=document.getElementById('btnStart');if(start)start.onclick=async()=>{const fd=new FormData();fd.append('csrf_token','<?= e(csrfToken()) ?>');fd.append('review_id','<?= $reviewId ?>');fd.append('action','start');const r=await fetch('ajax_review.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:fd}).then(x=>x.json());if(r.success)location.reload();else Swal.fire('Review Error',r.message,'error');};
 const assignModal=new bootstrap.Modal(document.getElementById('assignModal'));const assign=document.getElementById('btnAssign');if(assign)assign.onclick=()=>assignModal.show();assignForm.onsubmit=e=>{e.preventDefault();bind(assignForm);};
 const commentModal=new bootstrap.Modal(document.getElementById('commentModal'));const comment=document.getElementById('btnComment');if(comment)comment.onclick=()=>{commentForm.reset();commentModal.show();};commentForm.onsubmit=e=>{e.preventDefault();bind(commentForm);};
 document.querySelectorAll('.resolve-comment').forEach(b=>b.onclick=async function(){const p=await Swal.fire({title:'Resolve comment',input:'textarea',inputLabel:'Resolution',showCancelButton:true,inputValidator:v=>!v?'Resolution is required':undefined});if(!p.isConfirmed)return;const fd=new FormData();fd.append('csrf_token','<?= e(csrfToken()) ?>');fd.append('review_id','<?= $reviewId ?>');fd.append('action','resolve_comment');fd.append('comment_id',this.dataset.id);fd.append('resolution_text',p.value);const r=await fetch('ajax_review.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:fd}).then(x=>x.json());if(r.success)location.reload();else Swal.fire('Review Error',r.message,'error');});
 const completeModal=new bootstrap.Modal(document.getElementById('completeModal'));const complete=document.getElementById('btnComplete');if(complete)complete.onclick=()=>completeModal.show();completeForm.onsubmit=e=>{e.preventDefault();bind(completeForm);};
 const endorseModal=new bootstrap.Modal(document.getElementById('endorseModal'));const endorse=document.getElementById('btnEndorse');if(endorse)endorse.onclick=()=>endorseModal.show();endorseForm.onsubmit=async e=>{e.preventDefault();const r=await fetch('ajax_endorse.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:new FormData(endorseForm)}).then(x=>x.json());if(r.success){await Swal.fire('Endorsement Saved',r.message,'success');location.reload();}else Swal.fire('Endorsement Error',r.message,'error');};
 reviewDocForm.onsubmit=async e=>{e.preventDefault();const r=await fetch('ajax_upload_document.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:new FormData(reviewDocForm)}).then(x=>x.json());if(r.success)location.reload();else Swal.fire('Upload Error',r.message,'error');};
});
</script>
<?php include __DIR__.'/../../layouts/footer.php'; ?>
