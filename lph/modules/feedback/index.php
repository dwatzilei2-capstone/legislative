<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/lph_module_helpers.php';
require_once __DIR__ . '/../../config/ai_config.php';
require_once __DIR__ . '/../../includes/AI/AIAnalysisManager.php';
require_once __DIR__ . '/../../includes/AI/CEFAIAnalysisManager.php';

requireLogin();

$pdo=db();
$pageTitle='Public Feedback';
$activeMenu='feedback';
$canReview=hasPermission('lph.feedback.review');
$aiAvailable=AI_ENABLED && AIAnalysisManager::tablesExist();
$aiReviewReady=$aiAvailable && AIAnalysisManager::reviewFeaturesReady();
$cefAiAvailable=AI_ENABLED && CEFAIAnalysisManager::tablesExist();
$cefRecords=[];

if($canReview&&$cefAiAvailable){
    $cefRecords=$pdo->query(
        "SELECT
            s.id,s.reference_number,s.submission_type,s.title,s.details,
            s.priority_level,s.source_channel,s.status submission_status,
            s.created_at,c.name category_name,
            ai.status ai_status,ai.sentiment,ai.urgency_level,
            ai.flagged_for_review,ai.review_status,ai.summary
         FROM cef_submissions s
         LEFT JOIN cef_categories c ON c.id=s.category_id
         LEFT JOIN lph_cef_ai_analysis ai ON ai.submission_id=s.id
         WHERE s.deleted_at IS NULL
           AND s.submission_type IN ('Feedback','Complaint')
         ORDER BY s.created_at DESC,s.id DESC
         LIMIT 20"
    )->fetchAll();
}

$categories=$pdo->query('SELECT id,name FROM feedback_categories ORDER BY name')->fetchAll();
$hearings=$pdo->query(
 "SELECT id,reference_number,title,hearing_date,status
  FROM hearings
  WHERE status<>'Cancelled'
  ORDER BY hearing_date DESC
  LIMIT 200"
)->fetchAll();

$items=$pdo->query(
 "SELECT li.id,li.reference_number,li.title,lit.name type_name
  FROM legislative_items li
  JOIN legislative_item_types lit ON lit.id=li.item_type_id
  WHERE li.deleted_at IS NULL
  ORDER BY li.created_at DESC
  LIMIT 300"
)->fetchAll();

$aiSelect=$aiAvailable
    ? ",ai.analysis_scope ai_analysis_scope,ai.sentiment ai_sentiment,
       ai.confidence_score ai_confidence_score,ai.urgency_level ai_urgency_level,
       ai.urgency_score ai_urgency_score,ai.keyword_score ai_keyword_score,
       ai.summary ai_summary,ai.keywords ai_keywords,ai.risk_keywords ai_risk_keywords,
       ai.recommended_category ai_recommended_category,
       ai.suggested_response ai_suggested_response,
       ai.flagged_for_review ai_flagged_for_review,
       ai.status ai_status,ai.error_message ai_error_message"
       .($aiReviewReady
         ? ",ai.review_status ai_review_status,ai.review_notes ai_review_notes,
            ai.reviewed_by ai_reviewed_by,ai.reviewed_at ai_reviewed_at,
            ai.analysis_version ai_analysis_version,
            (SELECT COUNT(*) FROM feedback_ai_analysis_history hh
             WHERE hh.feedback_id=f.id) ai_history_count"
         : ",NULL ai_review_status,NULL ai_review_notes,NULL ai_reviewed_by,
            NULL ai_reviewed_at,1 ai_analysis_version,0 ai_history_count")
    : ",NULL ai_analysis_scope,NULL ai_sentiment,NULL ai_confidence_score,
       NULL ai_urgency_level,NULL ai_urgency_score,NULL ai_keyword_score,
       NULL ai_summary,NULL ai_keywords,NULL ai_risk_keywords,
       NULL ai_recommended_category,NULL ai_suggested_response,
       0 ai_flagged_for_review,NULL ai_status,NULL ai_error_message,
       NULL ai_review_status,NULL ai_review_notes,NULL ai_reviewed_by,
       NULL ai_reviewed_at,1 ai_analysis_version,0 ai_history_count";

$aiJoin=$aiAvailable
    ? "LEFT JOIN feedback_ai_analysis ai ON ai.feedback_id=f.id"
    : "";

$feedback=$pdo->query(
 "SELECT f.*,fc.name category_name,h.title hearing_title,h.reference_number hearing_reference,
         li.reference_number legislative_reference,li.title legislative_title,
         vu.full_name validated_by_name,ru.full_name replied_by_name,
         sr.name submitter_role,
         CASE
           WHEN LOWER(COALESCE(fc.name,''))='complaint'
             OR f.user_id IS NULL
             OR f.stakeholder_id IS NOT NULL
             OR sr.name IN ('Registered Stakeholder','Public User')
           THEN 1 ELSE 0
         END ai_eligible
         {$aiSelect}
  FROM feedback f
  LEFT JOIN feedback_categories fc ON fc.id=f.category_id
  LEFT JOIN hearings h ON h.id=f.hearing_id
  LEFT JOIN legislative_items li ON li.id=f.legislative_item_id
  LEFT JOIN users vu ON vu.id=f.validated_by
  LEFT JOIN users ru ON ru.id=f.replied_by
  LEFT JOIN users su ON su.id=f.user_id
  LEFT JOIN roles sr ON sr.id=su.role_id
  {$aiJoin}
  ORDER BY f.submitted_at DESC,f.id DESC"
)->fetchAll();

$stats=['total'=>0,'new'=>0,'review'=>0,'validated'=>0,'responded'=>0];
$s=$pdo->query(
 "SELECT COUNT(*) total,
  SUM(status='New') new,
  SUM(status='Under Review') review,
  SUM(status='Validated') validated,
  SUM(status='Responded') responded
  FROM feedback"
)->fetch();
foreach($stats as $k=>$v)$stats[$k]=(int)($s[$k]??0);

include __DIR__.'/../../layouts/header.php';
?>
<link rel="stylesheet" href="<?= e(APP_URL.'/assets/css/lph-complete-modules.css') ?>">
<div class="app-wrapper"><?php include __DIR__.'/../../layouts/sidebar.php'; ?><div class="main-content">
<?php include __DIR__ . '/../../layouts/top_controls.php'; ?>

<div class="lphx-head">
<div><div class="lphx-eyebrow"><i class="bi bi-chat-square-text"></i> Step 5</div><h1>Public Feedback Collection</h1><p>Collect structured public comments, positions, hearing feedback and legislative input, then validate and record official responses.</p></div>
<div class="d-flex gap-2 flex-wrap"><?php if($canReview&&$aiAvailable): ?><a class="btn btn-outline-secondary" href="ai_analytics.php"><i class="bi bi-robot"></i> AI Sentiment</a><?php endif; ?><a class="btn btn-outline-secondary" href="surveys.php"><i class="bi bi-ui-checks-grid"></i> Surveys</a><button class="btn btn-primary" id="btnNewFeedback"><i class="bi bi-chat-left-text"></i> Submit Feedback</button></div>
</div>

<div class="row g-3 mb-3">
<?php foreach([
 ['Total',$stats['total'],'bi-chat-dots'],
 ['New',$stats['new'],'bi-stars'],
 ['Under Review',$stats['review'],'bi-search'],
 ['Validated',$stats['validated'],'bi-patch-check'],
 ['Responded',$stats['responded'],'bi-reply-all'],
] as [$l,$v,$i]): ?><div class="col-6 col-md"><div class="lphx-stat"><i class="bi <?= e($i) ?>"></i><div><strong><?= $v ?></strong><small><?= e($l) ?></small></div></div></div><?php endforeach; ?>
</div>

<?php if($canReview&&$cefAiAvailable): ?>
<div class="card lphx-card mb-3">
<div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
<span><i class="bi bi-people"></i> Citizen Portal / CEPFMS Feedback &amp; Complaints</span>
<small class="text-light opacity-75">Directly read from CEPFMS—no duplicate LPH feedback records</small>
</div>
<div class="table-responsive">
<table class="table table-hover lphx-table mb-0 align-middle">
<thead><tr><th>Reference</th><th>Type</th><th>Title / Message</th><th>Portal Status</th><th>AI Result</th><th class="text-end">Review</th></tr></thead>
<tbody>
<?php if(!$cefRecords): ?><tr><td colspan="6"><div class="lphx-empty"><i class="bi bi-inboxes"></i>No Citizen Portal feedback or complaints found.</div></td></tr><?php endif; ?>
<?php foreach($cefRecords as $r): ?>
<tr>
<td><strong><?= e($r['reference_number']) ?></strong><div class="small text-muted"><?= e($r['source_channel']) ?></div></td>
<td><span class="badge text-bg-<?= $r['submission_type']==='Complaint'?'danger':'info' ?>"><?= e($r['submission_type']) ?></span><div class="small text-muted"><?= e($r['category_name']?:'Uncategorized') ?></div></td>
<td><strong><?= e($r['title']) ?></strong><div class="small text-muted"><?= e(mb_strimwidth((string)$r['details'],0,110,'…')) ?></div></td>
<td><span class="badge text-bg-light border"><?= e($r['submission_status']) ?></span><div class="small text-muted">Priority: <?= e($r['priority_level']) ?></div></td>
<td>
<?php if(($r['ai_status']??'')==='completed'): ?>
<span class="badge text-bg-<?= $r['sentiment']==='Negative'?'danger':($r['sentiment']==='Positive'?'success':'warning') ?>"><?= e($r['sentiment']) ?></span>
<span class="badge text-bg-<?= in_array($r['urgency_level'],['High','Critical'],true)?'danger':'secondary' ?>"><?= e(($r['urgency_level']?:'Low').' urgency') ?></span>
<?php elseif(($r['ai_status']??'')==='failed'): ?>
<span class="badge text-bg-danger">AI failed</span>
<?php else: ?>
<span class="badge text-bg-light border">Pending</span>
<?php endif; ?>
</td>
<td class="text-end"><a class="btn btn-sm btn-outline-primary" href="cef_ai_review.php?id=<?= (int)$r['id'] ?>"><i class="bi bi-search"></i> Review</a></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>
<?php endif; ?>

<div class="card lphx-card">
<div class="card-header"><i class="bi bi-inboxes"></i> Feedback Register</div>
<div class="table-responsive">
<table class="table table-hover lphx-table mb-0">
<thead><tr><th>Submitted</th><th>Citizen / Position</th><th>Subject / Message</th><th>Context</th><th>Status</th><?php if($canReview): ?><th>AI Sentiment</th><th class="text-end">Review</th><?php endif; ?></tr></thead>
<tbody>
<?php if(!$feedback): ?><tr><td colspan="<?= $canReview?7:5 ?>"><div class="lphx-empty"><i class="bi bi-chat-square-text"></i>No feedback submitted yet.</div></td></tr><?php endif; ?>
<?php foreach($feedback as $f): ?>
<tr>
<td><?= formatDateTime($f['submitted_at']) ?></td>
<td><strong><?= $f['is_anonymous']?'Anonymous':e($f['name']) ?></strong><div class="small text-muted"><?= e($f['feedback_position']?:'Comment') ?> · <?= e($f['category_name']?:'Uncategorized') ?></div></td>
<td><strong><?= e($f['subject']?:'(No subject)') ?></strong><div class="lphx-feedback-message"><?= e(mb_strimwidth($f['message'],0,180,'…')) ?></div><?php if(!empty($f['reply_text'])): ?><div class="small text-success mt-1"><i class="bi bi-reply"></i> Official response recorded</div><?php endif; ?></td>
<td><?php if($f['hearing_title']): ?><div><i class="bi bi-calendar-event"></i> <?= e($f['hearing_reference'].' '.$f['hearing_title']) ?></div><?php endif; ?><?php if($f['legislative_reference']): ?><div class="small text-muted"><i class="bi bi-file-text"></i> <?= e($f['legislative_reference']) ?></div><?php endif; ?></td>
<td><span class="badge text-bg-light"><?= e($f['status']) ?></span><div class="small text-muted"><?= e($f['visibility']) ?></div></td>
<?php if($canReview): ?>
<td>
<?php if(!$aiAvailable): ?>
<span class="badge text-bg-light">AI unavailable</span>
<?php elseif((int)($f['ai_eligible']??0)!==1): ?>
<span class="badge text-bg-light">Not eligible</span>
<?php elseif(($f['ai_status']??'')==='completed'): ?>
<?php
$sentimentClass=($f['ai_sentiment']??'')==='Negative'?'danger':(($f['ai_sentiment']??'')==='Positive'?'success':'warning');
$urgencyClass=in_array($f['ai_urgency_level']??'', ['High','Critical'], true)?'danger':(($f['ai_urgency_level']??'')==='Medium'?'warning':'secondary');
?>
<div class="d-flex gap-1 flex-wrap">
<span class="badge text-bg-<?= e($sentimentClass) ?>"><?= e($f['ai_sentiment']??'Neutral') ?></span>
<span class="badge text-bg-<?= e($urgencyClass) ?>"><?= e(($f['ai_urgency_level']??'Low').' urgency') ?></span>
<?php if((int)($f['ai_flagged_for_review']??0)===1): ?><span class="badge text-bg-danger"><i class="bi bi-flag-fill"></i></span><?php endif; ?>
</div>
<?php elseif(($f['ai_status']??'')==='failed'): ?>
<span class="badge text-bg-danger">AI failed</span>
<?php elseif(($f['ai_status']??'')==='pending'): ?>
<span class="badge text-bg-info">Analyzing</span>
<?php else: ?>
<span class="badge text-bg-light">Not analyzed</span>
<?php endif; ?>
</td>
<td class="text-end"><button class="btn btn-sm btn-outline-primary btn-review-feedback" data-row='<?= e(json_encode($f,JSON_HEX_APOS|JSON_HEX_QUOT)) ?>'><i class="bi bi-search"></i> Review</button></td>
<?php endif; ?>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>
</div></div><div class="modal fade" id="feedbackModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" style="max-width: 620px;">
    <div class="modal-content border-0 shadow-lg" style="border-radius: 14px; overflow: hidden;">
      <form id="feedbackForm">
        <?= csrfField() ?>
        <div class="modal-header py-2.5 px-3.5" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); border-bottom: 2px solid #3b82f6;">
          <div class="d-flex align-items-center gap-2">
            <div class="rounded-circle d-flex align-items-center justify-content-center bg-primary bg-opacity-25 text-info" style="width: 32px; height: 32px;">
              <i class="bi bi-chat-left-text fs-6"></i>
            </div>
            <div>
              <h6 class="modal-title text-white fw-bold mb-0" style="font-size: 0.95rem;">Submit Public Feedback</h6>
              <small class="text-white-50" style="font-size: 0.72rem;">Record public perspective on active hearings and ordinances</small>
            </div>
          </div>
          <button class="btn-close btn-close-white" type="button" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-3.5">
          <div class="row g-2">
            <div class="col-md-6">
              <label class="form-label small fw-semibold text-secondary mb-1">Name *</label>
              <input class="form-control form-control-sm" name="name" required>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold text-secondary mb-1">Email *</label>
              <input type="email" class="form-control form-control-sm" name="email" required>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold text-secondary mb-1">Related Hearing</label>
              <select class="form-select form-select-sm" name="hearing_id">
                <option value="">None</option>
                <?php foreach($hearings as $h): ?>
                  <option value="<?= (int)$h['id'] ?>"><?= e(($h['reference_number']?:'').' '.$h['title']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold text-secondary mb-1">Related Legislative Item</label>
              <select class="form-select form-select-sm" name="legislative_item_id">
                <option value="">None</option>
                <?php foreach($items as $i): ?>
                  <option value="<?= (int)$i['id'] ?>"><?= e('['.$i['type_name'].'] '.$i['reference_number'].' - '.$i['title']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-semibold text-secondary mb-1">Category</label>
              <select class="form-select form-select-sm" name="category_id">
                <option value="">Uncategorized</option>
                <?php foreach($categories as $c): ?>
                  <option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-semibold text-secondary mb-1">Position</label>
              <select class="form-select form-select-sm" name="feedback_position">
                <option>Comment</option>
                <option>Support</option>
                <option>Oppose</option>
                <option>Neutral</option>
              </select>
            </div>
            <?php if($canReview): ?>
              <div class="col-md-4">
                <label class="form-label small fw-semibold text-secondary mb-1">Visibility</label>
                <select class="form-select form-select-sm" name="visibility">
                  <option>Internal</option>
                  <option>Public</option>
                  <option>Restricted</option>
                </select>
              </div>
            <?php endif; ?>
            <div class="col-12">
              <label class="form-label small fw-semibold text-secondary mb-1">Subject</label>
              <input class="form-control form-control-sm" name="subject">
            </div>
            <div class="col-12">
              <label class="form-label small fw-semibold text-secondary mb-1">Message *</label>
              <textarea class="form-control form-control-sm" name="message" rows="4" required></textarea>
            </div>
            <div class="col-12">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="is_anonymous" id="anon">
                <label class="form-check-label small" for="anon">Display this feedback as anonymous after moderation</label>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer py-2 px-3.5 bg-light border-top d-flex justify-content-end gap-2">
          <button class="btn btn-sm btn-outline-secondary px-3" type="button" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-sm btn-primary px-3 fw-semibold shadow-sm">Submit Feedback</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php if($canReview): ?>
<div class="modal fade" id="reviewModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" style="max-width: 640px;">
    <div class="modal-content border-0 shadow-lg" style="border-radius: 14px; overflow: hidden;">
      <form id="reviewForm">
        <?= csrfField() ?>
        <input type="hidden" name="id" id="rv_id">
        <div class="modal-header py-2.5 px-3.5" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); border-bottom: 2px solid #3b82f6;">
          <div class="d-flex align-items-center gap-2">
            <div class="rounded-circle d-flex align-items-center justify-content-center bg-primary bg-opacity-25 text-info" style="width: 32px; height: 32px;">
              <i class="bi bi-shield-check fs-6"></i>
            </div>
            <div>
              <h6 class="modal-title text-white fw-bold mb-0" style="font-size: 0.95rem;">Review & Moderate Feedback</h6>
              <small class="text-white-50" style="font-size: 0.72rem;">Triage sentiment, visibility, and official disposition</small>
            </div>
          </div>
          <button class="btn-close btn-close-white" type="button" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-3.5">
          <div id="rv_message" class="lphx-feedback-message p-3 bg-light rounded mb-3"></div>

          <?php if($aiAvailable): ?>
          <div class="card mb-3 border-0 bg-light shadow-sm" style="border-radius: 10px;">
            <div class="card-body p-3">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="badge bg-warning text-dark"><i class="bi bi-stars"></i> AI Feedback Assistant</span>
                <button type="button" class="btn btn-sm btn-outline-warning" id="btnRunAiFeedback">Analyze with Ollama</button>
              </div>
              <div id="aiFeedbackSpinner" class="text-muted small d-none"><i class="bi bi-hourglass-split"></i> Running local Ollama inference...</div>
              <div id="aiFeedbackResult" class="small mt-2 d-none">
                <div class="p-2 rounded bg-white border">
                  <div><strong>Sentiment:</strong> <span id="aiFbSentiment"></span> | <strong>Urgency:</strong> <span id="aiFbUrgency"></span></div>
                  <div class="mt-1"><strong>Action Recommendation:</strong> <span id="aiFbAction"></span></div>
                  <div class="mt-1 text-muted" id="aiFbSummary"></div>
                </div>
              </div>
            </div>
          </div>
          <?php endif; ?>

          <div class="row g-2">
            <div class="col-md-6">
              <label class="form-label small fw-semibold text-secondary mb-1">Status</label>
              <select class="form-select form-select-sm" name="status" id="rv_status">
                <option>New</option>
                <option>Under Review</option>
                <option>Validated</option>
                <option>Responded</option>
                <option>Rejected</option>
                <option>Archived</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold text-secondary mb-1">Visibility</label>
              <select class="form-select form-select-sm" name="visibility" id="rv_visibility">
                <option>Internal</option>
                <option>Public</option>
                <option>Restricted</option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label small fw-semibold text-secondary mb-1">Official Response</label>
              <textarea class="form-control form-control-sm" name="reply_text" id="rv_reply" rows="4" placeholder="Record the official response or resolution."></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer py-2 px-3.5 bg-light border-top d-flex justify-content-end gap-2">
          <button class="btn btn-sm btn-outline-secondary px-3" type="button" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-sm btn-primary px-3 fw-semibold shadow-sm">Save Review</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded',function(){
 const fmodal=new bootstrap.Modal(document.getElementById('feedbackModal'));
 btnNewFeedback.onclick=()=>{feedbackForm.reset();fmodal.show();};
 feedbackForm.onsubmit=async e=>{e.preventDefault();const r=await appPostForm(APP_URL+'/modules/feedback/ajax_submit.php',feedbackForm);if(r.success){appToast('success',r.message);setTimeout(()=>location.reload(),400);}else if(!r.session_expired)Swal.fire('Feedback Error',r.message,'error');};
 <?php if($canReview): ?>
 const rmodal=new bootstrap.Modal(document.getElementById('reviewModal'));
 let currentFeedbackRow=null;

 function parseList(value){
   if(Array.isArray(value))return value;
   if(!value)return [];
   try{const parsed=JSON.parse(value);return Array.isArray(parsed)?parsed:[];}catch(e){return [];}
 }

 function esc(value){
   const d=document.createElement('div');
   d.textContent=value==null?'':String(value);
   return d.innerHTML;
 }

 function renderAi(analysis){
   const box=document.getElementById('rv_ai_result');
   if(!box)return;

   if(!analysis||!analysis.status){
     box.innerHTML='<span class="text-muted">This feedback has not been analyzed yet.</span>';
     return;
   }
   if(analysis.status==='pending'){
     box.innerHTML='<span class="text-info"><span class="spinner-border spinner-border-sm me-1"></span> Analysis is running...</span>';
     return;
   }
   if(analysis.status==='failed'){
     box.innerHTML='<span class="text-danger"><i class="bi bi-exclamation-triangle"></i> '+esc(analysis.error_message||'AI analysis failed.')+'</span>';
     return;
   }

   const sentiment=analysis.sentiment||'Neutral';
   const urgency=analysis.urgency_level||'Low';
   const sClass=sentiment==='Negative'?'danger':(sentiment==='Positive'?'success':'warning');
   const uClass=(urgency==='High'||urgency==='Critical')?'danger':(urgency==='Medium'?'warning':'secondary');
   const keywords=parseList(analysis.keywords);
   const riskKeywords=parseList(analysis.risk_keywords);

   box.innerHTML=`
     <div class="d-flex gap-2 flex-wrap mb-2">
       <span class="badge text-bg-${sClass}">Sentiment: ${esc(sentiment)}</span>
       <span class="badge text-bg-${uClass}">Urgency: ${esc(urgency)} ${analysis.urgency_score!=null?'('+Math.round(Number(analysis.urgency_score))+'%)':''}</span>
       <span class="badge text-bg-light">Confidence: ${analysis.confidence_score!=null?Math.round(Number(analysis.confidence_score))+'%':'-'}</span>
       ${Number(analysis.flagged_for_review||0)===1?'<span class="badge text-bg-danger"><i class="bi bi-flag-fill"></i> Staff review</span>':''}
     </div>
     <div class="mb-2"><strong>Version:</strong> ${esc(analysis.analysis_version||1)} ${analysis.review_status?'<span class="badge text-bg-light border ms-1">Review: '+esc(analysis.review_status)+'</span>':''}</div>
     <div class="mb-2"><strong>Summary:</strong> ${esc(analysis.summary||'-')}</div>
     <div class="mb-2"><strong>Keywords:</strong> ${keywords.length?keywords.map(k=>'<span class="badge text-bg-light border me-1">'+esc(k)+'</span>').join(''):'<span class="text-muted">none</span>'}</div>
     <div class="mb-2"><strong>Risk / urgency keywords:</strong> ${riskKeywords.length?riskKeywords.map(k=>'<span class="badge text-bg-warning me-1">'+esc(k)+'</span>').join(''):'<span class="text-muted">none</span>'}</div>
     <div class="mb-2"><strong>Recommended category:</strong> ${esc(analysis.recommended_category||'-')}</div>
     ${analysis.suggested_response?'<div><strong>Suggested response:</strong><div class="border rounded p-2 mt-1 bg-light">'+esc(analysis.suggested_response)+'</div><button type="button" class="btn btn-sm btn-outline-secondary mt-2" id="btnUseAiReply"><i class="bi bi-clipboard-check"></i> Use as response</button></div>':''}
   `;

   const use=document.getElementById('btnUseAiReply');
   if(use)use.onclick=()=>{rv_reply.value=analysis.suggested_response||'';appToast('success','AI draft copied. Review it before saving.');};

   const reviewControls=document.getElementById('rv_ai_review_controls');
   if(reviewControls){
     const completed=analysis.status==='completed';
     reviewControls.classList.toggle('d-none',!completed);

     if(completed){
       const reviewStatus=document.getElementById('rv_ai_review_status');
       const reviewNotes=document.getElementById('rv_ai_review_notes');
       const historyCount=document.getElementById('rv_ai_history_count');

       if(reviewStatus)reviewStatus.value=analysis.review_status||'Pending';
       if(reviewNotes)reviewNotes.value=analysis.review_notes||'';
       if(historyCount)historyCount.textContent='('+(analysis.history_count||analysis.ai_history_count||0)+')';
     }
   }
 }

 document.querySelectorAll('.btn-review-feedback').forEach(b=>b.onclick=function(){
   const r=JSON.parse(this.dataset.row);
   currentFeedbackRow=r;
   rv_id.value=r.id;
   rv_status.value=r.status||'New';
   rv_visibility.value=r.visibility||'Internal';
   rv_reply.value=r.reply_text||'';
   rv_message.textContent=(r.subject?'Subject: '+r.subject+'\n\n':'')+r.message;

   const eligible=Number(r.ai_eligible||0)===1;
   if(analyzeBtn)analyzeBtn.classList.toggle('d-none',!eligible);

   if(!eligible && !r.ai_status){
     const aiBox=document.getElementById('rv_ai_result');
     if(aiBox)aiBox.innerHTML='<span class="text-muted"><i class="bi bi-info-circle"></i> AI analysis is limited to citizen feedback and Complaint submissions.</span>';
   }else renderAi(r.ai_status?{
     status:r.ai_status,
     sentiment:r.ai_sentiment,
     confidence_score:r.ai_confidence_score,
     urgency_level:r.ai_urgency_level,
     urgency_score:r.ai_urgency_score,
     keyword_score:r.ai_keyword_score,
     summary:r.ai_summary,
     keywords:r.ai_keywords,
     risk_keywords:r.ai_risk_keywords,
     recommended_category:r.ai_recommended_category,
     suggested_response:r.ai_suggested_response,
     flagged_for_review:r.ai_flagged_for_review,
     error_message:r.ai_error_message,
     review_status:r.ai_review_status,
     review_notes:r.ai_review_notes,
     reviewed_at:r.ai_reviewed_at,
     analysis_version:r.ai_analysis_version,
     history_count:r.ai_history_count
   }:null);

   rmodal.show();
 });

 const analyzeBtn=document.getElementById('btnAnalyzeFeedback');
 if(analyzeBtn)analyzeBtn.onclick=async()=>{
   if(!currentFeedbackRow)return;

   const oldHtml=analyzeBtn.innerHTML;
   analyzeBtn.disabled=true;
   analyzeBtn.innerHTML='<span class="spinner-border spinner-border-sm me-1"></span> Analyzing...';
   renderAi({status:'pending'});

   const r=await appPost(APP_URL+'/modules/feedback/ajax_analyze.php',{
     id:currentFeedbackRow.id,
     csrf_token:window.APP_CSRF_TOKEN
   });

   analyzeBtn.disabled=false;
   analyzeBtn.innerHTML=oldHtml;

   if(r.success){
     renderAi(r.ai_analysis);
     appToast('success',r.message||'AI analysis complete.');
   }else if(!r.session_expired){
     if(r.ai_analysis)renderAi(r.ai_analysis);
     Swal.fire('AI Analysis',r.message||'Unable to analyze this feedback.','warning');
   }
 };

 const saveAiReviewBtn=document.getElementById('btnSaveAiReview');
 if(saveAiReviewBtn)saveAiReviewBtn.onclick=async()=>{
   if(!currentFeedbackRow)return;

   const decision=document.getElementById('rv_ai_review_status').value;
   const notes=document.getElementById('rv_ai_review_notes').value;

   saveAiReviewBtn.disabled=true;
   const old=saveAiReviewBtn.innerHTML;
   saveAiReviewBtn.innerHTML='<span class="spinner-border spinner-border-sm me-1"></span> Saving...';

   const r=await appPost(APP_URL+'/modules/feedback/ajax_review_ai.php',{
     id:currentFeedbackRow.id,
     decision:decision,
     notes:notes,
     csrf_token:window.APP_CSRF_TOKEN
   });

   saveAiReviewBtn.disabled=false;
   saveAiReviewBtn.innerHTML=old;

   if(r.success){
     if(r.ai_analysis){
       r.ai_analysis.history_count=currentFeedbackRow.ai_history_count||0;
       renderAi(r.ai_analysis);
     }
     appToast('success',r.message||'AI review saved.');
   }else if(!r.session_expired){
     Swal.fire('AI Review',r.message||'Unable to save AI review.','warning');
   }
 };

 const aiHistoryBtn=document.getElementById('btnAiHistory');
 if(aiHistoryBtn)aiHistoryBtn.onclick=async()=>{
   if(!currentFeedbackRow)return;
   const box=document.getElementById('rv_ai_history');

   if(!box.classList.contains('d-none')){
     box.classList.add('d-none');
     return;
   }

   box.classList.remove('d-none');
   box.innerHTML='<div class="text-muted small"><span class="spinner-border spinner-border-sm me-1"></span> Loading history...</div>';

   try{
     const res=await fetch(
       APP_URL+'/modules/feedback/ajax_ai_history.php?id='+encodeURIComponent(currentFeedbackRow.id),
       {headers:{'X-Requested-With':'XMLHttpRequest'}}
     );
     const r=await res.json();

     if(!r.success){
       box.innerHTML='<div class="alert alert-warning py-2 mb-0">'+esc(r.message||'Unable to load AI history.')+'</div>';
       return;
     }

     const rows=Array.isArray(r.history)?r.history:[];
     if(!rows.length){
       box.innerHTML='<div class="text-muted small">No previous AI analysis versions yet.</div>';
       return;
     }

     box.innerHTML='<div class="small fw-semibold mb-2">Previous AI Analysis Versions</div>'+
       rows.map(h=>{
         const kws=parseList(h.keywords);
         const risks=parseList(h.risk_keywords);
         return `
           <div class="border rounded p-2 mb-2 bg-light">
             <div class="d-flex justify-content-between flex-wrap gap-2">
               <strong>Version ${esc(h.analysis_version||1)}</strong>
               <span>${esc(h.sentiment||'-')} · ${esc(h.urgency_level||'-')} urgency</span>
             </div>
             <div class="text-muted">${esc(h.analyzed_at||h.archived_at||'')}</div>
             <div class="mt-1">${esc(h.summary||h.error_message||'-')}</div>
             ${kws.length?'<div class="mt-1"><strong>Keywords:</strong> '+kws.map(esc).join(', ')+'</div>':''}
             ${risks.length?'<div class="mt-1"><strong>Risk:</strong> '+risks.map(esc).join(', ')+'</div>':''}
             <div class="mt-1"><strong>Staff Review:</strong> ${esc(h.review_status||'Pending')}</div>
           </div>
         `;
       }).join('');
   }catch(e){
     box.innerHTML='<div class="alert alert-warning py-2 mb-0">Unable to load AI history.</div>';
   }
 };

 reviewForm.onsubmit=async e=>{e.preventDefault();const r=await appPostForm(APP_URL+'/modules/feedback/ajax_review.php',reviewForm);if(r.success){appToast('success',r.message);setTimeout(()=>location.reload(),400);}else if(!r.session_expired)Swal.fire('Review Error',r.message,'error');};
 const reviewFromQuery=new URLSearchParams(window.location.search).get('review');
 if(reviewFromQuery){
   const target=[...document.querySelectorAll('.btn-review-feedback')].find(btn=>{
     try{return String(JSON.parse(btn.dataset.row).id)===String(reviewFromQuery);}catch(e){return false;}
   });
   if(target)setTimeout(()=>target.click(),100);
 }
 <?php endif; ?>
});
</script>
<?php include __DIR__.'/../../layouts/footer.php'; ?>
