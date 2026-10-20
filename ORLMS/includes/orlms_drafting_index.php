<?php
declare(strict_types=1);

if(!isset($orlmsItemTypeCode,$orlmsActiveMenu,$orlmsStepLabel)){
    throw new RuntimeException('ORLMS drafting page configuration is missing.');
}

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/orlms_drafting_helpers.php';
requirePermission('orlms.drafting.manage');

$pdo=db();
$typeCode=$orlmsItemTypeCode;
$label=orlmsItemTypeLabel($typeCode);

$pageTitle=$label.' Drafting';
$activeMenu=$orlmsActiveMenu;
$extraCss=[APP_URL.'/assets/css/orlms-drafting-review.css'];

$search=clean($_GET['search']??'');
$status=clean($_GET['status']??'');
$priority=clean($_GET['priority']??'');
$officeId=(int)($_GET['office_id']??0);

$params=[':type'=>$typeCode];
$where=["lit.code=:type","li.deleted_at IS NULL"];

if($search!==''){
    $where[]='(li.reference_number LIKE :s1 OR li.title LIKE :s2 OR d.subject LIKE :s3 OR d.keywords LIKE :s4)';
    $like='%'.$search.'%';
    $params[':s1']=$like;$params[':s2']=$like;$params[':s3']=$like;$params[':s4']=$like;
}
if($status!==''){$where[]='li.current_status=:status';$params[':status']=$status;}
if($priority!==''){$where[]='li.priority_level=:priority';$params[':priority']=$priority;}
if($officeId>0){$where[]='li.originating_office_id=:office';$params[':office']=$officeId;}

$stmt=$pdo->prepare(
    'SELECT li.id,li.reference_number,li.title,li.current_status,li.priority_level,
            li.visibility,li.created_at,li.updated_at,o.name originating_office,
            d.short_title,d.subject,d.policy_area,d.draft_status,d.submitted_at,
            (SELECT COUNT(*) FROM orlms_item_authors a WHERE a.legislative_item_id=li.id) author_count,
            (SELECT COUNT(*) FROM orlms_draft_versions v WHERE v.legislative_item_id=li.id) version_count,
            (SELECT COUNT(*) FROM documents doc WHERE doc.legislative_item_id=li.id AND (doc.system_id=:system OR doc.system_id IS NULL)) document_count,
            (SELECT MAX(r.id) FROM orlms_reviews r WHERE r.legislative_item_id=li.id) latest_review_id
     FROM legislative_items li
     JOIN legislative_item_types lit ON lit.id=li.item_type_id
     LEFT JOIN offices o ON o.id=li.originating_office_id
     LEFT JOIN orlms_item_details d ON d.legislative_item_id=li.id
     WHERE '.implode(' AND ',$where).'
     ORDER BY li.updated_at DESC,li.id DESC'
);
$params[':system']=orlmsSystemId();
$stmt->execute($params);
$items=$stmt->fetchAll();

$typeId=orlmsItemTypeId($pdo,$typeCode);
$templateStmt=$pdo->prepare(
    'SELECT id,template_code,template_name,description,template_body
     FROM orlms_templates
     WHERE item_type_id=:type AND status="Active"
     ORDER BY template_name'
);
$templateStmt->execute([':type'=>$typeId]);
$templates=$templateStmt->fetchAll();

$offices=$pdo->query("SELECT id,name FROM offices WHERE status='Active' ORDER BY name")->fetchAll();
$users=$pdo->query(
    "SELECT u.id,u.full_name,u.office_id,o.name office_name
     FROM users u
     JOIN roles r ON r.id=u.role_id
     LEFT JOIN offices o ON o.id=u.office_id
     WHERE u.status='Active' AND u.deleted_at IS NULL
       AND r.name IN ('Administrator','Legislative Staff','Committee Member')
     ORDER BY u.full_name"
)->fetchAll();
$committees=$pdo->query("SELECT id,name FROM committees WHERE status='Active' ORDER BY name")->fetchAll();

$statsStmt=$pdo->prepare(
    "SELECT
      COUNT(*) total,
      SUM(li.current_status='Draft') drafts,
      SUM(li.current_status IN ('Returned','Revision Required')) revisions,
      SUM(li.current_status='Submitted for Review') submitted
     FROM legislative_items li
     JOIN legislative_item_types lit ON lit.id=li.item_type_id
     WHERE lit.code=:type AND li.deleted_at IS NULL"
);
$statsStmt->execute([':type'=>$typeCode]);$stats=$statsStmt->fetch()?:[];

include __DIR__.'/../layouts/header.php';
?>
<div class="orlms-app-layout">
<?php include __DIR__.'/../layouts/sidebar.php'; ?>
<main class="orlms-main-content">

<div class="orx-head">
<div>
<div class="orx-eyebrow"><i class="bi <?= $typeCode==='resolution'?'bi-file-earmark-arrow-up':'bi-file-earmark-text' ?>"></i> <?= e($orlmsStepLabel) ?></div>
<h1><?= e($label) ?> Drafting <?= $typeCode==='resolution'?'& Submission':'& Encoding' ?></h1>
<p><?= $typeCode==='resolution'
    ? 'Create structured resolution drafts, encode sponsors and supporting information, preserve version history, then submit the completed resolution to committee review.'
    : 'Create and encode ordinance drafts using standardized templates, authors, legislative metadata, document attachments and version history before committee review.' ?></p>
</div>
<button class="btn btn-primary btn-sm px-3 shadow-sm" id="btnNewDraft"><i class="bi bi-plus-lg me-1"></i> New <?= e($label) ?> Draft</button>
</div>

<div class="orx-flow">
<div class="orx-step-item active"><div class="orx-step-badge"><i class="bi bi-file-earmark-plus"></i></div><div class="orx-step-content"><strong>1. Create Draft</strong><small>Generate ORLMS reference</small></div></div>
<div class="orx-step-divider"><i class="bi bi-chevron-right"></i></div>
<div class="orx-step-item"><div class="orx-step-badge"><i class="bi bi-input-cursor-text"></i></div><div class="orx-step-content"><strong>2. Encode Details</strong><small>Metadata & Sponsors</small></div></div>
<div class="orx-step-divider"><i class="bi bi-chevron-right"></i></div>
<div class="orx-step-item"><div class="orx-step-badge"><i class="bi bi-file-earmark-text"></i></div><div class="orx-step-content"><strong>3. Draft Content</strong><small>Template & Text</small></div></div>
<div class="orx-step-divider"><i class="bi bi-chevron-right"></i></div>
<div class="orx-step-item"><div class="orx-step-badge"><i class="bi bi-clock-history"></i></div><div class="orx-step-content"><strong>4. Version & Evidence</strong><small>Snapshots & Docs</small></div></div>
<div class="orx-step-divider"><i class="bi bi-chevron-right"></i></div>
<div class="orx-step-item"><div class="orx-step-badge"><i class="bi bi-send-check"></i></div><div class="orx-step-content"><strong>5. Submit</strong><small>Route to Review</small></div></div>
</div>

<div class="row g-3 mb-3">
<?php foreach([
 ['All '.$label.'s',$stats['total']??0,'bi-files','#071426'],
 ['Active Drafts',$stats['drafts']??0,'bi-pencil-square','#0284c7'],
 ['Revision Required',$stats['revisions']??0,'bi-arrow-repeat','#d97706'],
 ['Submitted',$stats['submitted']??0,'bi-send-check','#059669'],
] as [$l,$v,$i,$c]): ?>
<div class="col-6 col-lg-3"><div class="orx-stat-card" style="border-left-color: <?= $c ?>;"><div class="orx-stat-icon" style="background: <?= $c ?>15; color: <?= $c ?>;"><i class="bi <?= e($i) ?>"></i></div><div class="orx-stat-info"><strong><?= (int)$v ?></strong><small><?= e($l) ?></small></div></div></div>
<?php endforeach; ?>
</div>

<div class="card orx-card mb-3"><div class="card-body">
<form class="row g-2 align-items-end">
<div class="col-xl-4"><label class="form-label small">Search</label><input class="form-control form-control-sm" name="search" value="<?= e($search) ?>" placeholder="Reference, title, subject or keyword"></div>
<div class="col-xl-2"><label class="form-label small">Status</label><select class="form-select form-select-sm" name="status"><option value="">All statuses</option><?php foreach(['Draft','Returned','Revision Required','Submitted for Review','Under Review','Committee Reviewed','Endorsed','Not Endorsed'] as $s): ?><option <?= $status===$s?'selected':'' ?>><?= e($s) ?></option><?php endforeach; ?></select></div>
<div class="col-xl-2"><label class="form-label small">Priority</label><select class="form-select form-select-sm" name="priority"><option value="">All priorities</option><?php foreach(['Low','Normal','High','Urgent'] as $p): ?><option <?= $priority===$p?'selected':'' ?>><?= e($p) ?></option><?php endforeach; ?></select></div>
<div class="col-xl-2"><label class="form-label small">Office</label><select class="form-select form-select-sm" name="office_id"><option value="">All offices</option><?php foreach($offices as $o): ?><option value="<?= (int)$o['id'] ?>" <?= $officeId===(int)$o['id']?'selected':'' ?>><?= e($o['name']) ?></option><?php endforeach; ?></select></div>
<div class="col-xl-2"><button class="btn btn-outline-primary btn-sm w-100"><i class="bi bi-funnel"></i> Apply</button></div>
</form>
</div></div>

<div class="card orx-card"><div class="card-header d-flex justify-content-between"><span><?= e($label) ?> Draft Registry</span><span><?= count($items) ?> record(s)</span></div>
<div class="table-responsive"><table class="table table-hover orx-table mb-0">
<thead><tr><th>Reference / Title</th><th>Office</th><th>Subject / Policy</th><th>Authors</th><th>Versions / Docs</th><th>Priority</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
<tbody>
<?php if(!$items): ?><tr><td colspan="8" class="text-center text-muted py-5">No <?= e(strtolower($label)) ?> drafts found.</td></tr><?php endif; ?>
<?php foreach($items as $item): ?>
<tr>
<td><span class="orx-code"><?= e($item['reference_number']) ?></span><div><strong><?= e($item['title']) ?></strong></div><div class="small text-muted"><?= e($item['short_title']?:'') ?></div></td>
<td><?= e($item['originating_office']?:'Not assigned') ?></td>
<td><?= e($item['subject']?:'—') ?><div class="small text-muted"><?= e($item['policy_area']?:'Unclassified') ?></div></td>
<td><?= (int)$item['author_count'] ?></td>
<td><?= (int)$item['version_count'] ?> version(s) · <?= (int)$item['document_count'] ?> file(s)</td>
<td><?= e($item['priority_level']) ?></td>
<td><span class="orx-status <?= in_array($item['current_status'],['Endorsed','Committee Reviewed'],true)?'good':(in_array($item['current_status'],['Revision Required','Returned','Not Endorsed'],true)?'bad':'') ?>"><?= e($item['current_status']) ?></span></td>
<td class="text-end"><div class="btn-group btn-group-sm">
<a class="btn btn-outline-primary" href="view.php?id=<?= (int)$item['id'] ?>"><i class="bi bi-eye"></i></a>
<?php if(orlmsCanEditDraft($item['current_status'])): ?><button class="btn btn-outline-secondary edit-draft" data-id="<?= (int)$item['id'] ?>"><i class="bi bi-pencil"></i></button><button class="btn btn-outline-success submit-draft" data-id="<?= (int)$item['id'] ?>" data-ref="<?= e($item['reference_number']) ?>"><i class="bi bi-send"></i></button><button class="btn btn-outline-danger delete-draft" data-id="<?= (int)$item['id'] ?>"><i class="bi bi-trash"></i></button><?php elseif($item['latest_review_id']): ?><a class="btn btn-outline-success" href="<?= e(APP_URL.'/modules/reviews/view.php?id='.$item['latest_review_id']) ?>"><i class="bi bi-clipboard-check"></i></a><?php endif; ?>
</div></td>
</tr>
<?php endforeach; ?>
</tbody></table></div></div>
</main></div>

<div class="modal fade" id="draftModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" style="max-width: 680px;">
    <div class="modal-content border-0 shadow-lg" style="border-radius: 14px; overflow: hidden;">
      <form id="draftForm" enctype="multipart/form-data">
        <?= csrfField() ?>
        <input type="hidden" name="id" id="d_id" value="0">
        <div class="modal-header py-2.5 px-3.5" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); border-bottom: 2px solid #3b82f6;">
          <div class="d-flex align-items-center gap-2">
            <div class="rounded-circle d-flex align-items-center justify-content-center bg-primary bg-opacity-25 text-info" style="width: 32px; height: 32px;">
              <i class="bi bi-file-earmark-text fs-6"></i>
            </div>
            <div>
              <h6 class="modal-title text-white fw-bold mb-0" style="font-size: 0.95rem;"><?= e($label) ?> Draft Workspace</h6>
              <small class="text-white-50" style="font-size: 0.72rem;">Prepare legislative ordinance or resolution drafting record</small>
            </div>
          </div>
          <button class="btn-close btn-close-white" type="button" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-3.5">
          <div class="row g-2">
            <div class="col-lg-8"><label class="form-label small fw-semibold text-secondary mb-1">Official Title *</label><input class="form-control form-control-sm" name="title" id="d_title" required></div>
            <div class="col-lg-4"><label class="form-label small fw-semibold text-secondary mb-1">Short Title</label><input class="form-control form-control-sm" name="short_title" id="d_short"></div>
            <div class="col-md-4"><label class="form-label small fw-semibold text-secondary mb-1">Originating Office</label><select class="form-select form-select-sm" name="originating_office_id" id="d_office"><option value="">Not assigned</option><?php foreach($offices as $o): ?><option value="<?= (int)$o['id'] ?>"><?= e($o['name']) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-4"><label class="form-label small fw-semibold text-secondary mb-1">Priority</label><select class="form-select form-select-sm" name="priority_level" id="d_priority"><option>Low</option><option selected>Normal</option><option>High</option><option>Urgent</option></select></div>
            <div class="col-md-4"><label class="form-label small fw-semibold text-secondary mb-1">Visibility</label><select class="form-select form-select-sm" name="visibility" id="d_visibility"><option>Internal</option><option>Restricted</option><option>Public</option></select></div>
            <div class="col-md-6"><label class="form-label small fw-semibold text-secondary mb-1">Subject *</label><input class="form-control form-control-sm" name="subject" id="d_subject" required></div>
            <div class="col-md-3"><label class="form-label small fw-semibold text-secondary mb-1">Legislative Category</label><input class="form-control form-control-sm" name="legislative_category" id="d_category" placeholder="e.g. Regulatory"></div>
            <div class="col-md-3"><label class="form-label small fw-semibold text-secondary mb-1">Policy Area</label><input class="form-control form-control-sm" name="policy_area" id="d_policy" placeholder="e.g. Public Health"></div>
            <div class="col-12"><label class="form-label small fw-semibold text-secondary mb-1">Summary</label><textarea class="form-control form-control-sm" name="summary" id="d_summary" rows="2"></textarea></div>
            <div class="col-md-6"><label class="form-label small fw-semibold text-secondary mb-1">Purpose</label><textarea class="form-control form-control-sm" name="purpose" id="d_purpose" rows="2"></textarea></div>
            <div class="col-md-6"><label class="form-label small fw-semibold text-secondary mb-1">Explanatory Note</label><textarea class="form-control form-control-sm" name="explanatory_note" id="d_note" rows="2"></textarea></div>
            <div class="col-md-4"><label class="form-label small fw-semibold text-secondary mb-1">Drafting Template</label><select class="form-select form-select-sm" name="template_id" id="d_template"><option value="">No template</option><?php foreach($templates as $t): ?><option value="<?= (int)$t['id'] ?>" data-body="<?= e($t['template_body']) ?>"><?= e($t['template_name'].' · '.$t['template_code']) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-4"><label class="form-label small fw-semibold text-secondary mb-1">Legal Basis</label><textarea class="form-control form-control-sm" name="legal_basis" id="d_legal" rows="2"></textarea></div>
            <div class="col-md-4"><label class="form-label small fw-semibold text-secondary mb-1">Fiscal / Resource Impact</label><textarea class="form-control form-control-sm" name="fiscal_impact" id="d_fiscal" rows="2"></textarea></div>
            <div class="col-12"><label class="form-label small fw-semibold text-secondary mb-1">Draft Body *</label><textarea class="form-control form-control-sm font-monospace" name="body_text" id="d_body" rows="6" style="resize: vertical; min-height: 110px; max-height: 240px;"></textarea><div class="form-text small" style="font-size:0.7rem;">Selecting a template can populate this workspace when it is empty.</div></div>
            <div class="col-12"><label class="form-label small fw-semibold text-secondary mb-1">Keywords</label><input class="form-control form-control-sm" name="keywords" id="d_keywords" placeholder="comma-separated keywords"></div>
          </div>
          <hr class="my-2.5">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="badge bg-secondary-subtle text-secondary" style="font-size: 0.68rem; letter-spacing: 0.5px;">AUTHORS / SPONSORS / PROPONENTS</span>
            <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2" id="btnAddAuthor" style="font-size: 0.78rem;"><i class="bi bi-plus"></i> Add Person</button>
          </div>
          <div id="authorRows"></div>
          <hr class="my-2.5">
          <div class="row g-2">
            <div class="col-md-5"><label class="form-label small fw-semibold text-secondary mb-1">Supporting Document</label><input type="file" class="form-control form-control-sm" name="supporting_document"></div>
            <div class="col-md-3"><label class="form-label small fw-semibold text-secondary mb-1">Document Type</label><input class="form-control form-control-sm" name="document_type" value="Supporting Document"></div>
            <div class="col-md-2"><label class="form-label small fw-semibold text-secondary mb-1">Document Title</label><input class="form-control form-control-sm" name="document_title"></div>
            <div class="col-md-2"><label class="form-label small fw-semibold text-secondary mb-1">Visibility</label><select class="form-select form-select-sm" name="document_visibility"><option>Internal</option><option>Restricted</option><option>Public</option></select></div>
            <div class="col-12"><label class="form-label small fw-semibold text-secondary mb-1">Change Summary</label><input class="form-control form-control-sm" name="change_summary" id="d_change" placeholder="Describe what changed in this saved version"></div>
          </div>
        </div>
        <div class="modal-footer py-2 px-3.5 bg-light border-top d-flex justify-content-end gap-2">
          <button class="btn btn-sm btn-outline-secondary px-3" type="button" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-sm btn-primary px-3 fw-semibold shadow-sm" id="btnSaveDraft"><i class="bi bi-floppy me-1"></i> Save Draft</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="submitModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered" style="max-width: 580px;">
    <div class="modal-content border-0 shadow-lg" style="border-radius: 14px; overflow: hidden;">
      <form id="submitForm">
        <?= csrfField() ?>
        <input type="hidden" name="id" id="s_id">
        <div class="modal-header py-2.5 px-3.5" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); border-bottom: 2px solid #3b82f6;">
          <div class="d-flex align-items-center gap-2">
            <div class="rounded-circle d-flex align-items-center justify-content-center bg-primary bg-opacity-25 text-info" style="width: 32px; height: 32px;">
              <i class="bi bi-send-check fs-6"></i>
            </div>
            <div>
              <h6 class="modal-title text-white fw-bold mb-0" style="font-size: 0.95rem;">Submit for Committee Review</h6>
              <small class="text-white-50" style="font-size: 0.72rem;">Refer draft to designated legislative committee</small>
            </div>
          </div>
          <button class="btn-close btn-close-white" type="button" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-3.5">
          <div id="submitSource" class="orx-alert mb-2.5 small"></div>
          <div class="row g-2">
            <div class="col-md-6"><label class="form-label small fw-semibold text-secondary mb-1">Reviewing Committee *</label><select class="form-select form-select-sm" name="committee_id" required><option value="">Select committee</option><?php foreach($committees as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-6"><label class="form-label small fw-semibold text-secondary mb-1">Assigned Reviewer</label><select class="form-select form-select-sm" name="reviewer_id"><option value="">Committee queue / unassigned</option><?php foreach($users as $u): ?><option value="<?= (int)$u['id'] ?>"><?= e($u['full_name'].($u['office_name']?' · '.$u['office_name']:'')) ?></option><?php endforeach; ?></select></div>
            <div class="col-12"><label class="form-label small fw-semibold text-secondary mb-1">Submission Note</label><textarea class="form-control form-control-sm" name="submission_note" rows="3" placeholder="Notes for the committee/reviewer"></textarea></div>
          </div>
        </div>
        <div class="modal-footer py-2 px-3.5 bg-light border-top d-flex justify-content-end gap-2">
          <button class="btn btn-sm btn-outline-secondary px-3" type="button" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-sm btn-success px-3 fw-semibold shadow-sm"><i class="bi bi-send-check me-1"></i> Submit Draft</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded',function(){
 const draftForm=document.getElementById('draftForm');
 const submitForm=document.getElementById('submitForm');
 const authorRows=document.getElementById('authorRows');
 const btnNewDraft=document.getElementById('btnNewDraft');
 const btnAddAuthor=document.getElementById('btnAddAuthor');
 const btnSaveDraft=document.getElementById('btnSaveDraft');
 const d_id=document.getElementById('d_id'),d_title=document.getElementById('d_title'),
       d_short=document.getElementById('d_short'),d_office=document.getElementById('d_office'),
       d_priority=document.getElementById('d_priority'),d_visibility=document.getElementById('d_visibility'),
       d_subject=document.getElementById('d_subject'),d_category=document.getElementById('d_category'),
       d_policy=document.getElementById('d_policy'),d_summary=document.getElementById('d_summary'),
       d_purpose=document.getElementById('d_purpose'),d_note=document.getElementById('d_note'),
       d_template=document.getElementById('d_template'),d_legal=document.getElementById('d_legal'),
       d_fiscal=document.getElementById('d_fiscal'),d_body=document.getElementById('d_body'),
       d_keywords=document.getElementById('d_keywords'),d_change=document.getElementById('d_change');
 const s_id=document.getElementById('s_id'),submitSource=document.getElementById('submitSource');
 const draftModal=new bootstrap.Modal(document.getElementById('draftModal'));
 const submitModal=new bootstrap.Modal(document.getElementById('submitModal'));
 const esc=s=>String(s??'').replace(/[&<>"']/g,ch=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[ch]));
 const users=<?= json_encode($users,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
 const offices=<?= json_encode($offices,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;

 function authorRow(data={}){
   const row=document.createElement('div');row.className='orx-author-row';
   const userOptions=['<option value="">Manual / external</option>'].concat(users.map(u=>`<option value="${u.id}" ${String(data.user_id||'')===String(u.id)?'selected':''}>${esc(u.full_name)}</option>`)).join('');
   const officeOptions=['<option value="">No office</option>'].concat(offices.map(o=>`<option value="${o.id}" ${String(data.office_id||'')===String(o.id)?'selected':''}>${esc(o.name)}</option>`)).join('');
   row.innerHTML=`<input class="form-control" name="author_name[]" placeholder="Name" value="${esc(data.author_name||'')}"><select class="form-select" name="author_type[]">${['Author','Co-Author','Sponsor','Co-Sponsor','Proponent'].map(x=>`<option ${data.author_type===x?'selected':''}>${x}</option>`).join('')}</select><select class="form-select author-user" name="author_user_id[]">${userOptions}</select><select class="form-select" name="author_office_id[]">${officeOptions}</select><button type="button" class="btn btn-outline-danger"><i class="bi bi-trash"></i></button>`;
   row.querySelector('button').onclick=()=>row.remove();
   row.querySelector('.author-user').onchange=function(){const u=users.find(x=>String(x.id)===this.value);if(u){row.querySelector('input[name="author_name[]"]').value=u.full_name;const office=row.querySelector('select[name="author_office_id[]"]');if(u.office_id)office.value=u.office_id;}};
   authorRows.appendChild(row);
 }
 function resetDraft(){
   draftForm.reset();d_id.value='0';d_priority.value='Normal';d_visibility.value='Internal';authorRows.innerHTML='';authorRow({author_type:'Author'});d_change.value='Initial draft';
 }
 btnAddAuthor.onclick=()=>authorRow({});
 btnNewDraft.onclick=()=>{resetDraft();draftModal.show();};
 d_template.onchange=function(){if(d_body.value.trim()===''&&this.selectedOptions[0]?.dataset.body)d_body.value=this.selectedOptions[0].dataset.body;};

 document.querySelectorAll('.edit-draft').forEach(b=>b.onclick=async function(){
   const r=await fetch('ajax_get.php?id='+encodeURIComponent(this.dataset.id),{headers:{'X-Requested-With':'XMLHttpRequest'}}).then(x=>x.json());
   if(!r.success){Swal.fire('Draft Error',r.message,'error');return;}
   const d=r.item;draftForm.reset();d_id.value=d.id;d_title.value=d.title||'';d_short.value=d.short_title||'';d_office.value=d.originating_office_id||'';d_priority.value=d.priority_level||'Normal';d_visibility.value=d.visibility||'Internal';d_subject.value=d.subject||'';d_category.value=d.legislative_category||'';d_policy.value=d.policy_area||'';d_summary.value=d.summary||'';d_purpose.value=d.purpose||'';d_note.value=d.explanatory_note||'';d_template.value=d.template_id||'';d_legal.value=d.legal_basis||'';d_fiscal.value=d.fiscal_impact||'';d_body.value=d.body_text||'';d_keywords.value=d.keywords||'';d_change.value='Draft updated';authorRows.innerHTML='';(r.authors||[]).forEach(authorRow);if(!(r.authors||[]).length)authorRow({author_type:'Author'});draftModal.show();
 });

 draftForm.onsubmit=async e=>{e.preventDefault();btnSaveDraft.disabled=true;try{const r=await fetch('ajax_save.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:new FormData(draftForm)}).then(x=>x.json());if(r.success){await Swal.fire('Saved',r.message,'success');location.href='view.php?id='+r.id;}else Swal.fire('Draft Error',r.message,'error');}finally{btnSaveDraft.disabled=false;}};

 document.querySelectorAll('.submit-draft').forEach(b=>b.onclick=function(){submitForm.reset();s_id.value=this.dataset.id;submitSource.textContent='Submitting '+this.dataset.ref+' for formal committee review.';submitModal.show();});
 submitForm.onsubmit=async e=>{e.preventDefault();const ask=await Swal.fire({title:'Submit this draft?',text:'The current draft will be versioned and locked for committee review until it is returned for revision.',icon:'question',showCancelButton:true,confirmButtonText:'Submit'});if(!ask.isConfirmed)return;const r=await fetch('ajax_submit.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:new FormData(submitForm)}).then(x=>x.json());if(r.success){await Swal.fire('Submitted',r.message,'success');location.href='<?= e(APP_URL) ?>/modules/reviews/view.php?id='+r.review_id;}else Swal.fire('Submission Error',r.message,'error');};

 document.querySelectorAll('.delete-draft').forEach(b=>b.onclick=async function(){const ask=await Swal.fire({title:'Deactivate draft?',text:'The record will be soft-deactivated. Existing audit history is preserved.',icon:'warning',showCancelButton:true,confirmButtonText:'Deactivate'});if(!ask.isConfirmed)return;const fd=new FormData();fd.append('csrf_token','<?= e(csrfToken()) ?>');fd.append('id',this.dataset.id);const r=await fetch('ajax_delete.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:fd}).then(x=>x.json());if(r.success)location.reload();else Swal.fire('Draft Error',r.message,'error');});
});
</script>
<?php include __DIR__.'/../layouts/footer.php'; ?>
