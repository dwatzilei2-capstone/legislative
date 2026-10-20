<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/vqdss_final_helpers.php';

requirePermission('vqd.validation.view');

$pdo=db();$pageTitle='Result Validation & Reporting';$activeMenu='validation';
$extraCss=[appUrl('assets/css/vqdss-final-ops.css')];

$decisions=$pdo->query(
 "SELECT d.id,d.reference_number,d.decision_record_number,d.official_outcome,d.record_status,
         li.reference_number legislative_reference,li.title legislative_title,
         v.id validation_id,v.validation_reference,v.validation_status
  FROM vqd_decisions d
  LEFT JOIN legislative_items li ON li.id=d.legislative_item_id
  LEFT JOIN vqd_result_validations v
    ON v.decision_id=d.id
   AND v.id=(SELECT MAX(v2.id) FROM vqd_result_validations v2 WHERE v2.decision_id=d.id)
  WHERE d.record_status IN ('Certified','Published')
  ORDER BY d.certified_at DESC,d.id DESC"
)->fetchAll();

$validations=$pdo->query(
 "SELECT v.*,d.reference_number decision_reference_live,d.official_outcome,
         li.reference_number legislative_reference,li.title legislative_title,
         (SELECT COUNT(*) FROM vqd_validation_findings f WHERE f.validation_id=v.id AND f.status<>'Resolved') open_findings,
         (SELECT COUNT(*) FROM vqd_validation_checks c WHERE c.validation_id=v.id AND c.check_status='Failed') failed_checks
  FROM vqd_result_validations v
  LEFT JOIN vqd_decisions d ON d.id=v.decision_id
  LEFT JOIN legislative_items li ON li.id=v.legislative_item_id
  ORDER BY v.created_at DESC,v.id DESC"
)->fetchAll();

$offices=$pdo->query("SELECT id,name FROM offices WHERE status='Active' ORDER BY name")->fetchAll();
$users=$pdo->query("SELECT id,full_name FROM users WHERE status='Active' AND deleted_at IS NULL ORDER BY full_name")->fetchAll();

$stats=$pdo->query(
 "SELECT COUNT(*) total,SUM(validation_status='Certified') certified,
   SUM(risk_level IN ('High','Critical')) high_risk,
   SUM(validation_outcome='Failed') failed
  FROM vqd_result_validations"
)->fetch();

include __DIR__.'/../../layouts/header.php';
?>
<div class="app-wrapper"><?php include __DIR__.'/../../layouts/sidebar.php'; ?><main class="main-content">

<div class="vqdf-head"><div><div class="vqdf-eyebrow"><i class="bi bi-patch-check"></i> Step 6 · Operational Backend</div><h1>Result Validation & Reporting</h1><p>Validate the complete chain from quorum through decision, calculate an integrity score and risk level, manage findings and corrective actions, certify the result, and release versioned validation reports.</p></div></div>

<div class="vqdf-flow">
<div><strong>1. Certified Decision</strong><small>End-to-end source chain</small></div>
<div><strong>2. Integrity Checks</strong><small>Quorum, ballots, tally, decision</small></div>
<div><strong>3. Findings</strong><small>Risk and corrective action</small></div>
<div><strong>4. Certification</strong><small>Final validated result</small></div>
<div><strong>5. Report Release</strong><small>Versioned validation report</small></div>
</div>

<div class="row g-3 mb-3"><?php foreach([
 ['Validations',$stats['total']??0,'bi-clipboard2-check'],['Certified',$stats['certified']??0,'bi-patch-check'],
 ['High/Critical Risk',$stats['high_risk']??0,'bi-shield-exclamation'],['Failed',$stats['failed']??0,'bi-x-octagon'],
] as [$l,$v,$i]): ?><div class="col-6 col-lg-3"><div class="vqdf-stat"><i class="bi <?= e($i) ?>"></i><div><strong><?= (int)$v ?></strong><small><?= e($l) ?></small></div></div></div><?php endforeach; ?></div>

<?php if(vqdOperationalManager()): ?>
<div class="card vqdf-card mb-3"><div class="card-header">Certified Decisions Ready for Validation</div><div class="table-responsive"><table class="table vqdf-table mb-0"><thead><tr><th>Decision</th><th>Legislative Item</th><th>Outcome</th><th>Status</th><th>Latest Validation</th><th class="text-end">Action</th></tr></thead><tbody>
<?php if(!$decisions): ?><tr><td colspan="6" class="text-center text-muted py-4">No certified decisions are ready.</td></tr><?php endif; ?>
<?php foreach($decisions as $d): ?><tr><td><span class="vqdf-code"><?= e($d['reference_number']) ?></span><div class="small text-muted"><?= e($d['decision_record_number']?:'') ?></div></td><td><?= e($d['legislative_reference']?:'—') ?><div class="small text-muted"><?= e(mb_strimwidth($d['legislative_title']?:'',0,90,'…')) ?></div></td><td><?= e($d['official_outcome']) ?></td><td><?= e($d['record_status']) ?></td><td><?= $d['validation_id']?e($d['validation_reference'].' · '.$d['validation_status']):'None' ?></td><td class="text-end"><button class="btn btn-sm btn-primary create-validation" data-id="<?= (int)$d['id'] ?>" data-ref="<?= e($d['reference_number']) ?>"><i class="bi bi-plus-circle"></i> New Validation</button></td></tr><?php endforeach; ?>
</tbody></table></div></div>
<?php endif; ?>

<div class="card vqdf-card"><div class="card-header">Validation Register</div><div class="table-responsive"><table class="table table-hover vqdf-table mb-0"><thead><tr><th>Validation</th><th>Decision</th><th>Integrity</th><th>Risk</th><th>Outcome</th><th>Findings</th><th>Status</th><th class="text-end">View</th></tr></thead><tbody>
<?php if(!$validations): ?><tr><td colspan="8" class="text-center text-muted py-5">No validation records yet.</td></tr><?php endif; ?>
<?php foreach($validations as $v): ?><tr><td><span class="vqdf-code"><?= e($v['validation_reference']) ?></span></td><td><?= e($v['decision_reference_live']?:$v['decision_reference']?:'—') ?><div class="small text-muted"><?= e($v['legislative_reference']?:'') ?></div></td><td><strong><?= $v['integrity_score']!==null?round((float)$v['integrity_score'],2).'%' :'—' ?></strong></td><td><span class="vqdf-risk <?= e(strtolower($v['risk_level'])) ?>"><?= e($v['risk_level']) ?></span></td><td><?= e($v['validation_outcome']) ?></td><td><?= (int)$v['open_findings'] ?> open · <?= (int)$v['failed_checks'] ?> failed checks</td><td><span class="badge text-bg-light"><?= e($v['validation_status']) ?></span></td><td class="text-end"><a class="btn btn-sm btn-outline-primary" href="view.php?id=<?= (int)$v['id'] ?>"><i class="bi bi-eye"></i></a></td></tr><?php endforeach; ?>
</tbody></table></div></div>
</main></div>

<?php if(vqdOperationalManager()): ?>
<div class="modal fade" id="validationModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered" style="max-width: 580px;"><div class="modal-content shadow-lg border-0" style="border-radius: 14px; overflow: hidden;"><form id="validationForm"><?= csrfField() ?><input type="hidden" name="decision_id" id="v_decision">
<div class="modal-header bg-dark text-white py-2.5 px-3.5"><h6 class="modal-title fw-bold mb-0">Create Result Validation</h6><button class="btn-close btn-close-white" type="button" data-bs-dismiss="modal"></button></div>
<div class="modal-body p-3.5"><div class="vqdf-alert mb-2 py-2 px-3 small" id="validationSource"></div><div class="row g-2">
<div class="col-md-6"><label class="form-label">Review Type</label><select class="form-select" name="review_type"><option>Standard Validation</option><option>Post-Vote Audit</option><option>Challenge Review</option><option>Management Review</option></select></div>
<div class="col-md-6"><label class="form-label">Requesting Office</label><select class="form-select" name="requesting_office_id"><option value="">Unspecified</option><?php foreach($offices as $o): ?><option value="<?= (int)$o['id'] ?>"><?= e($o['name']) ?></option><?php endforeach; ?></select></div>
<div class="col-md-6"><label class="form-label">Lead Validator</label><select class="form-select" name="lead_validator_id"><option value="">Current User</option><?php foreach($users as $u): ?><option value="<?= (int)$u['id'] ?>"><?= e($u['full_name']) ?></option><?php endforeach; ?></select></div>
<div class="col-md-6"><label class="form-label">Release Classification</label><select class="form-select" name="release_classification"><option>Internal</option><option>Public</option><option>Restricted</option></select></div>
<div class="col-12"><label class="form-label">Reason / Scope</label><textarea class="form-control" name="validation_reason" rows="4"></textarea></div>
</div></div><div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Create Validation</button></div>
</form></div></div></div>
<script>
document.addEventListener('DOMContentLoaded',function(){
 const modal=new bootstrap.Modal(document.getElementById('validationModal'));
 document.querySelectorAll('.create-validation').forEach(b=>b.onclick=function(){validationForm.reset();v_decision.value=this.dataset.id;validationSource.textContent='Source decision: '+this.dataset.ref;modal.show();});
 <?php if((int)($_GET['decision_id']??0)>0): ?>const auto=document.querySelector('.create-validation[data-id="<?= (int)$_GET['decision_id'] ?>"]');if(auto)auto.click();<?php endif; ?>
 validationForm.onsubmit=async e=>{e.preventDefault();const r=await fetch('ajax_create.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:new FormData(validationForm)}).then(x=>x.json());if(r.success)location.href='view.php?id='+r.id;else Swal.fire('Validation Error',r.message,'error');};
});
</script>
<?php endif; ?>
<?php include __DIR__.'/../../layouts/footer.php'; ?>
