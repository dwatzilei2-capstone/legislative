<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/vqdss_operational_helpers.php';

requirePermission('vqd.voting.view');

$pageTitle='Voting Management';
$activeMenu='voting';
$extraCss=[appUrl('assets/css/vqdss-operational.css')];
$pdo=db();

$quorums=$pdo->query(
 "SELECT qc.id,qc.reference_number,qc.session_id,qc.legislative_item_id,
         s.reference_number session_reference,s.session_title,s.session_date,
         li.reference_number legislative_reference,li.title legislative_title
  FROM vqd_quorum_checks qc
  JOIN vqd_sessions s ON s.id=qc.session_id
  LEFT JOIN legislative_items li ON li.id=qc.legislative_item_id
  WHERE qc.verification_status='Certified'
    AND qc.quorum_result='Met'
  ORDER BY qc.certified_at DESC,qc.id DESC"
)->fetchAll();

$eq=$pdo->prepare(
 "SELECT ve.*,qc.reference_number quorum_reference,s.reference_number session_reference,
         s.session_title,li.reference_number legislative_reference,li.title legislative_title,
         (SELECT COUNT(*) FROM vqd_voting_event_voters ev WHERE ev.voting_event_id=ve.id AND ev.voter_eligibility='Eligible') eligible_voters,
         (SELECT COUNT(*) FROM vqd_votes v WHERE v.voting_event_id=ve.id) votes_cast,
         (SELECT COUNT(*) FROM vqd_ballot_challenges bc WHERE bc.voting_event_id=ve.id AND bc.status='Open') open_challenges,
         (SELECT COUNT(*) FROM vqd_voting_event_voters ev WHERE ev.voting_event_id=ve.id AND ev.user_id=:curr AND ev.voter_eligibility='Eligible' AND ev.authorization_status<>'Revoked' AND ev.voted_at IS NULL) my_pending_vote
  FROM vqd_voting_events ve
  LEFT JOIN vqd_quorum_checks qc ON qc.id=ve.quorum_check_id
  LEFT JOIN vqd_sessions s ON s.id=ve.session_id
  LEFT JOIN legislative_items li ON li.id=ve.legislative_item_id
  ORDER BY ve.created_at DESC,ve.id DESC"
);
$eq->execute([':curr'=>currentUserId()]);
$events=$eq->fetchAll();

$myPendingBallots=array_filter($events,fn($x)=>$x['status']==='Open'&&!empty($x['my_pending_vote']));

$stats=$pdo->query(
 "SELECT
   COUNT(*) events,
   SUM(status='Open') open_events,
   SUM(status='Closed') closed_events,
   (SELECT COUNT(*) FROM vqd_votes) votes_cast
  FROM vqd_voting_events"
)->fetch();

include __DIR__.'/../../layouts/header.php';
?>
<div class="app-wrapper"><?php include __DIR__.'/../../layouts/sidebar.php'; ?><main class="main-content">

<div class="vqdx-head">
<div><div class="vqdx-eyebrow"><i class="bi bi-check2-square"></i> Step 3 · Operational Backend</div><h1>Voting Management</h1><p>Create voting events only from certified quorum, import the eligible roster, open/pause/close voting, record manual or electronic ballots, enforce one vote per voter, and review challenged ballots.</p></div>
<?php if(vqdOperationalManager()): ?><button class="btn btn-primary" id="btnNewVote"><i class="bi bi-plus-circle"></i> New Voting Event</button><?php endif; ?>
</div>

<?php if($myPendingBallots): ?>
<div class="card border-0 shadow-sm mb-3" style="background: linear-gradient(135deg, #0f2137 0%, #1e3a8a 100%); border-radius: 12px; color: #fff;">
  <div class="card-body p-3.5">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
      <div class="d-flex align-items-center gap-2">
        <span class="spinner-grow spinner-grow-sm text-warning" role="status"></span>
        <h6 class="mb-0 fw-bold text-white"><i class="bi bi-box-arrow-in-right text-warning me-1"></i> Active Ballot(s) Awaiting Your Vote</h6>
      </div>
      <span class="badge bg-warning text-dark fw-bold px-2.5 py-1"><?= count($myPendingBallots) ?> Voting Event(s) Open</span>
    </div>
    <div class="d-flex flex-column gap-2">
      <?php foreach($myPendingBallots as $b): ?>
        <div class="d-flex align-items-center justify-content-between p-2.5 rounded bg-white bg-opacity-10 flex-wrap gap-2">
          <div>
            <div class="d-flex align-items-center gap-2">
              <span class="badge bg-light text-dark fw-semibold"><?= e($b['reference_number']) ?></span>
              <strong class="text-white small"><?= e($b['session_reference']?:'Session') ?> · <?= e($b['session_title']?:'') ?></strong>
            </div>
            <div class="text-white-50 small mt-1"><?= e(mb_strimwidth($b['motion_text'], 0, 110, '…')) ?></div>
          </div>
          <a href="<?= e(appUrl('modules/voting/ballot.php?event_id='.$b['id'])) ?>" class="btn btn-sm btn-warning fw-bold px-3 py-1.5 shadow-sm">
            <i class="bi bi-check2-square me-1"></i> Cast Vote Now
          </a>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="vqdx-flow">
<div><strong>1. Certified Quorum</strong><small>Required before voting</small></div>
<div><strong>2. Motion & Options</strong><small>Configure ballot</small></div>
<div><strong>3. Eligible Voters</strong><small>Imported from quorum</small></div>
<div><strong>4. Open Voting</strong><small>Manual / Electronic / Hybrid</small></div>
<div><strong>5. Close & Tally</strong><small>Lock ballots for computation</small></div>
</div>

<div class="row g-3 mb-3">
<?php foreach([
 ['Voting Events',$stats['events']??0,'bi-ui-checks-grid'],
 ['Currently Open',$stats['open_events']??0,'bi-broadcast'],
 ['Closed',$stats['closed_events']??0,'bi-lock'],
 ['Ballots Cast',$stats['votes_cast']??0,'bi-check2-square'],
] as [$l,$v,$i]): ?><div class="col-6 col-lg-3"><div class="vqdx-stat"><i class="bi <?= e($i) ?>"></i><div><strong><?= (int)$v ?></strong><small><?= e($l) ?></small></div></div></div><?php endforeach; ?>
</div>

<div class="card vqdx-card"><div class="card-header d-flex justify-content-between"><span>Voting Event Register</span><span><?= count($events) ?> event(s)</span></div><div class="table-responsive">
<table class="table table-hover vqdx-table mb-0"><thead><tr><th>Reference / Motion</th><th>Session / Item</th><th>Method</th><th>Voters</th><th>Ballots</th><th>Challenges</th><th>Status</th><th class="text-end">Actions</th></tr></thead><tbody>
<?php if(!$events): ?><tr><td colspan="8" class="text-center text-muted py-5">No voting events have been created yet.</td></tr><?php endif; ?>
<?php foreach($events as $e): ?><tr>
<td><span class="vqdx-code"><?= e($e['reference_number']) ?></span><div><strong><?= e(mb_strimwidth($e['motion_text'],0,110,'…')) ?></strong></div></td>
<td><?= e($e['session_reference']?:'—') ?><div class="small text-muted"><?= e($e['legislative_reference']?:'No legislative item') ?></div></td>
<td><?= e($e['voting_method']) ?><div class="small text-muted"><?= e($e['vote_visibility'].' · '.$e['decision_threshold']) ?></div></td>
<td><?= (int)$e['eligible_voters'] ?></td>
<td><strong><?= (int)$e['votes_cast'] ?></strong></td>
<td><?= (int)$e['open_challenges'] ?></td>
<td><span class="badge text-bg-<?= $e['status']==='Open'?'success':($e['status']==='Closed'?'secondary':($e['status']==='Paused'?'warning':'light')) ?>"><?= e($e['status']) ?></span></td>
<td class="text-end"><div class="btn-group btn-group-sm"><a class="btn btn-outline-primary" href="view.php?id=<?= (int)$e['id'] ?>"><i class="bi bi-eye"></i></a><?php if($e['status']==='Open'&&!empty($e['my_pending_vote'])): ?><a class="btn btn-success fw-semibold" href="ballot.php?event_id=<?= (int)$e['id'] ?>"><i class="bi bi-box-arrow-in-right me-1"></i> Cast Vote</a><?php elseif($e['status']==='Open'): ?><a class="btn btn-outline-success" href="ballot.php?event_id=<?= (int)$e['id'] ?>"><i class="bi bi-box-arrow-in-right"></i></a><?php endif; ?><?php if($e['status']==='Closed'): ?><a class="btn btn-primary" href="<?= e(appUrl('modules/tallying/index.php?voting_event_id='.$e['id'])) ?>"><i class="bi bi-calculator"></i></a><?php endif; ?></div></td>
</tr><?php endforeach; ?>
</tbody></table></div></div>
</main></div>

<?php if(vqdOperationalManager()): ?>
<div class="modal fade" id="voteModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" style="max-width: 680px;"><div class="modal-content shadow-lg border-0" style="border-radius: 14px; overflow: hidden;"><form id="voteForm"><?= csrfField() ?><input type="hidden" name="id" value="0">
<div class="modal-header bg-dark text-white py-2.5 px-3.5"><h6 class="modal-title fw-bold mb-0">Create Voting Event</h6><button class="btn-close btn-close-white" type="button" data-bs-dismiss="modal"></button></div>
<div class="modal-body p-3.5">
<div class="row g-2">
<div class="col-12"><label class="form-label">Certified Quorum *</label><select class="form-select" name="quorum_check_id" required><option value="">Select certified quorum</option><?php foreach($quorums as $q): ?><option value="<?= (int)$q['id'] ?>" <?= (int)($_GET['quorum_check_id']??0)===(int)$q['id']?'selected':'' ?>><?= e($q['reference_number'].' · '.$q['session_reference'].' · '.($q['legislative_reference']?:$q['session_title'])) ?></option><?php endforeach; ?></select></div>
<div class="col-12"><label class="form-label">Motion / Question Put to Vote *</label><textarea class="form-control" name="motion_text" rows="4" required></textarea></div>
<div class="col-12"><label class="form-label">Procedural Basis</label><textarea class="form-control" name="procedural_basis" rows="2"></textarea></div>
<div class="col-md-4"><label class="form-label">Voting Method</label><select class="form-select" name="voting_method"><option>Manual</option><option>Electronic</option><option>Hybrid</option></select></div>
<div class="col-md-4"><label class="form-label">Vote Visibility</label><select class="form-select" name="vote_visibility"><option>Recorded</option><option>Secret</option></select></div>
<div class="col-md-4"><label class="form-label">Decision Threshold</label><select class="form-select" name="decision_threshold"><option>Simple Majority</option><option>Majority of Eligible Members</option><option>Two-Thirds</option><option>Three-Fourths</option><option>Unanimous</option><option>Custom Percentage</option><option>Fixed Count</option></select></div>
<div class="col-md-3"><label class="form-label">Custom %</label><input type="number" class="form-control" step=".01" min="0" max="100" name="required_percentage"></div>
<div class="col-md-3"><label class="form-label">Fixed Required Votes</label><input type="number" class="form-control" min="1" name="fixed_required_votes"></div>
<div class="col-md-3"><label class="form-label">Abstention Rule</label><select class="form-select" name="abstention_rule"><option>Allowed</option><option>Not Allowed</option></select></div>
<div class="col-md-3"><label class="form-label">Tie Rule</label><select class="form-select" name="tie_rule"><option>Presiding Officer Decides</option><option>Motion Fails</option><option>Re-Vote Required</option></select></div>
<div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="reauthentication_required" id="reauth"><label class="form-check-label" for="reauth">Require account password before a voter submits an electronic ballot</label></div></div>
<div class="col-12"><label class="form-label">Ballot Instructions</label><textarea class="form-control" name="ballot_instructions" rows="2"></textarea></div>
</div>
<hr>
<div class="d-flex justify-content-between align-items-center mb-2"><strong>Vote Options</strong><button type="button" class="btn btn-sm btn-outline-primary" id="btnAddOption"><i class="bi bi-plus"></i> Add Option</button></div>
<div id="optionBuilder"></div>
</div>
<div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save Voting Event</button></div>
</form></div></div></div>

<script>
document.addEventListener('DOMContentLoaded',function(){
 const modal=new bootstrap.Modal(document.getElementById('voteModal')), builder=document.getElementById('optionBuilder');
 function add(label='',code='',affirmative=0,abstain=0){
  const d=document.createElement('div');d.className='row g-2 mb-2 option-row';d.innerHTML=`<div class="col-md-4"><input class="form-control" name="option_label[]" placeholder="Option label" value="${label}" required></div><div class="col-md-2"><input class="form-control" name="option_code[]" placeholder="Code" value="${code}"></div><div class="col-md-2"><select class="form-select" name="option_affirmative[]"><option value="0">Not affirmative</option><option value="1" ${affirmative?'selected':''}>Affirmative</option></select></div><div class="col-md-2"><select class="form-select" name="option_abstain[]"><option value="0">Regular</option><option value="1" ${abstain?'selected':''}>Abstention</option></select></div><div class="col-md-2"><button type="button" class="btn btn-outline-danger w-100 remove-option"><i class="bi bi-trash"></i></button></div>`;d.querySelector('.remove-option').onclick=()=>d.remove();builder.appendChild(d);
 }
 btnAddOption.onclick=()=>add();
 btnNewVote.onclick=()=>{voteForm.reset();builder.innerHTML='';add('Yes','YES',1,0);add('No','NO',0,0);add('Abstain','ABSTAIN',0,1);modal.show();};
 <?php if((int)($_GET['quorum_check_id']??0)>0): ?>btnNewVote.click();<?php endif; ?>
 voteForm.onsubmit=async e=>{e.preventDefault();const r=await fetch('ajax_event_save.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:new FormData(voteForm)}).then(x=>x.json());if(r.success)location.href='view.php?id='+r.id;else Swal.fire('Voting Event Error',r.message,'error');};
});
</script>
<?php endif; ?>
<?php include __DIR__.'/../../layouts/footer.php'; ?>
