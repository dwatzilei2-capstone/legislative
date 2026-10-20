<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../includes/AI/OllamaService.php';
requirePermission('orlms.ai.view');
$pdo=db();
$documents=$pdo->query("SELECT li.id,li.reference_number,li.title,li.priority_level,li.current_status,t.name item_type,
                              d.legislative_category,d.policy_area,d.keywords
                       FROM legislative_items li JOIN legislative_item_types t ON t.id=li.item_type_id
                       LEFT JOIN orlms_item_details d ON d.legislative_item_id=li.id
                       WHERE li.deleted_at IS NULL AND t.code IN ('ordinance','resolution')
                       ORDER BY li.updated_at DESC,li.id DESC")->fetchAll();
$selectedDocument=max(0,(int)($_GET['document_id']??0));
$health=(new OllamaService())->healthCheck();
$pageTitle='Ollama AI Legislative Assistant';$activeMenu='ai_classification';
$extraCss=[APP_URL.'/assets/css/orlms-module-pages.css'];include __DIR__.'/../layouts/header.php';
?>
<div class="orlms-app-wrapper"><?php include __DIR__.'/../layouts/sidebar.php'; ?><main class="orlms-main-content">
<section class="scope-module-header"><div><div class="scope-module-eyebrow"><i class="bi bi-stars"></i> Local AI · Human Reviewed</div><h1>Ollama AI Legislative Assistant</h1><p>Classify ordinances and resolutions, identify review flags, and assess draft readiness using the local Ollama model.</p></div><span class="ai-planned-pill"><i class="bi bi-<?= $health['available']&&$health['model_installed']?'check-circle':'exclamation-triangle' ?>"></i> <?= e($health['available']&&$health['model_installed']?'AI Ready':'AI Attention Needed') ?></span></section>
<div class="alert <?= $health['available']&&$health['model_installed']?'alert-success':'alert-warning' ?>"><strong><?= e($health['message']) ?></strong><br><small>Endpoint: <?= e(ORLMS_OLLAMA_BASE_URL) ?> · Model: <?= e(ORLMS_OLLAMA_MODEL) ?>. AI output is advisory and must be reviewed by authorized personnel.</small></div>
<div class="row g-4"><div class="col-xl-5"><section class="scope-panel h-100"><div class="scope-panel-heading"><div><h2><i class="bi bi-file-earmark-text"></i> Select Legislative Document</h2><p>Choose a current ordinance or resolution record.</p></div></div>
<label class="form-label fw-semibold">Document</label><select class="form-select mb-3" id="aiDocument"><option value="">Select document...</option><?php foreach($documents as $d): ?><option value="<?= (int)$d['id'] ?>" <?= $selectedDocument===(int)$d['id']?'selected':'' ?>><?= e($d['reference_number'].' — '.$d['title']) ?></option><?php endforeach; ?></select>
<div class="d-grid gap-2"><button class="btn btn-primary" id="btnClassify"><i class="bi bi-tags"></i> Analyze Classification</button><button class="btn btn-outline-primary" id="btnReview"><i class="bi bi-clipboard-check"></i> Review Draft Quality</button></div>
<hr><small class="text-muted">Classification suggestions are saved as Pending Review. They do not modify the official record until an authorized user selects Apply Approved Classification.</small></section></div>
<div class="col-xl-7"><section class="scope-panel h-100"><div class="scope-panel-heading"><div><h2><i class="bi bi-robot"></i> AI Analysis Result</h2><p id="aiResultStatus">No analysis generated in this session.</p></div></div><div id="aiResult" class="text-muted py-4 text-center"><i class="bi bi-stars fs-1 d-block mb-2"></i>Select a document and run an analysis.</div>
<?php if(hasPermission('orlms.ai.manage')): ?><button class="btn btn-success d-none mt-3" id="btnApply"><i class="bi bi-person-check"></i> Apply Approved Classification</button><?php endif; ?></section></div></div>
</main></div>
<script>
document.addEventListener('DOMContentLoaded',()=>{
 const select=document.getElementById('aiDocument'),out=document.getElementById('aiResult'),status=document.getElementById('aiResultStatus'),apply=document.getElementById('btnApply');let classification=null;
 const esc=v=>String(v??'').replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[c]));
 const list=v=>Array.isArray(v)&&v.length?'<ul class="mb-0">'+v.map(x=>'<li>'+esc(x)+'</li>').join('')+'</ul>':'<span class="text-muted">None identified</span>';
 async function run(action,button){if(!select.value)return Swal.fire('Select Document','Please choose an ordinance or resolution first.','info');button.disabled=true;button.innerHTML='<span class="spinner-border spinner-border-sm"></span> Analyzing with Ollama...';
  try{const fd=new FormData();fd.append('csrf_token','<?= e(csrfToken()) ?>');fd.append('legislative_item_id',select.value);fd.append('action',action);const r=await fetch('ajax_ai_classification.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:fd}).then(x=>x.json());if(!r.success)throw new Error(r.message||'Analysis failed.');const d=r.data;status.textContent=r.message;
   if(action==='classify'){classification=d;out.innerHTML='<div class="row g-3"><div class="col-md-6"><small>Suggested Category</small><strong class="d-block">'+esc(d.legislative_category)+'</strong></div><div class="col-md-6"><small>Policy Area</small><strong class="d-block">'+esc(d.policy_area)+'</strong></div><div class="col-md-6"><small>Priority</small><strong class="d-block">'+esc(d.recommended_priority)+'</strong></div><div class="col-md-6"><small>Confidence</small><strong class="d-block">'+esc(d.confidence_score)+'%</strong></div><div class="col-12"><small>Summary</small><p>'+esc(d.document_summary)+'</p></div><div class="col-12"><small>Reason</small><p>'+esc(d.classification_reason)+'</p></div><div class="col-md-6"><small>Keywords</small>'+list(d.keywords)+'</div><div class="col-md-6"><small>Review Flags</small>'+list(d.review_flags)+'</div></div>';apply?.classList.remove('d-none');}
   else{classification=null;apply?.classList.add('d-none');out.innerHTML='<h6>Executive Summary</h6><p>'+esc(d.executive_summary)+'</p><div class="row g-3"><div class="col-md-6"><strong>Strengths</strong>'+list(d.strengths)+'</div><div class="col-md-6"><strong>Clarity Issues</strong>'+list(d.clarity_issues)+'</div><div class="col-md-6"><strong>Missing Elements</strong>'+list(d.missing_elements)+'</div><div class="col-md-6"><strong>Legal Review Questions</strong>'+list(d.legal_review_questions)+'</div><div class="col-12"><strong>Recommended Edits</strong>'+list(d.recommended_edits)+'</div><div class="col-12"><span class="badge bg-primary">'+esc(d.readiness)+'</span></div></div>';}
  }catch(e){Swal.fire('Ollama AI',e.message,'error');}finally{button.disabled=false;button.innerHTML=action==='classify'?'<i class="bi bi-tags"></i> Analyze Classification':'<i class="bi bi-clipboard-check"></i> Review Draft Quality';}}
 document.getElementById('btnClassify').onclick=function(){run('classify',this)};document.getElementById('btnReview').onclick=function(){run('review',this)};
 if(apply)apply.onclick=async()=>{if(!classification)return;const ask=await Swal.fire({title:'Apply AI classification?',text:'Confirm that you reviewed the category, policy area, keywords, and priority.',icon:'question',showCancelButton:true,confirmButtonText:'Apply reviewed result'});if(!ask.isConfirmed)return;const fd=new FormData();fd.append('csrf_token','<?= e(csrfToken()) ?>');fd.append('action','apply');fd.append('legislative_item_id',select.value);const r=await fetch('ajax_ai_classification.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:fd}).then(x=>x.json());if(r.success){await Swal.fire('Applied',r.message,'success');location.reload();}else Swal.fire('Apply Failed',r.message,'error');};
});
</script>
<?php include __DIR__.'/../layouts/footer.php'; ?>
