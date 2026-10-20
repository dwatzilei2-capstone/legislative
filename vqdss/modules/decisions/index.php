<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/vqdss_final_helpers.php';

requirePermission('vqd.decisions.view');

$pdo=db();$pageTitle='Decision Recording';$activeMenu='decisions';
$extraCss=[appUrl('assets/css/vqdss-final-ops.css')];

$ready=$pdo->query(
 "SELECT t.id tally_id,t.reference_number tally_reference,t.result_outcome,
         t.required_votes,t.affirmative_votes,t.negative_votes,t.abstain_votes,
         ve.reference_number voting_reference,ve.motion_text,ve.session_date,
         li.reference_number legislative_reference,li.title legislative_title,
         d.id decision_id,d.reference_number decision_reference
  FROM vqd_vote_tallies t
  JOIN vqd_voting_events ve ON ve.id=t.voting_event_id
  LEFT JOIN legislative_items li ON li.id=t.legislative_item_id
  LEFT JOIN vqd_decisions d ON d.tally_id=t.id
  WHERE t.tally_status='Certified'
  ORDER BY t.certified_at DESC,t.id DESC"
)->fetchAll();

$decisions=$pdo->query(
 "SELECT d.*,t.reference_number tally_reference,ve.reference_number voting_reference,
         ve.motion_text,li.reference_number legislative_reference,li.title legislative_title,
         (SELECT COUNT(*) FROM vqd_decision_actions a WHERE a.decision_id=d.id) action_count,
         (SELECT COUNT(*) FROM vqd_decision_actions a WHERE a.decision_id=d.id AND a.status NOT IN ('Completed','Cancelled')) open_actions
  FROM vqd_decisions d
  LEFT JOIN vqd_vote_tallies t ON t.id=d.tally_id
  LEFT JOIN vqd_voting_events ve ON ve.id=d.voting_event_id
  LEFT JOIN legislative_items li ON li.id=d.legislative_item_id
  ORDER BY d.created_at DESC,d.id DESC"
)->fetchAll();

$stats=$pdo->query(
 "SELECT COUNT(*) total,
   SUM(record_status='Draft') drafts,
   SUM(record_status='Certified') certified,
   SUM(record_status='Published') published
  FROM vqd_decisions"
)->fetch();

include __DIR__.'/../../layouts/header.php';
?>
<div class="app-wrapper"><?php include __DIR__.'/../../layouts/sidebar.php'; ?><main class="main-content">

<div class="vqdf-head"><div><div class="vqdf-eyebrow"><i class="bi bi-journal-check"></i> Step 5 · Operational Backend</div><h1>Decision Recording</h1><p>Convert a certified vote tally into the official decision record, preserve the exact vote totals and outcome, assign implementation actions, attach supporting records, certify, and publish authorized decisions.</p></div></div>

<div class="vqdf-flow">
<div><strong>1. Certified Tally</strong><small>Source result locked</small></div>
<div><strong>2. Draft Decision</strong><small>Statement and legal basis</small></div>
<div><strong>3. Record & Actions</strong><small>Implementation assignments</small></div>
<div><strong>4. Certification</strong><small>Official decision record</small></div>
<div><strong>5. Publication</strong><small>Public release when authorized</small></div>
</div>

<div class="row g-3 mb-3"><?php foreach([
 ['Decisions',$stats['total']??0,'bi-journal-text'],['Drafts',$stats['drafts']??0,'bi-pencil-square'],
 ['Certified',$stats['certified']??0,'bi-patch-check'],['Published',$stats['published']??0,'bi-megaphone'],
] as [$l,$v,$i]): ?><div class="col-6 col-lg-3"><div class="vqdf-stat"><i class="bi <?= e($i) ?>"></i><div><strong><?= (int)$v ?></strong><small><?= e($l) ?></small></div></div></div><?php endforeach; ?></div>

<?php if(vqdOperationalManager()): ?>
<div class="card vqdf-card mb-3"><div class="card-header">Certified Tallies Ready for Decision Recording</div><div class="table-responsive"><table class="table vqdf-table mb-0"><thead><tr><th>Tally</th><th>Legislative Item</th><th>Motion</th><th>Outcome</th><th>Votes</th><th class="text-end">Decision</th></tr></thead><tbody>
<?php if(!$ready): ?><tr><td colspan="6" class="text-center text-muted py-4">No certified tallies are ready.</td></tr><?php endif; ?>
<?php foreach($ready as $r): ?><tr>
<td><span class="vqdf-code"><?= e($r['tally_reference']) ?></span></td>
<td><?= e($r['legislative_reference']?:'—') ?><div class="small text-muted"><?= e(mb_strimwidth($r['legislative_title']?:'',0,90,'…')) ?></div></td>
<td><?= e(mb_strimwidth($r['motion_text'],0,120,'…')) ?></td>
<td><span class="badge text-bg-<?= $r['result_outcome']==='Passed'?'success':($r['result_outcome']==='Tie'?'warning':'danger') ?>"><?= e($r['result_outcome']) ?></span></td>
<td>Yes <?= (int)$r['affirmative_votes'] ?> · No <?= (int)$r['negative_votes'] ?> · Abstain <?= (int)$r['abstain_votes'] ?></td>
<td class="text-end"><?php if($r['decision_id']): ?><a class="btn btn-sm btn-outline-success" href="view.php?id=<?= (int)$r['decision_id'] ?>"><?= e($r['decision_reference']) ?></a><?php else: ?><button class="btn btn-sm btn-primary btn-create-decision" data-row='<?= e(json_encode($r,JSON_HEX_APOS|JSON_HEX_QUOT)) ?>'><i class="bi bi-plus-circle"></i> Create</button><?php endif; ?></td>
</tr><?php endforeach; ?>
</tbody></table></div></div>
<?php endif; ?>

<div class="card vqdf-card"><div class="card-header">Decision Register</div><div class="table-responsive"><table class="table table-hover vqdf-table mb-0"><thead><tr><th>Decision</th><th>Source</th><th>Outcome</th><th>Release</th><th>Actions</th><th>Status</th><th class="text-end">View</th></tr></thead><tbody>
<?php if(!$decisions): ?><tr><td colspan="7" class="text-center text-muted py-5">No decision records yet.</td></tr><?php endif; ?>
<?php foreach($decisions as $d): ?><tr>
<td><span class="vqdf-code"><?= e($d['reference_number']) ?></span><div><strong><?= e($d['decision_record_number']?:'—') ?></strong></div></td>
<td><?= e($d['tally_reference']?:$d['source_reference']?:'—') ?><div class="small text-muted"><?= e($d['legislative_reference']?:'') ?></div></td>
<td><span class="badge text-bg-<?= $d['official_outcome']==='Passed'?'success':($d['official_outcome']==='Tie'?'warning':'danger') ?>"><?= e($d['official_outcome']) ?></span></td>
<td><?= e($d['release_classification']) ?></td>
<td><?= (int)$d['action_count'] ?> total · <?= (int)$d['open_actions'] ?> open</td>
<td><span class="badge text-bg-light"><?= e($d['record_status']) ?></span></td>
<td class="text-end"><a class="btn btn-sm btn-outline-primary" href="view.php?id=<?= (int)$d['id'] ?>"><i class="bi bi-eye"></i></a></td>
</tr><?php endforeach; ?>
</tbody></table></div></div>

</main></div>

<?php if(vqdOperationalManager()): ?>
<div class="modal fade" id="decisionModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" style="max-width: 680px;"><div class="modal-content shadow-lg border-0" style="border-radius: 14px; overflow: hidden;"><form id="decisionForm"><?= csrfField() ?><input type="hidden" name="id" value="0"><input type="hidden" name="tally_id" id="d_tally">
<div class="modal-header bg-dark text-white py-2.5 px-3.5"><h6 class="modal-title fw-bold mb-0">Create Decision Record</h6><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
<div class="modal-body p-3.5">
<div id="decisionSource" class="vqdf-alert mb-2 py-2 px-3 small"></div>
<div class="row g-2">
<div class="col-md-6"><label class="form-label">Decision Classification</label><input class="form-control" name="decision_classification" value="Motion Result"></div>
<div class="col-md-3"><label class="form-label">Moved By</label><input class="form-control" name="moved_by"></div>
<div class="col-md-3"><label class="form-label">Seconded By</label><input class="form-control" name="seconded_by"></div>
<div class="col-12"><label class="form-label">Official Decision Statement *</label><textarea class="form-control" name="decision_statement" id="d_statement" rows="5" required></textarea></div>
<div class="col-md-6"><label class="form-label">Legal / Procedural Basis</label><textarea class="form-control" name="legal_basis" rows="3"></textarea></div>
<div class="col-md-6"><label class="form-label">Conditions / Qualifications</label><textarea class="form-control" name="conditions" rows="3"></textarea></div>
<div class="col-md-4"><label class="form-label">Effectivity Type</label><select class="form-select" name="effectivity_type"><option>Immediate</option><option>Specified Date</option><option>Upon Publication</option><option>Upon Compliance</option></select></div>
<div class="col-md-4"><label class="form-label">Effectivity Date</label><input type="date" class="form-control" name="effectivity_date"></div>
<div class="col-md-4"><label class="form-label">Release Classification</label><select class="form-select" name="release_classification"><option value="Public" selected>Public</option></select><div class="form-text">Decision records are created for public release.</div></div>
<div class="col-md-6"><label class="form-label">Implementation Notes</label><textarea class="form-control" name="implementation_notes" rows="3"></textarea></div>
<div class="col-md-6"><label class="form-label">Publication Notes</label><textarea class="form-control" name="publication_notes" rows="3"></textarea></div>
<div class="col-12"><label class="form-label">Record Remarks</label><textarea class="form-control" name="record_remarks" rows="2"></textarea></div>
</div></div>
<div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Create Decision</button></div>
</form></div></div></div>
<script>
document.addEventListener('DOMContentLoaded',function(){
 const modal=new bootstrap.Modal(document.getElementById('decisionModal'));
 document.querySelectorAll('.btn-create-decision').forEach(b=>b.onclick=function(){
   const r=JSON.parse(this.dataset.row);decisionForm.reset();d_tally.value=r.tally_id;
   decisionSource.innerHTML='<strong>'+r.tally_reference+'</strong> · '+r.result_outcome+' · Yes '+r.affirmative_votes+' / No '+r.negative_votes+' / Abstain '+r.abstain_votes;
   d_statement.value='The motion was '+(r.result_outcome==='Passed'?'approved':'not approved')+' based on the certified vote tally '+r.tally_reference+'.';
   modal.show();
 });
 decisionForm.onsubmit=async e=>{e.preventDefault();const r=await fetch('ajax_save.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:new FormData(decisionForm)}).then(x=>x.json());if(r.success)location.href='view.php?id='+r.id;else Swal.fire('Decision Error',r.message,'error');};
});
</script>
<?php endif; ?>
<?php include __DIR__.'/../../layouts/footer.php'; ?>
