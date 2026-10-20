<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/vqdss_operational_helpers.php';

requirePermission('vqd.tally.view');

$pdo=db();$id=(int)($_GET['id']??0);
$q=$pdo->prepare(
 "SELECT t.*,ve.motion_text,ve.reference_number voting_reference,ve.tie_rule,
         s.reference_number session_reference,li.reference_number legislative_reference,
         u.full_name tally_officer_name,r.full_name reviewer_name,c.full_name certified_name
  FROM vqd_vote_tallies t
  JOIN vqd_voting_events ve ON ve.id=t.voting_event_id
  LEFT JOIN vqd_sessions s ON s.id=ve.session_id
  LEFT JOIN legislative_items li ON li.id=t.legislative_item_id
  LEFT JOIN users u ON u.id=t.tally_officer_id
  LEFT JOIN users r ON r.id=t.reviewer_id
  LEFT JOIN users c ON c.id=t.certified_by
  WHERE t.id=:id"
);$q->execute([':id'=>$id]);$t=$q->fetch();
if(!$t){setFlash('warning','Tally not found.');redirect(appUrl('modules/tallying/index.php'));}

$options=$pdo->prepare('SELECT * FROM vqd_vote_tally_options WHERE tally_id=:id ORDER BY sequence_number,id');
$options->execute([':id'=>$id]);$optionRows=$options->fetchAll();

$recounts=$pdo->prepare("SELECT r.*,u.full_name FROM vqd_tally_recounts r LEFT JOIN users u ON u.id=r.performed_by WHERE r.tally_id=:id ORDER BY r.recount_number DESC");
$recounts->execute([':id'=>$id]);$recountRows=$recounts->fetchAll();

$history=$pdo->prepare("SELECT h.*,u.full_name FROM vqd_tally_history h LEFT JOIN users u ON u.id=h.changed_by WHERE h.tally_id=:id ORDER BY h.created_at DESC,h.id DESC");
$history->execute([':id'=>$id]);$historyRows=$history->fetchAll();

$docs=$pdo->prepare('SELECT * FROM vqd_tally_documents WHERE tally_id=:id ORDER BY uploaded_at DESC,id DESC');
$docs->execute([':id'=>$id]);$documentRows=$docs->fetchAll();

$challenges=$pdo->prepare(
 "SELECT bc.*,v.ballot_reference,v.vote_value,v.is_secret,v.is_valid
  FROM vqd_ballot_challenges bc JOIN vqd_votes v ON v.id=bc.vote_id
  WHERE bc.voting_event_id=:event ORDER BY bc.raised_at DESC"
);$challenges->execute([':event'=>$t['voting_event_id']]);$challengeRows=$challenges->fetchAll();

$pageTitle=$t['reference_number'];$activeMenu='tallying';$extraCss=[appUrl('assets/css/vqdss-operational.css')];
include __DIR__.'/../../layouts/header.php';
?>
<div class="app-wrapper"><?php include __DIR__.'/../../layouts/sidebar.php'; ?><main class="main-content">
<div class="vqdx-head"><div><a class="small text-decoration-none" href="index.php"><i class="bi bi-arrow-left"></i> Vote Tallying</a><div class="vqdx-eyebrow mt-2"><?= e($t['reference_number']) ?></div><h1><?= e(mb_strimwidth($t['motion_text'],0,170,'…')) ?></h1><p><?= e($t['voting_reference'].' · '.($t['legislative_reference']?:'No legislative item')) ?></p></div><div class="d-flex gap-2 flex-wrap"><span class="badge fs-6 text-bg-<?= $t['result_outcome']==='Passed'?'success':($t['result_outcome']==='Tie'?'warning':'danger') ?>"><?= e($t['result_outcome']) ?></span><span class="badge fs-6 text-bg-light"><?= e($t['tally_status']) ?></span><a class="btn btn-outline-secondary btn-sm" href="print.php?id=<?= $id ?>" target="_blank"><i class="bi bi-printer"></i></a></div></div>

<div class="row g-3 mb-3"><?php foreach([
 ['Eligible',$t['eligible_voters'],'bi-people'],['Votes Cast',$t['votes_cast'],'bi-check2-square'],
 ['Valid',$t['valid_votes'],'bi-check-circle'],['Invalid',$t['invalid_ballots'],'bi-x-circle'],
 ['Challenged',$t['challenged_ballots'],'bi-exclamation-triangle'],['Missing',$t['missing_votes'],'bi-hourglass'],
] as [$l,$v,$i]): ?><div class="col-6 col-lg-2"><div class="vqdx-stat"><i class="bi <?= e($i) ?>"></i><div><strong><?= (int)$v ?></strong><small><?= e($l) ?></small></div></div></div><?php endforeach; ?></div>

<div class="row g-3">
<div class="col-xl-8">
<div class="card vqdx-card mb-3"><div class="card-header">Certified Result Computation</div><div class="card-body">
<div class="vqdx-grid mb-3"><div><small>Threshold</small><strong><?= e($t['decision_threshold']) ?></strong></div><div><small>Required Votes</small><strong><?= (int)$t['required_votes'] ?></strong></div><div><small>Affirmative</small><strong><?= (int)$t['affirmative_votes'] ?></strong></div><div><small>Negative</small><strong><?= (int)$t['negative_votes'] ?></strong></div><div><small>Abstain</small><strong><?= (int)$t['abstain_votes'] ?></strong></div><div><small>Decision Handoff</small><strong><?= e($t['decision_handoff']) ?></strong></div></div>
<div class="alert alert-<?= $t['result_outcome']==='Passed'?'success':($t['result_outcome']==='Tie'?'warning':'danger') ?> mb-0"><?= e($t['result_statement']) ?></div>
</div></div>

<div class="card vqdx-card mb-3"><div class="card-header">Option Tally</div><div class="card-body d-grid gap-3"><?php foreach($optionRows as $o): ?><div><div class="d-flex justify-content-between small mb-1"><strong><?= e($o['option_label']) ?></strong><span><?= (int)$o['vote_count'] ?> vote(s) · <?= round((float)$o['percentage'],2) ?>%</span></div><div class="vqdx-tally-bar"><span style="width:<?= min(100,(float)$o['percentage']) ?>%"></span></div></div><?php endforeach; ?></div></div>

<div class="card vqdx-card"><div class="card-header">Ballot Challenges</div><div class="table-responsive"><table class="table vqdx-table mb-0"><thead><tr><th>Ballot</th><th>Reason</th><th>Status</th><th>Raised</th><th class="text-end">Resolution</th></tr></thead><tbody>
<?php if(!$challengeRows): ?><tr><td colspan="5" class="text-center text-muted py-4">No ballot challenges.</td></tr><?php endif; ?>
<?php foreach($challengeRows as $c): ?><tr><td><span class="vqdx-code"><?= e($c['ballot_reference']) ?></span><div class="small text-muted"><?= $c['is_secret']?'Secret ballot':e($c['vote_value']) ?></div></td><td><?= e($c['reason']) ?></td><td><?= e($c['status']) ?></td><td><?= formatDateTime($c['raised_at']) ?></td><td class="text-end"><?php if(vqdOperationalManager()&&$c['status']==='Open'): ?><div class="btn-group btn-group-sm"><button class="btn btn-outline-success challenge-resolve" data-vote="<?= (int)$c['vote_id'] ?>" data-action="validate">Valid</button><button class="btn btn-outline-danger challenge-resolve" data-vote="<?= (int)$c['vote_id'] ?>" data-action="invalidate">Invalid</button></div><?php else: ?><?= e($c['resolution']?:'—') ?><?php endif; ?></td></tr><?php endforeach; ?>
</tbody></table></div></div>
</div>

<div class="col-xl-4">
<div class="card vqdx-card mb-3"><div class="card-header">Integrity Review</div><div class="card-body"><div class="mb-2"><small class="text-muted">Classification</small><div><span class="badge text-bg-<?= $t['discrepancy_classification']==='None'?'success':($t['discrepancy_classification']==='Critical'?'danger':'warning') ?>"><?= e($t['discrepancy_classification']) ?></span></div></div><div class="vqdx-alert"><?= e($t['audit_findings']?:'No findings recorded.') ?></div></div></div>

<?php if(vqdOperationalManager()): ?>
<div class="card vqdx-card mb-3"><div class="card-header">Tally Workflow</div><div class="card-body"><form id="workflowForm"><?= csrfField() ?><input type="hidden" name="id" value="<?= $id ?>"><textarea class="form-control form-control-sm mb-2" name="notes" rows="2" placeholder="Review/certification notes"></textarea><div class="d-grid gap-2"><?php if(in_array($t['tally_status'],['Computed','Recounted'],true)): ?><button type="button" class="btn btn-outline-primary tally-transition" data-action="review">Move to Review</button><?php endif; ?><?php if($t['tally_status']!=='Certified'): ?><button type="button" class="btn btn-success tally-transition" data-action="certify"><i class="bi bi-patch-check"></i> Certify Result</button><?php endif; ?><?php if($t['tally_status']==='Certified'): ?><a class="btn btn-primary" href="<?= e(appUrl('modules/decisions/index.php?tally_id='.$id)) ?>"><i class="bi bi-box-arrow-right"></i> Continue to Decision Recording</a><?php endif; ?></div></form></div></div>

<?php if($t['tally_status']!=='Certified'): ?><div class="card vqdx-card mb-3"><div class="card-header d-flex justify-content-between align-items-center"><span>Controlled Recount</span><button type="button" class="btn btn-sm btn-outline-warning py-0 px-2" id="btnAiDraftRecount" style="font-size:0.75rem;"><i class="bi bi-stars"></i> AI Draft Reason</button></div><div class="card-body"><form id="recountForm"><?= csrfField() ?><input type="hidden" name="tally_id" value="<?= $id ?>"><select class="form-select form-select-sm mb-2" name="recount_method"><option>System Recount</option><option>Manual Verification</option><option>Hybrid Recount</option></select><textarea class="form-control form-control-sm mb-2" name="reason" rows="3" placeholder="Reason for recount" required></textarea><button class="btn btn-outline-warning w-100"><i class="bi bi-arrow-repeat"></i> Perform Recount</button></form></div></div><?php endif; ?>
<?php endif; ?>

<?php if(vqdOperationalManager()): ?><div class="card vqdx-card mb-3"><div class="card-header">Tally Evidence</div><div class="card-body"><div class="list-group mb-3"><?php if(!$documentRows): ?><div class="list-group-item text-muted">No tally evidence uploaded.</div><?php endif; ?><?php foreach($documentRows as $d): ?><a class="list-group-item list-group-item-action" target="_blank" href="<?= e(appUrl('assets/uploads/'.$d['file_path'])) ?>"><strong><?= e($d['file_name']) ?></strong><div class="small text-muted"><?= e($d['document_type'].' · '.$d['visibility']) ?> · <?= formatDateTime($d['uploaded_at']) ?></div></a><?php endforeach; ?></div><form id="tallyDocForm" class="row g-2" enctype="multipart/form-data"><?= csrfField() ?><input type="hidden" name="tally_id" value="<?= $id ?>"><div class="col-12"><input type="file" class="form-control form-control-sm" name="document" required></div><div class="col-7"><input class="form-control form-control-sm" name="document_type" value="Tally Evidence"></div><div class="col-5"><select class="form-select form-select-sm" name="visibility"><option>Internal</option><option>Public</option><option>Restricted</option></select></div><div class="col-12"><button class="btn btn-outline-primary btn-sm w-100"><i class="bi bi-upload"></i> Upload Evidence</button></div></form></div></div><?php endif; ?>

<div class="card vqdx-card mb-3"><div class="card-header">Recount History</div><div class="card-body vqdx-history"><?php if(!$recountRows): ?><div class="text-muted small">No recount performed.</div><?php endif; ?><?php foreach($recountRows as $r): ?><div><strong>Recount #<?= (int)$r['recount_number'] ?> · <?= e($r['recount_method']) ?></strong><small><?= e($r['difference_summary']?:$r['reason']) ?><br><?= formatDateTime($r['performed_at']) ?> · <?= e($r['full_name']?:'System') ?></small></div><?php endforeach; ?></div></div>

<div class="card vqdx-card"><div class="card-header">Tally Audit History</div><div class="card-body vqdx-history"><?php foreach($historyRows as $h): ?><div><strong><?= e($h['action']) ?> · <?= e($h['full_name']?:'System') ?></strong><small><?= e($h['details']?:'') ?><br><?= formatDateTime($h['created_at']) ?></small></div><?php endforeach; ?></div></div>
</div>
</div>
</main></div>

<script>
document.addEventListener('DOMContentLoaded',function(){
 <?php if(vqdOperationalManager()): ?>
 document.querySelectorAll('.tally-transition').forEach(b=>b.onclick=async function(){const fd=new FormData(workflowForm);fd.append('action',this.dataset.action);const r=await fetch('ajax_transition.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:fd}).then(x=>x.json());if(r.success){await Swal.fire('Tally Updated',r.message,'success');location.reload();}else Swal.fire('Tally Error',r.message,'error');});
 const rf=document.getElementById('recountForm');if(rf)rf.onsubmit=async e=>{e.preventDefault();const ask=await Swal.fire({title:'Perform recount?',text:'The current tally will be recomputed from the locked ballot records.',showCancelButton:true});if(!ask.isConfirmed)return;const r=await fetch('ajax_recount.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:new FormData(rf)}).then(x=>x.json());if(r.success)location.reload();else Swal.fire('Recount Error',r.message,'error');};
 document.querySelectorAll('.challenge-resolve').forEach(b=>b.onclick=async function(){const p=await Swal.fire({title:this.dataset.action==='invalidate'?'Invalidate challenged ballot?':'Validate challenged ballot?',input:'textarea',inputLabel:'Resolution',showCancelButton:true,inputValidator:v=>!v?'Resolution is required':undefined});if(!p.isConfirmed)return;const fd=new FormData();fd.append('csrf_token','<?= e(csrfToken()) ?>');fd.append('vote_id',this.dataset.vote);fd.append('action',this.dataset.action);fd.append('resolution',p.value);const r=await fetch('../voting/ajax_ballot_challenge.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:fd}).then(x=>x.json());if(r.success){const rec=new FormData();rec.append('csrf_token','<?= e(csrfToken()) ?>');rec.append('tally_id','<?= $id ?>');rec.append('recount_method','Challenge Resolution Recompute');rec.append('reason','Recomputed after resolving a challenged ballot.');await fetch('ajax_recount.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:rec});location.reload();}else Swal.fire('Challenge Error',r.message,'error');});
 const tdf=document.getElementById('tallyDocForm');if(tdf)tdf.onsubmit=async e=>{e.preventDefault();const r=await fetch('ajax_upload_document.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:new FormData(tdf)}).then(x=>x.json());if(r.success)location.reload();else Swal.fire('Upload Error',r.message,'error');};

 // AI Recount Reason Drafter
 const btnRec = document.getElementById('btnAiDraftRecount');
 if (btnRec) {
   btnRec.addEventListener('click', async () => {
     btnRec.disabled = true;
     const origHtml = btnRec.innerHTML;
     btnRec.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Drafting...';
     try {
       const fd = new FormData();
       fd.append('csrf_token', '<?= e(csrfToken()) ?>');
       fd.append('tally_id', '<?= $id ?>');
       fd.append('action', 'draft_recount');
       const res = await fetch('ajax_ai_tally.php', {
         method: 'POST',
         headers: {'X-Requested-With': 'XMLHttpRequest'},
         body: fd
       }).then(x => x.json());

       if (!res.success) {
         Swal.fire('AI Recount Drafter', res.message || 'Failed to generate justification.', 'error');
         return;
       }

       const reasonArea = document.querySelector('#recountForm textarea[name="reason"]');
       if (reasonArea && res.data && res.data.recount_reason) {
         reasonArea.value = res.data.recount_reason;
         Swal.fire({toast: true, position: 'top-end', icon: 'success', title: 'Recount Justification Drafted', timer: 1500, showConfirmButton: false});
       }
     } catch (err) {
       Swal.fire('AI Error', err.message, 'error');
     } finally {
       btnRec.disabled = false;
       btnRec.innerHTML = origHtml;
     }
   });
 }
 <?php endif; ?>
});
</script>
<?php include __DIR__.'/../../layouts/footer.php'; ?>
