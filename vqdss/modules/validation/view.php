<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/vqdss_final_helpers.php';

requirePermission('vqd.validation.view');

$pdo=db();$id=(int)($_GET['id']??0);
try{$stats=vqdRefreshValidation($pdo,$id);}catch(Throwable $e){$stats=null;}

$q=$pdo->prepare(
 "SELECT v.*,d.official_outcome,d.decision_statement,
         li.reference_number legislative_reference,li.title legislative_title,
         lv.full_name lead_name,rv.full_name reviewer_name,cv.full_name certifier_name
  FROM vqd_result_validations v
  LEFT JOIN vqd_decisions d ON d.id=v.decision_id
  LEFT JOIN legislative_items li ON li.id=v.legislative_item_id
  LEFT JOIN users lv ON lv.id=v.lead_validator_id
  LEFT JOIN users rv ON rv.id=v.reviewed_by_id
  LEFT JOIN users cv ON cv.id=v.certified_by
  WHERE v.id=:id"
);$q->execute([':id'=>$id]);$v=$q->fetch();
if(!$v){setFlash('warning','Validation not found.');redirect(appUrl('modules/validation/index.php'));}

$checks=$pdo->prepare('SELECT * FROM vqd_validation_checks WHERE validation_id=:id ORDER BY sequence_number,id');
$checks->execute([':id'=>$id]);$checkRows=$checks->fetchAll();

$findings=$pdo->prepare(
 "SELECT f.*,o.name office_name,u.full_name resolved_name
  FROM vqd_validation_findings f
  LEFT JOIN offices o ON o.id=f.responsible_office_id
  LEFT JOIN users u ON u.id=f.resolved_by
  WHERE f.validation_id=:id ORDER BY FIELD(f.severity,'Critical','High','Medium','Low'),f.id DESC"
);$findings->execute([':id'=>$id]);$findingRows=$findings->fetchAll();

$docs=$pdo->prepare('SELECT * FROM vqd_validation_documents WHERE validation_id=:id ORDER BY uploaded_at DESC');
$docs->execute([':id'=>$id]);$documents=$docs->fetchAll();

$reports=$pdo->prepare('SELECT * FROM vqd_validation_reports WHERE validation_id=:id ORDER BY version_number DESC');
$reports->execute([':id'=>$id]);$reportRows=$reports->fetchAll();

$history=$pdo->prepare(
 "SELECT h.*,u.full_name FROM vqd_validation_history h
  LEFT JOIN users u ON u.id=h.changed_by
  WHERE h.validation_id=:id ORDER BY h.created_at DESC,h.id DESC"
);$history->execute([':id'=>$id]);$historyRows=$history->fetchAll();

$offices=$pdo->query("SELECT id,name FROM offices WHERE status='Active' ORDER BY name")->fetchAll();

$pageTitle=$v['validation_reference'];$activeMenu='validation';$extraCss=[appUrl('assets/css/vqdss-final-ops.css')];
include __DIR__.'/../../layouts/header.php';
?>
<div class="app-wrapper"><?php include __DIR__.'/../../layouts/sidebar.php'; ?><main class="main-content">

<div class="vqdf-head"><div><a href="index.php" class="small text-decoration-none"><i class="bi bi-arrow-left"></i> Result Validation</a><div class="vqdf-eyebrow mt-2"><?= e($v['validation_reference']) ?></div><h1><?= e($v['decision_reference'].' · '.$v['official_outcome']) ?></h1><p><?= e(($v['legislative_reference']?:'No legislative item').' · '.$v['review_type']) ?></p></div><div class="d-flex gap-2 flex-wrap"><span class="vqdf-risk <?= e(strtolower($v['risk_level'])) ?>"><?= e($v['risk_level']) ?> Risk</span><span class="badge fs-6 text-bg-light"><?= e($v['validation_status']) ?></span><a class="btn btn-outline-secondary btn-sm" target="_blank" href="print.php?id=<?= $id ?>"><i class="bi bi-printer"></i></a></div></div>

<div class="vqdf-score mb-3"><strong><?= round((float)$v['integrity_score'],2) ?>%</strong><div><div class="fw-semibold">Integrity Score · <?= e($v['validation_outcome']) ?></div><span>Calculated from end-to-end quorum, voting, ballot, tally, and decision integrity checks plus unresolved findings.</span></div></div>

<div class="row g-3">
<div class="col-xl-8">
<div class="card vqdf-card mb-3"><div class="card-header">End-to-End Source Chain</div><div class="card-body"><div class="vqdf-trace"><div><strong>Quorum</strong><small><?= e($v['quorum_reference']?:'—') ?></small></div><div><strong>Voting</strong><small><?= e($v['voting_reference']?:'—') ?></small></div><div><strong>Tally</strong><small><?= e($v['tally_reference']?:'—') ?></small></div><div><strong>Decision</strong><small><?= e($v['decision_reference']?:'—') ?></small></div><div><strong>Validation</strong><small><?= e($v['validation_reference']) ?></small></div></div></div></div>

<div class="card vqdf-card mb-3"><div class="card-header">Integrity Checks</div><div>
<?php foreach($checkRows as $c): ?><div class="vqdf-check"><div><strong><?= e($c['check_name']) ?></strong><div class="small text-muted"><?= e($c['check_code']?:'Manual Check') ?> · <?= e($c['check_note']?:'No note') ?></div></div><div><span class="badge text-bg-<?= $c['check_status']==='Passed'?'success':($c['check_status']==='Failed'?'danger':($c['check_status']==='Warning'?'warning':'light')) ?>"><?= e($c['check_status']) ?></span></div><div><?= $c['reviewed_at']?formatDateTime($c['reviewed_at']):'Automatic' ?></div></div><?php endforeach; ?>
</div></div>

<div class="card vqdf-card mb-3"><div class="card-header d-flex justify-content-between"><span>Validation Findings</span><?php if(vqdOperationalManager()&&$v['validation_status']!=='Certified'): ?><button class="btn btn-sm btn-primary" id="btnFinding"><i class="bi bi-plus"></i> Add Finding</button><?php endif; ?></div><div class="table-responsive"><table class="table vqdf-table mb-0"><thead><tr><th>Finding</th><th>Severity</th><th>Responsible</th><th>Due</th><th>Status</th><th class="text-end">Update</th></tr></thead><tbody>
<?php if(!$findingRows): ?><tr><td colspan="6" class="text-center text-muted py-4">No findings recorded.</td></tr><?php endif; ?>
<?php foreach($findingRows as $f): ?><tr><td><strong><?= e($f['finding_title']) ?></strong><div class="small text-muted"><?= e(mb_strimwidth($f['finding_description']?:'',0,110,'…')) ?></div></td><td><span class="vqdf-risk <?= e(strtolower($f['severity'])) ?>"><?= e($f['severity']) ?></span></td><td><?= e($f['office_name']?:'Unassigned') ?></td><td><?= $f['due_date']?formatDate($f['due_date']):'—' ?></td><td><?= e($f['status']) ?></td><td class="text-end"><?php if(vqdOperationalManager()&&$v['validation_status']!=='Certified'): ?><button class="btn btn-sm btn-outline-primary edit-finding" data-row='<?= e(json_encode($f,JSON_HEX_APOS|JSON_HEX_QUOT)) ?>'><i class="bi bi-pencil"></i></button><?php endif; ?></td></tr><?php endforeach; ?>
</tbody></table></div></div>

<div class="card vqdf-card"><div class="card-header">Validation Evidence</div><div class="card-body"><div class="list-group mb-3"><?php if(!$documents): ?><div class="list-group-item text-muted">No evidence uploaded.</div><?php endif; ?><?php foreach($documents as $doc): ?><a class="list-group-item list-group-item-action" target="_blank" href="<?= e(appUrl('assets/uploads/'.$doc['file_path'])) ?>"><strong><?= e($doc['file_name']) ?></strong><div class="small text-muted"><?= e($doc['document_type'].' · '.$doc['visibility']) ?> · <?= formatDateTime($doc['uploaded_at']) ?></div></a><?php endforeach; ?></div><?php if(vqdOperationalManager()): ?><form id="docForm" class="row g-2" enctype="multipart/form-data"><?= csrfField() ?><input type="hidden" name="validation_id" value="<?= $id ?>"><div class="col-md-6"><input type="file" class="form-control form-control-sm" name="document" required></div><div class="col-md-3"><input class="form-control form-control-sm" name="document_type" value="Validation Evidence"></div><div class="col-md-3"><select class="form-select form-select-sm" name="visibility"><option>Internal</option><option>Public</option><option>Restricted</option></select></div><div class="col-12"><button class="btn btn-outline-primary btn-sm">Upload Evidence</button></div></form><?php endif; ?></div></div>
</div>

<div class="col-xl-4">
<?php if(vqdOperationalManager()): ?><div class="card vqdf-card mb-3" style="border-top: 3px solid #f59e0b;"><div class="card-header d-flex justify-content-between align-items-center"><span>Validation Workflow</span><button type="button" class="btn btn-sm btn-outline-warning py-0 px-2" id="btnAiAudit" style="font-size:0.8rem;"><i class="bi bi-stars"></i> AI Audit Review</button></div><div class="card-body"><form id="workflowForm"><?= csrfField() ?><input type="hidden" name="id" value="<?= $id ?>"><label class="form-label small text-muted mb-0">Executive Summary</label><textarea class="form-control form-control-sm mb-2" name="executive_summary" id="wf_executive" rows="2" placeholder="Executive summary"><?= e($v['executive_summary']?:'') ?></textarea><label class="form-label small text-muted mb-0">Overall Findings</label><textarea class="form-control form-control-sm mb-2" name="overall_findings" id="wf_findings" rows="2" placeholder="Overall findings"><?= e($v['overall_findings']?:'') ?></textarea><label class="form-label small text-muted mb-0">Corrective Action</label><textarea class="form-control form-control-sm mb-2" name="corrective_action" id="wf_corrective" rows="2" placeholder="Corrective action"><?= e($v['corrective_action']?:'') ?></textarea><label class="form-label small text-muted mb-0">Management Response</label><textarea class="form-control form-control-sm mb-2" name="management_response" id="wf_response" rows="2" placeholder="Management response"><?= e($v['management_response']?:'') ?></textarea><label class="form-label small text-muted mb-0">Recommendation</label><textarea class="form-control form-control-sm mb-2" name="recommendation" id="wf_recommendation" rows="2" placeholder="Recommendation / revalidation reason"><?= e($v['recommendation']?:'') ?></textarea><label class="form-label small text-muted mb-0">Certification Statement</label><textarea class="form-control form-control-sm mb-2" name="certification_statement" id="wf_certification" rows="2" placeholder="Certification statement"><?= e($v['certification_statement']?:'') ?></textarea><div class="d-grid gap-2">
<?php if(in_array($v['validation_status'],['Draft','Reopened'],true)): ?><button type="button" class="btn btn-outline-primary validation-transition" data-action="review">Move to Review</button><?php endif; ?>
<?php if(in_array($v['validation_status'],['Draft','In Review','Reopened'],true)): ?><button type="button" class="btn btn-primary validation-transition" data-action="validate">Validate Result</button><?php endif; ?>
<?php if(in_array($v['validation_status'],['Validated','In Review'],true)): ?><button type="button" class="btn btn-success validation-transition" data-action="certify"><i class="bi bi-patch-check"></i> Certify Validation</button><?php endif; ?>
<?php if(in_array($v['validation_status'],['Certified','Failed'],true)): ?><button type="button" class="btn btn-outline-warning validation-transition" data-action="reopen">Reopen / Revalidate</button><?php endif; ?>
</div></form></div></div><?php endif; ?>

<div class="card vqdf-card mb-3"><div class="card-header d-flex justify-content-between"><span>Validation Reports</span><?php if(vqdOperationalManager()): ?><button class="btn btn-sm btn-primary" id="btnGenerateReport">Generate Version</button><?php endif; ?></div><div class="card-body"><div class="list-group"><?php if(!$reportRows): ?><div class="list-group-item text-muted">No generated report versions.</div><?php endif; ?><?php foreach($reportRows as $r): ?><div class="list-group-item"><div class="d-flex justify-content-between align-items-center"><div><strong><?= e($r['report_reference']?:'Version '.$r['version_number']) ?></strong><div class="small text-muted"><?= e($r['report_type'].' · '.$r['report_format'].' · '.$r['status']) ?></div></div><?php if(vqdOperationalManager()&&$v['validation_status']==='Certified'&&$r['status']!=='Released'): ?><button class="btn btn-sm btn-outline-success release-report" data-id="<?= (int)$r['id'] ?>">Release</button><?php endif; ?></div></div><?php endforeach; ?></div></div></div>

<div class="card vqdf-card"><div class="card-header">Validation History</div><div class="card-body vqdf-history"><?php foreach($historyRows as $h): ?><div><strong><?= e($h['action']) ?> · <?= e($h['full_name']?:'System') ?></strong><small><?= e($h['details']?:'') ?><br><?= formatDateTime($h['created_at']) ?></small></div><?php endforeach; ?></div></div>
</div>
</div>
</main></div>

<?php if(vqdOperationalManager()&&$v['validation_status']!=='Certified'): ?>
<div class="modal fade" id="findingModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered" style="max-width: 580px;"><div class="modal-content shadow-lg border-0" style="border-radius: 14px; overflow: hidden;"><form id="findingForm"><?= csrfField() ?><input type="hidden" name="validation_id" value="<?= $id ?>"><input type="hidden" name="id" id="f_id" value="0">
<div class="modal-header bg-dark text-white py-2.5 px-3.5"><h6 class="modal-title fw-bold mb-0">Validation Finding</h6><button class="btn-close btn-close-white" type="button" data-bs-dismiss="modal"></button></div>
<div class="modal-body p-3.5"><div class="row g-2">
<div class="col-12"><label class="form-label">Finding Title *</label><input class="form-control" name="finding_title" id="f_title" required></div>
<div class="col-12"><label class="form-label">Description</label><textarea class="form-control" name="finding_description" id="f_desc" rows="3"></textarea></div>
<div class="col-md-4"><label class="form-label">Severity</label><select class="form-select" name="severity" id="f_severity"><option>Low</option><option>Medium</option><option>High</option><option>Critical</option></select></div>
<div class="col-md-4"><label class="form-label">Responsible Office</label><select class="form-select" name="responsible_office_id" id="f_office"><option value="">Unassigned</option><?php foreach($offices as $o): ?><option value="<?= (int)$o['id'] ?>"><?= e($o['name']) ?></option><?php endforeach; ?></select></div>
<div class="col-md-4"><label class="form-label">Due Date</label><input type="date" class="form-control" name="due_date" id="f_due"></div>
<div class="col-md-4"><label class="form-label">Status</label><select class="form-select" name="status" id="f_status"><option>Open</option><option>In Progress</option><option>Resolved</option><option>Accepted Risk</option></select></div>
<div class="col-md-8"><label class="form-label">Corrective Action</label><input class="form-control" name="corrective_action" id="f_corrective"></div>
<div class="col-12"><label class="form-label">Resolution</label><textarea class="form-control" name="resolution" id="f_resolution" rows="2"></textarea></div>
</div></div><div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save Finding</button></div>
</form></div></div></div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded',function(){
 <?php if(vqdOperationalManager()): ?>
 document.querySelectorAll('.validation-transition').forEach(b=>b.onclick=async function(){const fd=new FormData(workflowForm);fd.append('action',this.dataset.action);const r=await fetch('ajax_transition.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:fd}).then(x=>x.json());if(r.success){await Swal.fire('Validation Updated',r.message,'success');location.reload();}else Swal.fire('Validation Error',r.message,'error');});
 docForm.onsubmit=async e=>{e.preventDefault();const r=await fetch('ajax_upload_document.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:new FormData(docForm)}).then(x=>x.json());if(r.success)location.reload();else Swal.fire('Upload Error',r.message,'error');};
 btnGenerateReport.onclick=async()=>{const fd=new FormData();fd.append('csrf_token','<?= e(csrfToken()) ?>');fd.append('validation_id','<?= $id ?>');fd.append('action','generate');const r=await fetch('ajax_generate_report.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:fd}).then(x=>x.json());if(r.success)location.reload();else Swal.fire('Report Error',r.message,'error');};
 document.querySelectorAll('.release-report').forEach(b=>b.onclick=async function(){const fd=new FormData();fd.append('csrf_token','<?= e(csrfToken()) ?>');fd.append('validation_id','<?= $id ?>');fd.append('report_id',this.dataset.id);fd.append('action','release');const r=await fetch('ajax_generate_report.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:fd}).then(x=>x.json());if(r.success)location.reload();else Swal.fire('Report Error',r.message,'error');});
 <?php if($v['validation_status']!=='Certified'): ?>
 const fmodal=new bootstrap.Modal(document.getElementById('findingModal'));
 btnFinding.onclick=()=>{findingForm.reset();f_id.value='0';f_status.value='Open';f_severity.value='Low';fmodal.show();};
 document.querySelectorAll('.edit-finding').forEach(b=>b.onclick=function(){const f=JSON.parse(this.dataset.row);f_id.value=f.id;f_title.value=f.finding_title||'';f_desc.value=f.finding_description||'';f_severity.value=f.severity||'Low';f_office.value=f.responsible_office_id||'';f_due.value=f.due_date||'';f_status.value=f.status||'Open';f_corrective.value=f.corrective_action||'';f_resolution.value=f.resolution||'';fmodal.show();});
 findingForm.onsubmit=async e=>{e.preventDefault();const r=await fetch('ajax_finding_save.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:new FormData(findingForm)}).then(x=>x.json());if(r.success)location.reload();else Swal.fire('Finding Error',r.message,'error');};
 <?php endif; ?>

 // Ollama AI Legislative Audit Review
 const btnAudit = document.getElementById('btnAiAudit');
 if (btnAudit) {
   btnAudit.addEventListener('click', async () => {
     btnAudit.disabled = true;
     const origHtml = btnAudit.innerHTML;
     btnAudit.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Auditing with Ollama...';
     try {
       const fd = new FormData();
       fd.append('csrf_token', '<?= e(csrfToken()) ?>');
       fd.append('validation_id', '<?= $id ?>');
       fd.append('action', 'audit_review');

       const res = await fetch('ajax_ai_validation.php', {
         method: 'POST',
         headers: {'X-Requested-With': 'XMLHttpRequest'},
         body: fd
       }).then(x => x.json());

       if (!res.success) {
         Swal.fire('Ollama AI Audit', res.message || 'Audit review failed.', 'error');
         return;
       }

       const d = res.data || {};
       if (document.getElementById('wf_executive') && d.executive_summary) document.getElementById('wf_executive').value = d.executive_summary;
       if (document.getElementById('wf_findings') && d.overall_findings) document.getElementById('wf_findings').value = d.overall_findings;
       if (document.getElementById('wf_corrective') && d.corrective_action) document.getElementById('wf_corrective').value = d.corrective_action;
       if (document.getElementById('wf_response') && d.management_response) document.getElementById('wf_response').value = d.management_response;
       if (document.getElementById('wf_recommendation') && d.recommendation) document.getElementById('wf_recommendation').value = d.recommendation;
       if (document.getElementById('wf_certification') && d.certification_statement) document.getElementById('wf_certification').value = d.certification_statement;

       Swal.fire({
         toast: true,
         position: 'top-end',
         icon: 'success',
         title: 'AI Audit Complete · Form Populated',
         timer: 2000,
         showConfirmButton: false
       });
     } catch (err) {
       Swal.fire('Ollama AI Error', err.message, 'error');
     } finally {
       btnAudit.disabled = false;
       btnAudit.innerHTML = origHtml;
     }
   });
 }
 <?php endif; ?>
});
</script>
<?php include __DIR__.'/../../layouts/footer.php'; ?>
