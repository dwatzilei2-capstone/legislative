<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/ai_config.php';
require_once __DIR__ . '/../../includes/AI/CEFAIAnalysisManager.php';
requireRole([ROLE_ADMIN, ROLE_STAFF]);

$pageTitle='Citizen Portal AI Review';
$activeMenu='ai_sentiment';
$pdo=db();

$id=(int)($_GET['id']??0);
if($id<=0){
    http_response_code(404);
    exit('Invalid Citizen Portal submission id.');
}

$stmt=$pdo->prepare(
    "SELECT
        s.*,
        c.name category_name,
        cf.feedback_kind,
        cf.service_area,
        cf.desired_outcome,
        cf.citizen_rating,
        cc.affected_service,
        cc.incident_datetime,
        cc.urgency_level citizen_reported_urgency
     FROM cef_submissions s
     LEFT JOIN cef_categories c ON c.id=s.category_id
     LEFT JOIN cef_feedback_submissions cf ON cf.submission_id=s.id
     LEFT JOIN cef_complaints cc ON cc.submission_id=s.id
     WHERE s.id=:id
       AND s.deleted_at IS NULL
       AND s.submission_type IN ('Feedback','Complaint')
     LIMIT 1"
);
$stmt->execute([':id'=>$id]);
$submission=$stmt->fetch();

if(!$submission){
    http_response_code(404);
    exit('Citizen Portal feedback/complaint was not found.');
}

$analysis=CEFAIAnalysisManager::getAnalysis($id);
$history=CEFAIAnalysisManager::getHistory($id);

include __DIR__ . '/../../layouts/header.php';
?>
<div class="app-wrapper">
<?php include __DIR__ . '/../../layouts/sidebar.php'; ?>
<div class="main-content">
<?php include __DIR__ . '/../../layouts/top_controls.php'; ?>

<div class="container-fluid py-3">
<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
<div>
<div class="text-uppercase small text-muted fw-semibold">Citizen Portal / CEPFMS</div>
<h3 class="mb-1"><?= e($submission['reference_number']) ?> · <?= e($submission['title']) ?></h3>
<div class="text-muted"><?= e($submission['submission_type']) ?> · <?= e($submission['category_name']?:'Uncategorized') ?> · <?= e($submission['source_channel']) ?></div>
</div>
<a class="btn btn-outline-secondary" href="ai_analytics.php"><i class="bi bi-arrow-left"></i> AI Analytics</a>
</div>

<div class="row g-3">
<div class="col-xl-7">
<div class="card mb-3">
<div class="card-header"><i class="bi bi-person-lines-fill"></i> Citizen Submission</div>
<div class="card-body">
<div class="row g-3 mb-3">
<div class="col-md-6"><small class="text-muted d-block">Citizen</small><strong><?= e($submission['citizen_name']?:'Anonymous') ?></strong></div>
<div class="col-md-3"><small class="text-muted d-block">Portal Priority</small><strong><?= e($submission['priority_level']) ?></strong></div>
<div class="col-md-3"><small class="text-muted d-block">Status</small><strong><?= e($submission['status']) ?></strong></div>
</div>
<?php if(!empty($submission['summary'])): ?>
<div class="mb-3"><small class="text-muted d-block">Citizen Summary</small><?= nl2br(e($submission['summary'])) ?></div>
<?php endif; ?>
<div class="mb-3"><small class="text-muted d-block">Details / Message</small><div class="border rounded bg-light p-3"><?= nl2br(e($submission['details'])) ?></div></div>

<?php if($submission['submission_type']==='Complaint'): ?>
<div class="row g-3">
<div class="col-md-4"><small class="text-muted d-block">Citizen-selected Urgency</small><strong><?= e($submission['citizen_reported_urgency']?:'Normal') ?></strong></div>
<div class="col-md-4"><small class="text-muted d-block">Affected Service</small><strong><?= e($submission['affected_service']?:'-') ?></strong></div>
<div class="col-md-4"><small class="text-muted d-block">Incident</small><strong><?= e($submission['incident_datetime']?:'-') ?></strong></div>
</div>
<?php else: ?>
<div class="row g-3">
<div class="col-md-4"><small class="text-muted d-block">Feedback Kind</small><strong><?= e($submission['feedback_kind']?:'General Feedback') ?></strong></div>
<div class="col-md-4"><small class="text-muted d-block">Service Area</small><strong><?= e($submission['service_area']?:'-') ?></strong></div>
<div class="col-md-4"><small class="text-muted d-block">Citizen Rating</small><strong><?= e((string)($submission['citizen_rating']?:'-')) ?></strong></div>
</div>
<?php endif; ?>
</div>
</div>
</div>

<div class="col-xl-5">
<div class="card mb-3">
<div class="card-header d-flex justify-content-between align-items-center gap-2 flex-wrap">
<span><i class="bi bi-robot"></i> Ollama Sentiment & Urgency</span>
<button class="btn btn-sm btn-primary" id="btnAnalyzeCef"><i class="bi bi-stars"></i> <?= ($analysis&&($analysis['status']??'')==='completed')?'Re-analyze':'Analyze' ?></button>
</div>
<div class="card-body" id="cefAiResult">
<?php if(!$analysis): ?>
<div class="text-muted">Not analyzed yet.</div>
<?php elseif(($analysis['status']??'')==='failed'): ?>
<div class="alert alert-warning mb-0"><?= e($analysis['error_message']?:'AI analysis failed.') ?></div>
<?php elseif(($analysis['status']??'')!=='completed'): ?>
<div class="text-muted">AI status: <?= e($analysis['status']??'pending') ?></div>
<?php else: ?>
<?php
$sClass=$analysis['sentiment']==='Negative'?'danger':($analysis['sentiment']==='Positive'?'success':'warning');
$uClass=in_array($analysis['urgency_level'],['High','Critical'],true)?'danger':($analysis['urgency_level']==='Medium'?'warning':'secondary');
?>
<div class="d-flex gap-2 flex-wrap mb-3">
<span class="badge text-bg-<?= e($sClass) ?>">Sentiment: <?= e($analysis['sentiment']) ?></span>
<span class="badge text-bg-<?= e($uClass) ?>">Urgency: <?= e($analysis['urgency_level']) ?> <?= $analysis['urgency_score']!==null?'('.(int)round((float)$analysis['urgency_score']).'%)':'' ?></span>
<span class="badge text-bg-light border">Confidence: <?= (int)round((float)$analysis['confidence_score']) ?>%</span>
<?php if((int)$analysis['flagged_for_review']===1): ?><span class="badge text-bg-danger"><i class="bi bi-flag-fill"></i> Staff review</span><?php endif; ?>
</div>
<p><strong>Summary:</strong><br><?= e($analysis['summary']?:'-') ?></p>
<p><strong>Keywords:</strong><br><?= e(implode(', ',$analysis['keywords']??[])) ?></p>
<p><strong>Risk / Urgency Keywords:</strong><br><?= e(implode(', ',$analysis['risk_keywords']??[])) ?></p>
<p><strong>Recommended Category:</strong><br><?= e($analysis['recommended_category']?:'-') ?></p>
<?php if(!empty($analysis['suggested_response'])): ?>
<p class="mb-0"><strong>Suggested Staff Response:</strong></p>
<div class="border rounded p-2 bg-light small"><?= nl2br(e($analysis['suggested_response'])) ?></div>
<?php endif; ?>
<?php endif; ?>
</div>
</div>

<?php if($analysis&&($analysis['status']??'')==='completed'): ?>
<div class="card mb-3">
<div class="card-header"><i class="bi bi-check2-square"></i> Staff AI Review</div>
<div class="card-body">
<div class="mb-2">
<select class="form-select" id="cefReviewStatus">
<?php foreach(['Pending','Accepted','Manual Review','Dismissed'] as $value): ?>
<option value="<?= e($value) ?>" <?= ($analysis['review_status']??'Pending')===$value?'selected':'' ?>><?= e($value==='Manual Review'?'Needs Manual Review':$value) ?></option>
<?php endforeach; ?>
</select>
</div>
<textarea class="form-control mb-2" id="cefReviewNotes" rows="3" placeholder="Optional staff note"><?= e($analysis['review_notes']??'') ?></textarea>
<button class="btn btn-outline-primary w-100" id="btnSaveCefReview">Save AI Review</button>
</div>
</div>
<?php endif; ?>
</div>
</div>

<div class="card mt-3">
<div class="card-header"><i class="bi bi-clock-history"></i> Re-analysis History</div>
<div class="card-body">
<?php if(!$history): ?><div class="text-muted">No previous AI versions.</div><?php endif; ?>
<?php foreach($history as $h): ?>
<div class="border rounded p-2 mb-2 bg-light small">
<div class="d-flex justify-content-between gap-2 flex-wrap"><strong>Version <?= (int)$h['analysis_version'] ?></strong><span><?= e(($h['sentiment']?:'-').' · '.($h['urgency_level']?:'-').' urgency') ?></span></div>
<div class="text-muted"><?= e((string)($h['analyzed_at']?:$h['archived_at'])) ?></div>
<div class="mt-1"><?= e($h['summary']?:$h['error_message']?:'-') ?></div>
<div class="mt-1"><strong>Staff Review:</strong> <?= e($h['review_status']?:'Pending') ?></div>
</div>
<?php endforeach; ?>
</div>
</div>

</div>
</div>
</div>

<script>
document.addEventListener('DOMContentLoaded',()=>{
  const analyze=document.getElementById('btnAnalyzeCef');
  if(analyze)analyze.onclick=async()=>{
    const old=analyze.innerHTML;
    analyze.disabled=true;
    analyze.innerHTML='<span class="spinner-border spinner-border-sm me-1"></span> Analyzing...';
    const r=await appPost(APP_URL+'/modules/feedback/ajax_analyze_cef.php',{
      id:<?= (int)$id ?>,
      csrf_token:window.APP_CSRF_TOKEN
    });
    analyze.disabled=false;
    analyze.innerHTML=old;
    if(r.success){appToast('success',r.message);setTimeout(()=>location.reload(),500);}
    else if(!r.session_expired)Swal.fire('Citizen Portal AI',r.message||'Analysis failed.','warning');
  };

  const save=document.getElementById('btnSaveCefReview');
  if(save)save.onclick=async()=>{
    save.disabled=true;
    const r=await appPost(APP_URL+'/modules/feedback/ajax_review_cef_ai.php',{
      id:<?= (int)$id ?>,
      decision:document.getElementById('cefReviewStatus').value,
      notes:document.getElementById('cefReviewNotes').value,
      csrf_token:window.APP_CSRF_TOKEN
    });
    save.disabled=false;
    if(r.success){appToast('success',r.message);setTimeout(()=>location.reload(),400);}
    else if(!r.session_expired)Swal.fire('AI Review',r.message||'Unable to save review.','warning');
  };
});
</script>
<?php include __DIR__ . '/../../layouts/footer.php'; ?>
