<?php
declare(strict_types=1);

require_once __DIR__.'/../../includes/cepfms_operational_helpers.php';
requireCefPermission('cepfms.moderation.view');

$pdo=db();$id=(int)($_GET['id']??0);$s=cefSubmissionRow($pdo,$id);
if(!$s){setFlash('warning','Submission not found.');redirect(appUrl('modules/moderation/index.php'));}

$reviewQ=$pdo->prepare(
 'SELECT r.*,u.full_name reviewer_name
  FROM cef_moderation_reviews r
  LEFT JOIN users u ON u.id=r.reviewer_id
  WHERE r.submission_id=:id
  ORDER BY r.review_round DESC,r.id DESC LIMIT 1'
);
$reviewQ->execute([':id'=>$id]);$review=$reviewQ->fetch()?:null;

$checklist=$review?cefReviewChecklist($pdo,(int)$review['id']):[];

$aiQ=$pdo->prepare('SELECT * FROM cef_ai_analysis WHERE submission_id=:id ORDER BY id DESC LIMIT 1');
$aiQ->execute([':id'=>$id]);$latestAi=$aiQ->fetch()?:null;
$latestAiData=$latestAi&&!empty($latestAi['result_json'])?json_decode((string)$latestAi['result_json'],true):null;

$dupQ=$pdo->prepare(
 "SELECT dm.*,m.reference_number,m.title,m.status submission_status
  FROM cef_duplicate_matches dm
  JOIN cef_submissions m ON m.id=dm.matched_submission_id
  WHERE dm.submission_id=:id
  ORDER BY dm.status='Confirmed' DESC,dm.similarity_score DESC,dm.created_at DESC"
);
$dupQ->execute([':id'=>$id]);$duplicates=$dupQ->fetchAll();

$candidates=$pdo->prepare(
 "SELECT id,reference_number,title,status
  FROM cef_submissions
  WHERE id<>:id
    AND submission_type=:type
    AND deleted_at IS NULL
  ORDER BY created_at DESC LIMIT 150"
);
$candidates->execute([':id'=>$id,':type'=>$s['submission_type']]);
$duplicateCandidates=$candidates->fetchAll();

$docsQ=$pdo->prepare(
 "SELECT d.*,u.full_name uploaded_name
  FROM cef_submission_documents d
  LEFT JOIN users u ON u.id=d.uploaded_by
  WHERE d.submission_id=:id
  ORDER BY d.uploaded_at DESC,d.id DESC"
);
$docsQ->execute([':id'=>$id]);$docs=$docsQ->fetchAll();

$historyQ=$pdo->prepare(
 "SELECT h.*,u.full_name changed_name
  FROM cef_submission_history h
  LEFT JOIN users u ON u.id=h.changed_by
  WHERE h.submission_id=:id
  ORDER BY h.created_at DESC,h.id DESC"
);
$historyQ->execute([':id'=>$id]);$history=$historyQ->fetchAll();

$pageTitle=$s['reference_number'].' Moderation';$activeMenu='moderation';
$extraCss=[appUrl('assets/css/cepfms-operational.css')];
include __DIR__.'/../../layouts/header.php';
?>
<div class="app-wrapper"><?php include __DIR__.'/../../layouts/sidebar.php'; ?><main class="main-content">

<div class="cef-head">
<div>
<a class="small text-decoration-none" href="index.php"><i class="bi bi-arrow-left"></i> Moderation Queue</a>
<div class="cef-eyebrow mt-2"><?= e($s['reference_number']) ?> · <?= e(cefSubmissionTypeLabel($s['submission_type'])) ?></div>
<h1><?= e($s['title']) ?></h1>
<p><?= e($s['category_name']?:'Unclassified') ?> · <?= e($s['priority_level']) ?> priority · <?= formatDateTime($s['created_at']) ?></p>
</div>
<span class="cef-status <?= e(cefStatusClass($s['status'])) ?>"><?= e($s['moderation_status']) ?></span>
</div>

<div class="row g-3">
<div class="col-xl-8">

<div class="card cef-card mb-3"><div class="card-header">Citizen Submission</div><div class="card-body">
<div class="cef-grid">
<div><small>Type</small><strong><?= e(cefSubmissionTypeLabel($s['submission_type'])) ?></strong></div>
<div><small>Citizen</small><strong><?= $s['anonymous_flag']?'Anonymous':e($s['citizen_name']?:'Not provided') ?></strong></div>
<div><small>Contact</small><strong><?= $s['anonymous_flag']?'Not retained':e($s['citizen_email']?:$s['citizen_phone']?:'Not provided') ?></strong></div>
<div><small>Category</small><strong><?= e($s['category_name']?:'Unclassified') ?></strong></div>
<div><small>Location</small><strong><?= e($s['barangay']?:$s['district']?:$s['location_text']?:'Not specified') ?></strong></div>
<div><small>Current Status</small><strong><?= e($s['status']) ?></strong></div>
</div>
<div class="cef-alert mt-3"><strong>Details</strong><br><?= nl2br(e($s['details'])) ?></div>
</div></div>

<div class="card cef-card mb-3" id="aiAssistantCard" style="border-left: 4px solid #a97900;">
  <div class="card-header d-flex justify-content-between align-items-center bg-light">
    <span><i class="bi bi-robot text-warning me-1"></i> <strong>Ollama AI Moderation Assistant</strong></span>
    <button type="button" class="btn btn-sm btn-outline-warning" id="btnAiAnalyze">
      <i class="bi bi-stars"></i> <span id="aiBtnText">Analyze with Ollama</span>
    </button>
  </div>
  <div class="card-body" id="aiResultArea">
    <?php if ($latestAiData): ?>
      <div class="d-flex flex-wrap gap-2 mb-3">
        <span class="badge bg-<?= in_array($latestAiData['urgency_level'] ?? '', ['High', 'Urgent']) ? 'danger' : 'warning text-dark' ?>">
          <i class="bi bi-alarm me-1"></i>Urgency: <?= e($latestAiData['urgency_level'] ?? 'Normal') ?> (<?= (int)($latestAiData['urgency_score'] ?? 0) ?>%)
        </span>
        <span class="badge bg-<?= ($latestAiData['sentiment'] ?? '') === 'Negative' ? 'danger' : (($latestAiData['sentiment'] ?? '') === 'Positive' ? 'success' : 'secondary') ?>">
          Sentiment: <?= e($latestAiData['sentiment'] ?? 'Neutral') ?>
        </span>
        <span class="badge bg-<?= ($latestAiData['content_safety'] ?? '') === 'Safe' ? 'success' : 'danger' ?>">
          Safety: <?= e($latestAiData['content_safety'] ?? 'Safe') ?>
        </span>
        <span class="badge bg-info text-dark">
          Actionability: <?= e($latestAiData['actionability'] ?? 'Complete') ?>
        </span>
      </div>
      <div class="mb-2"><strong>Executive Summary:</strong> <?= e($latestAiData['executive_summary'] ?? '') ?></div>
      <div class="row g-2 mb-2">
        <div class="col-md-6">
          <small class="text-muted d-block">Suggested Category</small>
          <strong><?= e($latestAiData['recommended_category'] ?? 'General') ?></strong>
        </div>
        <div class="col-md-6">
          <small class="text-muted d-block">Recommended Office</small>
          <strong><?= e($latestAiData['recommended_office'] ?? 'Public Assistance Desk') ?></strong>
        </div>
      </div>
      <?php if (!empty($latestAiData['risk_keywords']) || !empty($latestAiData['key_topics'])): ?>
        <div class="small mb-2">
          <span class="text-muted me-1">Keywords:</span>
          <?php foreach(($latestAiData['risk_keywords'] ?? []) as $kw): ?>
            <span class="badge bg-danger-subtle text-danger border border-danger-subtle me-1"><?= e($kw) ?></span>
          <?php endforeach; ?>
          <?php foreach(($latestAiData['key_topics'] ?? []) as $kw): ?>
            <span class="badge bg-light text-dark border me-1"><?= e($kw) ?></span>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <?php if (cefHasPermission('cepfms.moderation.manage')): ?>
        <div class="d-flex gap-2 mt-3 pt-2 border-top">
          <button type="button" class="btn btn-sm btn-success" id="btnApplyAi" data-cat="<?= e($latestAiData['recommended_category'] ?? '') ?>" data-prio="<?= e($latestAiData['urgency_level'] ?? 'Normal') ?>">
            <i class="bi bi-check2-circle"></i> Apply AI Category & Priority
          </button>
        </div>
      <?php endif; ?>
    <?php else: ?>
      <div class="text-muted small">
        <i class="bi bi-info-circle me-1"></i> No AI triage record yet. Click <strong>"Analyze with Ollama"</strong> to detect urgency, sentiment, civic category, and safety flags.
      </div>
    <?php endif; ?>
  </div>
</div>

<div class="card cef-card mb-3"><div class="card-header">Supporting Evidence</div><div class="list-group list-group-flush">
<?php if(!$docs): ?><div class="list-group-item text-muted">No supporting evidence.</div><?php endif; ?>
<?php foreach($docs as $doc): ?><a class="list-group-item list-group-item-action" target="_blank" href="<?= e(UPLOAD_URL.$doc['file_path']) ?>"><strong><?= e($doc['file_name']) ?></strong><div class="small text-muted"><?= e($doc['document_type'].' · '.$doc['visibility']) ?> · <?= formatDateTime($doc['uploaded_at']) ?></div></a><?php endforeach; ?>
</div></div>

<div class="card cef-card mb-3"><div class="card-header d-flex justify-content-between"><span>Moderation Checklist</span><?php if(cefHasPermission('cepfms.moderation.manage')&&!$review): ?><button class="btn btn-sm btn-warning" id="btnStartReview">Start Review</button><?php endif; ?></div><div class="card-body">
<?php if(!$review): ?><div class="cef-alert">Start a formal review to create the moderation checklist.</div><?php else: ?>
<div class="small text-muted mb-3">Round <?= (int)$review['review_round'] ?> · Reviewer: <?= e($review['reviewer_name']?:'Current staff') ?> · Decision: <?= e($review['decision']) ?></div>
<div class="d-grid gap-2">
<?php foreach($checklist as $item): ?>
<form class="cef-person moderation-check-form">
<?= csrfField() ?><input type="hidden" name="review_id" value="<?= (int)$review['id'] ?>"><input type="hidden" name="item_code" value="<?= e($item['item_code']) ?>">
<div class="flex-grow-1"><strong><?= e($item['item_label']) ?></strong><small><?= e($item['item_code']) ?></small><input class="form-control form-control-sm mt-2" name="notes" value="<?= e($item['notes']?:'') ?>" placeholder="Optional reviewer note" <?= !cefHasPermission('cepfms.moderation.manage')?'disabled':'' ?>></div>
<select class="form-select form-select-sm" style="max-width:150px" name="status" <?= !cefHasPermission('cepfms.moderation.manage')?'disabled':'' ?>><?php foreach(['Pending','Pass','Fail','Not Applicable'] as $x): ?><option <?= $item['status']===$x?'selected':'' ?>><?= e($x) ?></option><?php endforeach; ?></select>
<?php if(cefHasPermission('cepfms.moderation.manage')): ?><button class="btn btn-sm btn-outline-primary">Save</button><?php endif; ?>
</form>
<?php endforeach; ?>
</div>
<?php endif; ?>
</div></div>

<div class="card cef-card mb-3"><div class="card-header d-flex justify-content-between"><span>Potential Duplicate Matches</span><?php if(cefHasPermission('cepfms.moderation.manage')): ?><div class="d-flex gap-2"><button class="btn btn-sm btn-primary" id="btnDuplicateScan"><i class="bi bi-files"></i> Scan Recent</button><button class="btn btn-sm btn-outline-primary" id="btnAiDuplicateScan" title="Scan using Ollama AI semantic understanding"><i class="bi bi-robot"></i> AI Scan</button></div><?php endif; ?></div><div class="card-body d-grid gap-2" id="duplicateList">
<?php if(!$duplicates): ?><div class="text-muted small">No duplicate suggestion has been recorded.</div><?php endif; ?>
<?php foreach($duplicates as $d): ?><div class="cef-person"><div><strong><?= e($d['reference_number'].' · '.$d['title']) ?></strong><small><?= e($d['status'].' · '.$d['submission_status']) ?><?= $d['similarity_score']!==null?' · Similarity '.number_format((float)$d['similarity_score'],1).'%':'' ?> · <em><?= e($d['match_source']??'Text Similarity') ?></em></small></div></div><?php endforeach; ?>
</div></div>

</div>

<div class="col-xl-4">
<?php if(cefHasPermission('cepfms.moderation.manage')&&$review): ?>
<div class="card cef-card mb-3"><div class="card-header">Moderation Decision</div><div class="card-body">
<form id="decisionForm">
<?= csrfField() ?><input type="hidden" name="submission_id" value="<?= $id ?>"><input type="hidden" name="review_id" value="<?= (int)$review['id'] ?>">
<label class="form-label">Decision</label>
<select class="form-select mb-2" name="decision" id="decisionSelect">
<option value="Validate">Validate</option>
<option value="Needs Clarification">Needs Clarification</option>
<option value="Reject">Reject</option>
<option value="Duplicate">Duplicate</option>
</select>
<div id="duplicateTargetWrap" class="d-none">
<label class="form-label">Duplicate Of</label>
<select class="form-select mb-2" name="duplicate_of_submission_id"><option value="">Choose original submission</option><?php foreach($duplicateCandidates as $x): ?><option value="<?= (int)$x['id'] ?>"><?= e($x['reference_number'].' · '.$x['title'].' · '.$x['status']) ?></option><?php endforeach; ?></select>
</div>
<label class="form-label">Decision Notes</label>
<textarea class="form-control mb-3" name="notes" rows="5" placeholder="Required for clarification, rejection, and duplicate decisions"></textarea>
<button class="btn btn-primary w-100">Save Moderation Decision</button>
</form>
</div></div>
<?php endif; ?>

<div class="card cef-card"><div class="card-header">Submission History</div><div class="card-body cef-timeline">
<?php foreach($history as $x): ?><div><strong><?= e($x['action']) ?><?= $x['public_visible']?' · Public':'' ?></strong><small><?= e(($x['previous_status']?:'—').' → '.($x['new_status']?:'—')) ?><?= $x['details']?'<br>'.e($x['details']):'' ?><br><?= formatDateTime($x['created_at']) ?></small></div><?php endforeach; ?>
</div></div>
</div>
</div>

</main></div>

<script>
document.addEventListener('DOMContentLoaded',()=>{
 const start=document.getElementById('btnStartReview');
 if(start)start.onclick=async()=>{const fd=new FormData();fd.append('csrf_token','<?= e(csrfToken()) ?>');fd.append('submission_id','<?= $id ?>');const r=await fetch('ajax_start.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:fd}).then(x=>x.json());if(r.success)location.reload();else Swal.fire('Moderation',r.message,'error');};

 document.querySelectorAll('.moderation-check-form').forEach(form=>form.onsubmit=async e=>{e.preventDefault();const r=await fetch('ajax_checklist.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:new FormData(form)}).then(x=>x.json());if(r.success)Swal.fire({toast:true,position:'top-end',icon:'success',title:'Checklist saved',showConfirmButton:false,timer:1200});else Swal.fire('Checklist',r.message,'error');});

 const runDupScan = async (useAi = false) => {
   const fd=new FormData();fd.append('csrf_token','<?= e(csrfToken()) ?>');fd.append('submission_id','<?= $id ?>');
   if(useAi) fd.append('ai_scan', '1');
   Swal.fire({title: useAi ? 'Running AI Semantic Scan...' : 'Scanning Records...', didOpen: () => Swal.showLoading(), allowOutsideClick: false});
   const r=await fetch('ajax_duplicate_scan.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:fd}).then(x=>x.json());
   if(!r.success){Swal.fire('Duplicate Scan',r.message,'error');return;}
   await Swal.fire('Duplicate Scan',`${r.matches.length} potential match(es) found.`,'info');location.reload();
 };
 document.getElementById('btnDuplicateScan')?.addEventListener('click',()=>runDupScan(false));
 document.getElementById('btnAiDuplicateScan')?.addEventListener('click',()=>runDupScan(true));

 const decision=document.getElementById('decisionSelect'),wrap=document.getElementById('duplicateTargetWrap'),form=document.getElementById('decisionForm');
 if(decision)decision.onchange=()=>wrap.classList.toggle('d-none',decision.value!=='Duplicate');
 if(form)form.onsubmit=async e=>{e.preventDefault();const confirm=await Swal.fire({title:'Save moderation decision?',showCancelButton:true});if(!confirm.isConfirmed)return;const r=await fetch('ajax_decision.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:new FormData(form)}).then(x=>x.json());if(r.success){await Swal.fire('Moderation',r.message,'success');location.reload();}else Swal.fire('Moderation',r.message,'error');};

 // AI Analyze Handler
 const btnAi = document.getElementById('btnAiAnalyze');
 if (btnAi) {
   btnAi.addEventListener('click', async () => {
     btnAi.disabled = true;
     const origHtml = btnAi.innerHTML;
     btnAi.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Analyzing with Ollama...';
     try {
       const fd = new FormData();
       fd.append('csrf_token', '<?= e(csrfToken()) ?>');
       fd.append('submission_id', '<?= $id ?>');
       const res = await fetch('ajax_ai_analyze.php', {
         method: 'POST',
         headers: {'X-Requested-With': 'XMLHttpRequest'},
         body: fd
       }).then(x => x.json());

       if (!res.success) {
         Swal.fire('Ollama AI', res.message || 'Analysis failed. Make sure Ollama is running.', 'error');
         return;
       }
       Swal.fire({toast: true, position: 'top-end', icon: 'success', title: 'Ollama Analysis Complete', timer: 1500, showConfirmButton: false});
       location.reload();
     } catch (err) {
       Swal.fire('Ollama AI', 'Failed to connect to server: ' + err.message, 'error');
     } finally {
       btnAi.disabled = false;
       btnAi.innerHTML = origHtml;
     }
   });
 }

 // Apply AI Recommendations Handler
 const btnApply = document.getElementById('btnApplyAi');
 if (btnApply) {
   btnApply.addEventListener('click', async () => {
     const cat = btnApply.dataset.cat;
     const prio = btnApply.dataset.prio;
     const conf = await Swal.fire({
       title: 'Apply AI Recommendations?',
       text: `Set category to "${cat}" and priority to "${prio}"?`,
       icon: 'question',
       showCancelButton: true,
       confirmButtonText: 'Yes, apply'
     });
     if (!conf.isConfirmed) return;

     const fd = new FormData();
     fd.append('csrf_token', '<?= e(csrfToken()) ?>');
     fd.append('submission_id', '<?= $id ?>');
     fd.append('action', 'apply');
     fd.append('category', cat);
     fd.append('priority', prio);

     const res = await fetch('ajax_ai_analyze.php', {
       method: 'POST',
       headers: {'X-Requested-With': 'XMLHttpRequest'},
       body: fd
     }).then(x => x.json());

     if (res.success) {
       await Swal.fire('Applied', res.message, 'success');
       location.reload();
     } else {
       Swal.fire('Error', res.message, 'error');
     }
   });
 }
});
</script>
<?php include __DIR__.'/../../layouts/footer.php'; ?>
