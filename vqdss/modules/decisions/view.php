<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/vqdss_final_helpers.php';

requirePermission('vqd.decisions.view');

$pdo=db();$id=(int)($_GET['id']??0);
$q=$pdo->prepare(
 "SELECT d.*,t.reference_number tally_reference,t.tally_status,t.result_statement,
         ve.reference_number voting_reference,ve.motion_text,
         li.reference_number legislative_reference,li.title legislative_title,
         rb.full_name recorded_name,cb.full_name certified_name,pb.full_name published_name
  FROM vqd_decisions d
  LEFT JOIN vqd_vote_tallies t ON t.id=d.tally_id
  LEFT JOIN vqd_voting_events ve ON ve.id=d.voting_event_id
  LEFT JOIN legislative_items li ON li.id=d.legislative_item_id
  LEFT JOIN users rb ON rb.id=d.recorded_by
  LEFT JOIN users cb ON cb.id=d.certified_by
  LEFT JOIN users pb ON pb.id=d.published_by
  WHERE d.id=:id"
);
$q->execute([':id'=>$id]);$d=$q->fetch();
if(!$d){setFlash('warning','Decision record not found.');redirect(appUrl('modules/decisions/index.php'));}

$offices=$pdo->query("SELECT id,name FROM offices WHERE status='Active' ORDER BY name")->fetchAll();
$users=$pdo->query("SELECT id,full_name FROM users WHERE status='Active' AND deleted_at IS NULL ORDER BY full_name")->fetchAll();

$a=$pdo->prepare(
 "SELECT a.*,o.name office_name,u.full_name user_name
  FROM vqd_decision_actions a
  LEFT JOIN offices o ON o.id=a.responsible_office_id
  LEFT JOIN users u ON u.id=a.responsible_user_id
  WHERE a.decision_id=:id ORDER BY a.created_at DESC"
);$a->execute([':id'=>$id]);$actions=$a->fetchAll();

$docs=$pdo->prepare('SELECT * FROM vqd_decision_documents WHERE decision_id=:id ORDER BY uploaded_at DESC');
$docs->execute([':id'=>$id]);$documents=$docs->fetchAll();

$history=$pdo->prepare(
 "SELECT h.*,u.full_name FROM vqd_decision_history h
  LEFT JOIN users u ON u.id=h.changed_by
  WHERE h.decision_id=:id ORDER BY h.created_at DESC,h.id DESC"
);$history->execute([':id'=>$id]);$historyRows=$history->fetchAll();

$aiQ=$pdo->prepare("SELECT * FROM vqd_ai_analysis WHERE module='decisions' AND reference_id=:id ORDER BY id DESC LIMIT 1");
$aiQ->execute([':id'=>$id]);$latestAi=$aiQ->fetch()?:null;
$latestAiData=$latestAi&&!empty($latestAi['result_json'])?json_decode((string)$latestAi['result_json'],true):null;

$pageTitle=$d['reference_number'];$activeMenu='decisions';$extraCss=[appUrl('assets/css/vqdss-final-ops.css')];
include __DIR__.'/../../layouts/header.php';
?>
<div class="app-wrapper"><?php include __DIR__.'/../../layouts/sidebar.php'; ?><main class="main-content">

<div class="vqdf-head"><div><a href="index.php" class="small text-decoration-none"><i class="bi bi-arrow-left"></i> Decision Recording</a><div class="vqdf-eyebrow mt-2"><?= e($d['reference_number']) ?></div><h1><?= e($d['decision_record_number']?:'Official Decision Record') ?></h1><p><?= e(($d['legislative_reference']?:'No legislative item').' · '.$d['tally_reference'].' · '.$d['official_outcome']) ?></p></div><div class="d-flex gap-2 flex-wrap"><span class="badge fs-6 text-bg-light"><?= e($d['record_status']) ?></span><a class="btn btn-outline-secondary btn-sm" target="_blank" href="print.php?id=<?= $id ?>"><i class="bi bi-printer"></i></a><?php if(in_array($d['record_status'],['Certified','Published'],true)): ?><a class="btn btn-primary btn-sm" href="<?= e(appUrl('modules/validation/index.php?decision_id='.$id)) ?>"><i class="bi bi-patch-check"></i> Validate Result</a><?php endif; ?></div></div>

<div class="row g-3 mb-3">
<?php foreach([
 ['Required',$d['required_votes'],'bi-calculator'],['Affirmative',$d['affirmative_votes'],'bi-check-circle'],
 ['Negative',$d['negative_votes'],'bi-x-circle'],['Abstain',$d['abstain_votes'],'bi-dash-circle'],
] as [$l,$v,$i]): ?><div class="col-6 col-lg-3"><div class="vqdf-stat"><i class="bi <?= e($i) ?>"></i><div><strong><?= (int)$v ?></strong><small><?= e($l) ?></small></div></div></div><?php endforeach; ?>
</div>

<div class="row g-3">
<div class="col-xl-8">
<div class="card vqdf-card mb-3"><div class="card-header">Official Decision</div><div class="card-body">
<div class="vqdf-grid mb-3"><div><small>Outcome</small><strong><?= e($d['official_outcome']) ?></strong></div><div><small>Classification</small><strong><?= e($d['decision_classification']?:'—') ?></strong></div><div><small>Moved By</small><strong><?= e($d['moved_by']?:'—') ?></strong></div><div><small>Seconded By</small><strong><?= e($d['seconded_by']?:'—') ?></strong></div><div><small>Effectivity</small><strong><?= e($d['effectivity_type']?:'—') ?><?= $d['effectivity_date']?' · '.formatDate($d['effectivity_date']):'' ?></strong></div><div><small>Release</small><strong><?= e($d['release_classification']) ?></strong></div></div>
<div class="vqdf-alert"><strong>Decision Statement</strong><br><?= nl2br(e($d['decision_statement'])) ?></div>
<?php if($d['legal_basis']): ?><p class="small mt-3 mb-1"><strong>Legal / Procedural Basis</strong></p><div class="small text-muted"><?= nl2br(e($d['legal_basis'])) ?></div><?php endif; ?>
<?php if($d['conditions']): ?><p class="small mt-3 mb-1"><strong>Conditions</strong></p><div class="small text-muted"><?= nl2br(e($d['conditions'])) ?></div><?php endif; ?>
</div></div>

<div class="card vqdf-card mb-3" id="aiDecisionCard" style="border-left: 4px solid #f59e0b;">
  <div class="card-header d-flex justify-content-between align-items-center bg-light">
    <span><i class="bi bi-robot text-warning me-1"></i> <strong>Ollama AI Decision Assistant</strong></span>
    <div class="d-flex gap-2">
      <button type="button" class="btn btn-sm btn-outline-warning" id="btnAiDraftDecision">
        <i class="bi bi-stars"></i> <span id="btnAiDraftText">Draft Decision Excerpt</span>
      </button>
      <button type="button" class="btn btn-sm btn-outline-primary" id="btnAiSuggestActions">
        <i class="bi bi-diagram-3"></i> <span>Suggest Action Plan</span>
      </button>
    </div>
  </div>
  <div class="card-body" id="aiDecisionBody">
    <?php if ($latestAiData && ($latestAi['analysis_type'] ?? '') === 'decision_draft'): ?>
      <div class="alert alert-light border py-2 px-3 small mb-3">
        <strong><i class="bi bi-lightbulb me-1"></i> Executive Summary:</strong> <?= e($latestAiData['executive_summary'] ?? 'Drafted by Ollama AI.') ?>
      </div>
      <div class="mb-3">
        <small class="text-muted d-block fw-semibold mb-1">AI Drafted Decision Statement:</small>
        <div class="vqdf-alert py-2 mb-2" id="aiDraftStmt"><?= nl2br(e($latestAiData['decision_statement'] ?? '')) ?></div>
      </div>
      <div class="row g-2 mb-3">
        <div class="col-md-6">
          <small class="text-muted d-block fw-semibold mb-1">Statutory Legal Basis:</small>
          <div class="small text-muted border p-2 rounded bg-light" id="aiDraftBasis"><?= nl2br(e($latestAiData['legal_basis'] ?? '')) ?></div>
        </div>
        <div class="col-md-6">
          <small class="text-muted d-block fw-semibold mb-1">Effectivity & Conditions:</small>
          <div class="small text-muted border p-2 rounded bg-light" id="aiDraftCond"><?= nl2br(e($latestAiData['conditions'] ?? '')) ?></div>
        </div>
      </div>
      <?php if (vqdOperationalManager() && in_array($d['record_status'], ['Draft', 'Recorded'], true)): ?>
        <div class="pt-2 border-top">
          <button type="button" class="btn btn-sm btn-primary" id="btnApplyAiDecision"
                  data-stmt="<?= e($latestAiData['decision_statement'] ?? '') ?>"
                  data-basis="<?= e($latestAiData['legal_basis'] ?? '') ?>"
                  data-cond="<?= e($latestAiData['conditions'] ?? '') ?>">
            <i class="bi bi-check2-circle me-1"></i> Apply AI Draft to Decision Record
          </button>
        </div>
      <?php endif; ?>
    <?php else: ?>
      <div class="text-muted small py-2">
        <i class="bi bi-info-circle me-1"></i> No AI draft generated yet. Click <strong>Draft Decision Excerpt</strong> to have local Ollama draft the formal legislative resolution and legal basis under RA 7160, or <strong>Suggest Action Plan</strong> to generate executive implementation steps.
      </div>
    <?php endif; ?>
  </div>
</div>

<div class="card vqdf-card mb-3"><div class="card-header d-flex justify-content-between"><span>Implementation Actions</span><?php if(vqdOperationalManager()): ?><button class="btn btn-sm btn-primary" id="btnAddAction"><i class="bi bi-plus"></i> Add Action</button><?php endif; ?></div><div class="table-responsive"><table class="table vqdf-table mb-0"><thead><tr><th>Action</th><th>Responsible</th><th>Priority</th><th>Due</th><th>Status</th><th class="text-end">Update</th></tr></thead><tbody>
<?php if(!$actions): ?><tr><td colspan="6" class="text-center text-muted py-4">No implementation actions recorded.</td></tr><?php endif; ?>
<?php foreach($actions as $a): ?><tr><td><strong><?= e($a['action_title']) ?></strong><div class="small text-muted"><?= e(mb_strimwidth($a['action_description']?:'',0,100,'…')) ?></div></td><td><?= e($a['office_name']?:$a['user_name']?:'Unassigned') ?></td><td><?= e($a['priority']) ?></td><td><?= $a['due_date']?formatDate($a['due_date']):'—' ?></td><td><span class="badge text-bg-light"><?= e($a['status']) ?></span></td><td class="text-end"><?php if(vqdOperationalManager()): ?><button class="btn btn-sm btn-outline-primary edit-action" data-row='<?= e(json_encode($a,JSON_HEX_APOS|JSON_HEX_QUOT)) ?>'><i class="bi bi-pencil"></i></button><?php endif; ?></td></tr><?php endforeach; ?>
</tbody></table></div></div>

<div class="card vqdf-card"><div class="card-header">Decision Documents</div><div class="card-body">
<div class="list-group mb-3"><?php if(!$documents): ?><div class="list-group-item text-muted">No decision documents uploaded.</div><?php endif; ?><?php foreach($documents as $doc): ?><a class="list-group-item list-group-item-action" target="_blank" href="<?= e(appUrl('assets/uploads/'.$doc['file_path'])) ?>"><strong><?= e($doc['file_name']) ?></strong><div class="small text-muted"><?= e($doc['document_type'].' · '.$doc['visibility']) ?> · <?= formatDateTime($doc['uploaded_at']) ?></div></a><?php endforeach; ?></div>
<?php if(vqdOperationalManager()): ?><form id="docForm" class="row g-2" enctype="multipart/form-data"><?= csrfField() ?><input type="hidden" name="decision_id" value="<?= $id ?>"><div class="col-md-6"><input type="file" class="form-control form-control-sm" name="document" required></div><div class="col-md-3"><input class="form-control form-control-sm" name="document_type" value="Decision Document"></div><div class="col-md-3"><select class="form-select form-select-sm" name="visibility"><option>Internal</option><option>Public</option><option>Restricted</option></select></div><div class="col-12"><button class="btn btn-outline-primary btn-sm"><i class="bi bi-upload"></i> Upload</button></div></form><?php endif; ?>
</div></div>
</div>

<div class="col-xl-4">
<?php if(vqdOperationalManager()): ?><div class="card vqdf-card mb-3"><div class="card-header">Decision Workflow</div><div class="card-body"><form id="workflowForm"><?= csrfField() ?><input type="hidden" name="id" value="<?= $id ?>"><textarea class="form-control form-control-sm mb-2" name="notes" rows="2" placeholder="Certification, publication or cancellation note"></textarea><div class="d-grid gap-2">
<?php if($d['record_status']==='Draft'): ?><button type="button" class="btn btn-outline-primary decision-transition" data-action="record">Formally Record</button><?php endif; ?>
<?php if(in_array($d['record_status'],['Draft','Recorded'],true)): ?><button type="button" class="btn btn-success decision-transition" data-action="certify"><i class="bi bi-patch-check"></i> Certify Decision</button><?php endif; ?>
<?php if($d['record_status']==='Certified'): ?><button type="button" class="btn btn-primary decision-transition" data-action="publish"><i class="bi bi-megaphone"></i> Publish Public Decision</button><?php endif; ?>
<?php if(!in_array($d['record_status'],['Published','Cancelled'],true)): ?><button type="button" class="btn btn-outline-danger decision-transition" data-action="cancel">Cancel Record</button><?php endif; ?>
</div></form></div></div><?php endif; ?>

<div class="card vqdf-card"><div class="card-header">Decision History</div><div class="card-body vqdf-history"><?php foreach($historyRows as $h): ?><div><strong><?= e($h['action']) ?> · <?= e($h['full_name']?:'System') ?></strong><small><?= e($h['details']?:'') ?><br><?= formatDateTime($h['created_at']) ?></small></div><?php endforeach; ?></div></div>
</div>
</div>
</main></div>

<?php if(vqdOperationalManager()): ?>
<div class="modal fade" id="actionModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered" style="max-width: 580px;"><div class="modal-content shadow"><form id="actionForm"><?= csrfField() ?><input type="hidden" name="decision_id" value="<?= $id ?>"><input type="hidden" name="id" id="a_id" value="0">
<div class="modal-header bg-dark text-white py-2 px-3"><h6 class="modal-title mb-0">Implementation Action</h6><button class="btn-close btn-close-white" type="button" data-bs-dismiss="modal"></button></div>
<div class="modal-body p-3"><div class="row g-2">
<div class="col-12"><label class="form-label small fw-bold mb-1">Action Title *</label><input class="form-control form-control-sm" name="action_title" id="a_title" required></div>
<div class="col-12"><label class="form-label small fw-bold mb-1">Description</label><textarea class="form-control form-control-sm" name="action_description" id="a_desc" rows="2"></textarea></div>
<div class="col-md-6"><label class="form-label small fw-bold mb-1">Responsible Office</label><select class="form-select form-select-sm" name="responsible_office_id" id="a_office"><option value="">Unassigned</option><?php foreach($offices as $o): ?><option value="<?= (int)$o['id'] ?>"><?= e($o['name']) ?></option><?php endforeach; ?></select></div>
<div class="col-md-6"><label class="form-label small fw-bold mb-1">Responsible User</label><select class="form-select form-select-sm" name="responsible_user_id" id="a_user"><option value="">Unassigned</option><?php foreach($users as $u): ?><option value="<?= (int)$u['id'] ?>"><?= e($u['full_name']) ?></option><?php endforeach; ?></select></div>
<div class="col-md-4"><label class="form-label small fw-bold mb-1">Priority</label><select class="form-select form-select-sm" name="priority" id="a_priority"><option>Low</option><option selected>Normal</option><option>High</option><option>Urgent</option></select></div>
<div class="col-md-4"><label class="form-label small fw-bold mb-1">Due Date</label><input type="date" class="form-control form-control-sm" name="due_date" id="a_due"></div>
<div class="col-md-4"><label class="form-label small fw-bold mb-1">Status</label><select class="form-select form-select-sm" name="status" id="a_status"><option>Pending</option><option>In Progress</option><option>Completed</option><option>Cancelled</option></select></div>
<div class="col-12"><label class="form-label small fw-bold mb-1">Completion Notes</label><textarea class="form-control form-control-sm" name="completion_notes" id="a_completion" rows="2"></textarea></div>
</div></div><div class="modal-footer py-2 px-3"><button class="btn btn-sm btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancel</button><button class="btn btn-sm btn-primary">Save Action</button></div>
</form></div></div></div>

<!-- AI Implementation Actions Modal -->
<div class="modal fade" id="aiActionsModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered" style="max-width: 620px;"><div class="modal-content shadow">
<div class="modal-header bg-dark text-white py-2 px-3"><h6 class="modal-title mb-0"><i class="bi bi-robot text-warning me-2"></i>AI Suggested Implementation Plan</h6><button class="btn-close btn-close-white" type="button" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
  <p class="small text-muted mb-3">Ollama AI has analyzed the decision and generated the following departmental execution directives:</p>
  <div id="aiActionsList" class="d-grid gap-2 mb-3"></div>
</div>
<div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" id="btnApplySuggestedActions"><i class="bi bi-check2-circle me-1"></i> 1-Click Apply All to Implementation Roster</button></div>
</div></div></div>

<script>
document.addEventListener('DOMContentLoaded',function(){
 const amodal=new bootstrap.Modal(document.getElementById('actionModal'));
 const aiActModal=new bootstrap.Modal(document.getElementById('aiActionsModal'));
 let currentSuggestedActions = [];

 btnAddAction.onclick=()=>{actionForm.reset();a_id.value='0';a_status.value='Pending';a_priority.value='Normal';amodal.show();};
 document.querySelectorAll('.edit-action').forEach(b=>b.onclick=function(){const a=JSON.parse(this.dataset.row);a_id.value=a.id;a_title.value=a.action_title||'';a_desc.value=a.action_description||'';a_office.value=a.responsible_office_id||'';a_user.value=a.responsible_user_id||'';a_priority.value=a.priority||'Normal';a_due.value=a.due_date||'';a_status.value=a.status||'Pending';a_completion.value=a.completion_notes||'';amodal.show();});
 actionForm.onsubmit=async e=>{e.preventDefault();const r=await fetch('ajax_action_save.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:new FormData(actionForm)}).then(x=>x.json());if(r.success)location.reload();else Swal.fire('Action Error',r.message,'error');};
 document.querySelectorAll('.decision-transition').forEach(b=>b.onclick=async function(){const fd=new FormData(workflowForm);fd.append('action',this.dataset.action);const r=await fetch('ajax_transition.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:fd}).then(x=>x.json());if(r.success){await Swal.fire('Decision Updated',r.message,'success');location.reload();}else Swal.fire('Decision Error',r.message,'error');});
 docForm.onsubmit=async e=>{e.preventDefault();const r=await fetch('ajax_upload_document.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:new FormData(docForm)}).then(x=>x.json());if(r.success)location.reload();else Swal.fire('Upload Error',r.message,'error');};

 // Ollama AI Decision Drafting
 const btnAiDraft = document.getElementById('btnAiDraftDecision');
 if (btnAiDraft) {
   btnAiDraft.addEventListener('click', async () => {
     btnAiDraft.disabled = true;
     const origHtml = btnAiDraft.innerHTML;
     btnAiDraft.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Drafting with Ollama...';
     try {
       const fd = new FormData();
       fd.append('csrf_token', '<?= e(csrfToken()) ?>');
       fd.append('decision_id', '<?= $id ?>');
       fd.append('action', 'draft_decision');
       const res = await fetch('ajax_ai_decision.php', {
         method: 'POST',
         headers: {'X-Requested-With': 'XMLHttpRequest'},
         body: fd
       }).then(x => x.json());

       if (!res.success) {
         Swal.fire('Ollama AI', res.message || 'Decision drafting failed.', 'error');
         return;
       }
       Swal.fire({toast: true, position: 'top-end', icon: 'success', title: 'Decision Draft Complete', timer: 1500, showConfirmButton: false});
       location.reload();
     } catch (err) {
       Swal.fire('Ollama AI', err.message, 'error');
     } finally {
       btnAiDraft.disabled = false;
       btnAiDraft.innerHTML = origHtml;
     }
   });
 }

 // Apply AI Decision Content
 const btnApplyDec = document.getElementById('btnApplyAiDecision');
 if (btnApplyDec) {
   btnApplyDec.addEventListener('click', async () => {
     const stmt = btnApplyDec.dataset.stmt;
     const basis = btnApplyDec.dataset.basis;
     const cond = btnApplyDec.dataset.cond;

     const conf = await Swal.fire({
       title: 'Apply AI Draft to Decision?',
       text: 'This will update the formal Decision Statement, Legal Basis, and Conditions on this record.',
       icon: 'question',
       showCancelButton: true,
       confirmButtonText: 'Yes, apply'
     });
     if (!conf.isConfirmed) return;

     const fd = new FormData();
     fd.append('csrf_token', '<?= e(csrfToken()) ?>');
     fd.append('decision_id', '<?= $id ?>');
     fd.append('action', 'apply_decision');
     fd.append('decision_statement', stmt);
     fd.append('legal_basis', basis);
     fd.append('conditions', cond);

     const res = await fetch('ajax_ai_decision.php', {
       method: 'POST',
       headers: {'X-Requested-With': 'XMLHttpRequest'},
       body: fd
     }).then(x => x.json());

     if (res.success) {
       await Swal.fire('Updated', res.message, 'success');
       location.reload();
     } else {
       Swal.fire('Error', res.message, 'error');
     }
   });
 }

 // Ollama AI Suggest Implementation Actions
 const btnAiSugg = document.getElementById('btnAiSuggestActions');
 if (btnAiSugg) {
   btnAiSugg.addEventListener('click', async () => {
     btnAiSugg.disabled = true;
     const origHtml = btnAiSugg.innerHTML;
     btnAiSugg.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Generating Plan...';
     try {
       const fd = new FormData();
       fd.append('csrf_token', '<?= e(csrfToken()) ?>');
       fd.append('decision_id', '<?= $id ?>');
       fd.append('action', 'suggest_actions');
       const res = await fetch('ajax_ai_decision.php', {
         method: 'POST',
         headers: {'X-Requested-With': 'XMLHttpRequest'},
         body: fd
       }).then(x => x.json());

       if (!res.success) {
         Swal.fire('Ollama AI', res.message || 'Failed to suggest actions.', 'error');
         return;
       }

       currentSuggestedActions = (res.data && res.data.actions) ? res.data.actions : [];
       const container = document.getElementById('aiActionsList');
       if (container) {
         container.innerHTML = currentSuggestedActions.map((act, idx) => `
           <div class="border rounded p-3 bg-light">
             <div class="d-flex justify-content-between align-items-center mb-1">
               <strong class="text-primary">${idx + 1}. ${escapeHtml(act.title)}</strong>
               <span class="badge bg-${act.priority === 'High' || act.priority === 'Urgent' ? 'danger' : 'secondary'}">${act.priority}</span>
             </div>
             <p class="small text-muted mb-2">${escapeHtml(act.description)}</p>
             <div class="d-flex justify-content-between small text-muted">
               <span><i class="bi bi-building me-1"></i>Office: <strong>${escapeHtml(act.suggested_office)}</strong></span>
               <span><i class="bi bi-calendar-event me-1"></i>Due in: <strong>${act.days_due} days</strong></span>
             </div>
           </div>
         `).join('');
       }
       aiActModal.show();
     } catch (err) {
       Swal.fire('Ollama AI', err.message, 'error');
     } finally {
       btnAiSugg.disabled = false;
       btnAiSugg.innerHTML = origHtml;
     }
   });
 }

 // Apply Suggested Actions
 const btnApplyAct = document.getElementById('btnApplySuggestedActions');
 if (btnApplyAct) {
   btnApplyAct.addEventListener('click', async () => {
     if (!currentSuggestedActions.length) return;
     btnApplyAct.disabled = true;
     btnApplyAct.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Applying...';

     try {
       const fd = new FormData();
       fd.append('csrf_token', '<?= e(csrfToken()) ?>');
       fd.append('decision_id', '<?= $id ?>');
       fd.append('action', 'apply_actions');
       fd.append('actions_json', JSON.stringify(currentSuggestedActions));

       const res = await fetch('ajax_ai_decision.php', {
         method: 'POST',
         headers: {'X-Requested-With': 'XMLHttpRequest'},
         body: fd
       }).then(x => x.json());

       if (res.success) {
         aiActModal.hide();
         await Swal.fire('Actions Applied', res.message, 'success');
         location.reload();
       } else {
         Swal.fire('Error', res.message, 'error');
       }
     } catch (err) {
       Swal.fire('Error', err.message, 'error');
     } finally {
       btnApplyAct.disabled = false;
       btnApplyAct.innerHTML = '<i class="bi bi-check2-circle me-1"></i> 1-Click Apply All to Implementation Roster';
     }
   });
 }

 function escapeHtml(str) {
   return String(str || '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[m]);
 }
});
</script>
<?php endif; ?>
<?php include __DIR__.'/../../layouts/footer.php'; ?>

