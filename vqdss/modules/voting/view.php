<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/vqdss_operational_helpers.php';

requirePermission('vqd.voting.view');

$pdo=db();$id=(int)($_GET['id']??0);$isVotingAdmin=isAdmin();
$q=$pdo->prepare(
 "SELECT ve.*,qc.reference_number quorum_reference,qc.quorum_result,qc.verification_status,
         s.reference_number session_reference,s.session_title,
         li.reference_number legislative_reference,li.title legislative_title
  FROM vqd_voting_events ve
  LEFT JOIN vqd_quorum_checks qc ON qc.id=ve.quorum_check_id
  LEFT JOIN vqd_sessions s ON s.id=ve.session_id
  LEFT JOIN legislative_items li ON li.id=ve.legislative_item_id
  WHERE ve.id=:id"
);$q->execute([':id'=>$id]);$event=$q->fetch();
if(!$event){setFlash('warning','Voting event not found.');redirect(appUrl('modules/voting/index.php'));}

$options=$pdo->prepare(
 "SELECT o.*,
   (SELECT COUNT(*) FROM vqd_votes v WHERE v.option_id=o.id AND v.is_valid=1) vote_count
  FROM vqd_vote_options o WHERE o.voting_event_id=:id ORDER BY o.sequence_number,o.id"
);$options->execute([':id'=>$id]);$optionRows=$options->fetchAll();

$voters=$pdo->prepare(
 "SELECT ev.*,u.full_name,u.email,
   v.id vote_id,v.ballot_reference,v.vote_value,v.is_secret,v.is_valid,
   (SELECT COUNT(*) FROM vqd_ballot_challenges bc WHERE bc.vote_id=v.id AND bc.status='Open') open_challenge
  FROM vqd_voting_event_voters ev
  JOIN users u ON u.id=ev.user_id
  LEFT JOIN vqd_votes v ON v.event_voter_id=ev.id AND v.voting_event_id=ev.voting_event_id
  WHERE ev.voting_event_id=:id ORDER BY u.full_name"
);$voters->execute([':id'=>$id]);$voterRows=$voters->fetchAll();

$history=$pdo->prepare(
 "SELECT h.*,u.full_name FROM vqd_voting_history h
  LEFT JOIN users u ON u.id=h.changed_by
  WHERE h.voting_event_id=:id ORDER BY h.created_at DESC,h.id DESC"
);$history->execute([':id'=>$id]);$historyRows=$history->fetchAll();

$docs=$pdo->prepare('SELECT * FROM vqd_voting_documents WHERE voting_event_id=:id ORDER BY uploaded_at DESC,id DESC');
$docs->execute([':id'=>$id]);$documentRows=$docs->fetchAll();

$cast=count(array_filter($voterRows,fn($v)=>!empty($v['voted_at'])));
$eligible=count(array_filter($voterRows,fn($v)=>$v['voter_eligibility']==='Eligible'&&$v['authorization_status']!=='Revoked'));

$pageTitle=$event['reference_number'];$activeMenu='voting';$extraCss=[appUrl('assets/css/vqdss-operational.css')];
include __DIR__.'/../../layouts/header.php';
?>
<div class="app-wrapper"><?php include __DIR__.'/../../layouts/sidebar.php'; ?><main class="main-content">
<div class="vqdx-head"><div><a class="small text-decoration-none" href="index.php"><i class="bi bi-arrow-left"></i> Voting Management</a><div class="vqdx-eyebrow mt-2"><?= e($event['reference_number']) ?></div><h1><?= e(mb_strimwidth($event['motion_text'],0,170,'…')) ?></h1><p><?= e($event['session_reference'].' · '.($event['legislative_reference']?:'No legislative item').' · '.$event['voting_method'].' · '.$event['vote_visibility']) ?></p></div><div class="d-flex gap-2 flex-wrap"><span class="badge fs-6 text-bg-<?= $event['status']==='Open'?'success':($event['status']==='Paused'?'warning':'secondary') ?>"><?= e($event['status']) ?></span><?php if($event['status']==='Open'): ?><a class="btn btn-success btn-sm" href="ballot.php?event_id=<?= $id ?>"><i class="bi bi-box-arrow-in-right"></i> My Ballot</a><?php endif; ?><a class="btn btn-outline-secondary btn-sm" href="print.php?id=<?= $id ?>" target="_blank"><i class="bi bi-printer"></i></a></div></div>

<div class="row g-3 mb-3">
<?php foreach([
 ['Eligible Voters',$eligible,'bi-people'],
 ['Votes Cast',$cast,'bi-check2-square'],
 ['Remaining',max(0,$eligible-$cast),'bi-hourglass'],
 ['Open Challenges',array_sum(array_map(fn($v)=>(int)$v['open_challenge'],$voterRows)),'bi-exclamation-triangle'],
] as [$l,$v,$i]): ?><div class="col-6 col-lg-3"><div class="vqdx-stat"><i class="bi <?= e($i) ?>"></i><div><strong><?= $v ?></strong><small><?= e($l) ?></small></div></div></div><?php endforeach; ?>
</div>

<div class="row g-3">
<div class="col-xl-8">
<div class="card vqdx-card mb-3"><div class="card-header d-flex justify-content-between align-items-center"><span>Motion & Voting Rules</span><button type="button" class="btn btn-sm btn-outline-warning py-0 px-2" id="btnAiAdviseThreshold" style="font-size:0.8rem;"><i class="bi bi-stars"></i> AI Threshold Advisor</button></div><div class="card-body"><div class="vqdx-grid"><div><small>Motion</small><strong><?= e($event['motion_text']) ?></strong></div><div><small>Certified Quorum</small><strong><?= e($event['quorum_reference'].' · '.$event['quorum_result']) ?></strong></div><div><small>Method / Visibility</small><strong><?= e($event['voting_method'].' / '.$event['vote_visibility']) ?></strong></div><div><small>Decision Threshold</small><strong><?= e($event['decision_threshold']) ?></strong></div><div><small>Abstention</small><strong><?= e($event['abstention_rule']) ?></strong></div><div><small>Tie Rule</small><strong><?= e($event['tie_rule']) ?></strong></div></div><?php if($event['ballot_instructions']): ?><div class="vqdx-alert mt-3"><?= nl2br(e($event['ballot_instructions'])) ?></div><?php endif; ?></div></div>

<div class="card vqdx-card mb-3"><div class="card-header">Vote Options</div><div class="card-body d-grid gap-2"><?php foreach($optionRows as $o): ?><div class="vqdx-option"><div><strong><?= e($o['option_label']) ?></strong><span class="ms-2"><?= e($o['option_category']) ?></span></div><span><?= $event['status']==='Closed'?(int)$o['vote_count'].' vote(s)':'Count hidden until close/tally' ?></span></div><?php endforeach; ?></div></div>

<?php if($isVotingAdmin): ?><div class="card vqdx-card"><div class="card-header">Eligible Voter Roster / Manual Ballot Station</div><div class="table-responsive"><table class="table vqdx-table mb-0"><thead><tr><th>Voter</th><th>Eligibility</th><th>Ballot</th><th>Channel</th><th>Challenge</th><th class="text-end">Manual Vote</th></tr></thead><tbody>
<?php foreach($voterRows as $v): ?><tr>
<td><strong><?= e($v['full_name']) ?></strong><div class="small text-muted"><?= e($v['email']) ?></div></td>
<td><?= e($v['voter_eligibility']) ?></td>
<td><?php if($v['voted_at']): ?><span class="badge text-bg-success">Cast</span><div class="small text-muted"><?= e($v['ballot_reference']) ?><?= $event['vote_visibility']==='Recorded'?' · '.e($v['vote_value']):' · Secret' ?></div><?php else: ?><span class="text-muted">Not cast</span><?php endif; ?></td>
<td><?= e($v['voting_channel']) ?></td>
<td><?= (int)$v['open_challenge']>0?'<span class="badge text-bg-warning">Open</span>':'—' ?></td>
<td class="text-end"><?php if($event['status']==='Open'&&!$v['voted_at']): ?><button class="btn btn-sm btn-outline-primary manual-vote" data-voter="<?= (int)$v['id'] ?>" data-name="<?= e($v['full_name']) ?>">Record Vote</button><?php elseif($v['vote_id']&&$event['status']==='Closed'): ?><button class="btn btn-sm btn-outline-warning challenge-vote" data-vote="<?= (int)$v['vote_id'] ?>">Challenge</button><?php endif; ?></td>
</tr><?php endforeach; ?>
</tbody></table></div></div><?php endif; ?>
</div>

<div class="col-xl-4">
<?php if($isVotingAdmin): ?><div class="card vqdx-card mb-3"><div class="card-header">Voting Controls</div><div class="card-body"><form id="controlForm"><?= csrfField() ?><input type="hidden" name="id" value="<?= $id ?>"><textarea class="form-control form-control-sm mb-2" name="reason" rows="2" placeholder="Reason required for cancellation"></textarea><div class="d-grid gap-2">
<?php if(in_array($event['status'],['Ready','Paused'],true)): ?><button type="button" class="btn btn-success control" data-action="open"><i class="bi bi-play"></i> <?= $event['status']==='Paused'?'Resume':'Open Voting' ?></button><?php endif; ?>
<?php if($event['status']==='Open'): ?><button type="button" class="btn btn-warning control" data-action="pause"><i class="bi bi-pause"></i> Pause Voting</button><?php endif; ?>
<?php if(in_array($event['status'],['Open','Paused'],true)): ?><button type="button" class="btn btn-primary control" data-action="close"><i class="bi bi-lock"></i> Close & Lock Voting</button><?php endif; ?>
<?php if(in_array($event['status'],['Ready','Open','Paused'],true)): ?><button type="button" class="btn btn-outline-danger control" data-action="cancel"><i class="bi bi-x-circle"></i> Cancel Event</button><?php endif; ?>
<?php if($event['status']==='Closed'): ?><a class="btn btn-primary" href="<?= e(appUrl('modules/tallying/index.php?voting_event_id='.$id)) ?>"><i class="bi bi-calculator"></i> Continue to Vote Tallying</a><?php endif; ?>
</div></form></div></div><?php endif; ?>

<?php if(vqdOperationalManager()): ?><div class="card vqdx-card mb-3"><div class="card-header">Voting Documents</div><div class="card-body"><form id="votingDocForm" class="row g-2 align-items-end" enctype="multipart/form-data"><?= csrfField() ?><input type="hidden" name="voting_event_id" value="<?= $id ?>"><div class="col-12"><div class="list-group mb-3"><?php if(!$documentRows): ?><div class="list-group-item text-muted">No voting documents uploaded.</div><?php endif; ?><?php foreach($documentRows as $d): ?><a class="list-group-item list-group-item-action" target="_blank" href="<?= e(appUrl('assets/uploads/'.$d['file_path'])) ?>"><strong><?= e($d['file_name']) ?></strong><div class="small text-muted"><?= e($d['document_type'].' · '.$d['visibility']) ?> · <?= formatDateTime($d['uploaded_at']) ?></div></a><?php endforeach; ?></div></div><div class="col-12"><input type="file" class="form-control form-control-sm" name="document" required></div><div class="col-7"><input class="form-control form-control-sm" name="document_type" value="Voting Document"></div><div class="col-5"><select class="form-select form-select-sm" name="visibility"><option>Internal</option><option>Public</option><option>Restricted</option></select></div><div class="col-12"><button class="btn btn-outline-primary btn-sm w-100"><i class="bi bi-upload"></i> Upload Document</button></div></form></div></div><?php endif; ?>

<div class="card vqdx-card"><div class="card-header">Voting Audit History</div><div class="card-body vqdx-history"><?php foreach($historyRows as $h): ?><div><strong><?= e($h['action']) ?> · <?= e($h['full_name']?:'System') ?></strong><small><?= e($h['details']?:'') ?><br><?= formatDateTime($h['created_at']) ?></small></div><?php endforeach; ?></div></div>
</div>
</div>
</main></div>

<!-- AI Threshold Advisor Modal -->
<div class="modal fade" id="aiThresholdModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered" style="max-width: 580px;"><div class="modal-content shadow">
<div class="modal-header bg-dark text-white py-2 px-3"><h6 class="modal-title mb-0"><i class="bi bi-robot text-warning me-2"></i>Ollama AI Parliamentary Threshold Advisor</h6><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
<div class="modal-body" id="aiThresholdModalBody">
  <div class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-1"></div> Consulting Ollama on RA 7160 parliamentary requirements...</div>
</div>
<div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Close</button></div>
</div></div></div>

<?php if($isVotingAdmin): ?>
<div class="modal fade" id="manualVoteModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered" style="max-width: 480px;"><div class="modal-content border-0 shadow-lg rounded-3"><form id="manualVoteForm"><?= csrfField() ?><input type="hidden" name="event_id" value="<?= $id ?>"><input type="hidden" name="event_voter_id" id="manualVoter"><input type="hidden" name="cast_channel" value="Manual"><div class="modal-header py-2 px-3 text-white" style="background: linear-gradient(135deg, #0f172a, #1e293b);"><div class="d-flex align-items-center gap-2"><div class="d-inline-flex align-items-center justify-content-center bg-white bg-opacity-10 text-white rounded-circle" style="width: 28px; height: 28px;"><i class="bi bi-box-seam fs-6"></i></div><h6 class="modal-title fw-bold mb-0 text-white">Record Manual Ballot</h6></div><button type="button" class="btn-close btn-close-white btn-sm" data-bs-dismiss="modal"></button></div><div class="modal-body p-3"><p id="manualVoterName" class="fw-semibold small text-primary mb-2"></p><label class="form-label text-uppercase fw-semibold text-muted mb-1" style="font-size: 0.72rem; letter-spacing: 0.04em;">Select Vote *</label><select class="form-select form-select-sm" name="option_id" required><option value="">Select vote</option><?php foreach($optionRows as $o): ?><option value="<?= (int)$o['id'] ?>"><?= e($o['option_label']) ?></option><?php endforeach; ?></select></div><div class="modal-footer py-2 px-3 bg-light border-top"><button class="btn btn-sm btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancel</button><button class="btn btn-sm btn-primary px-3 fw-semibold">Record Ballot</button></div></form></div></div></div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded',function(){
 document.querySelectorAll('.control').forEach(b=>b.onclick=async function(){const fd=new FormData(controlForm);fd.append('action',this.dataset.action);const r=await fetch('ajax_transition.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:fd}).then(x=>x.json());if(r.success){await Swal.fire('Voting Control',r.message,'success');location.reload();}else Swal.fire('Voting Control Error',r.message,'error');});
 <?php if($isVotingAdmin): ?>
 const mmodal=new bootstrap.Modal(document.getElementById('manualVoteModal'));
 document.querySelectorAll('.manual-vote').forEach(b=>b.onclick=function(){manualVoter.value=this.dataset.voter;manualVoterName.textContent='Voter: '+this.dataset.name;manualVoteForm.reset();manualVoter.value=this.dataset.voter;mmodal.show();});
 manualVoteForm.onsubmit=async e=>{e.preventDefault();const r=await fetch('ajax_cast_vote.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:new FormData(manualVoteForm)}).then(x=>x.json());if(r.success){await Swal.fire('Vote Recorded','Ballot reference: '+r.ballot_reference,'success');location.reload();}else Swal.fire('Ballot Error',r.message,'error');};
 document.querySelectorAll('.challenge-vote').forEach(b=>b.onclick=async function(){const p=await Swal.fire({title:'Challenge ballot',input:'textarea',inputLabel:'Reason',showCancelButton:true,inputValidator:v=>!v?'Reason is required':undefined});if(!p.isConfirmed)return;const fd=new FormData();fd.append('csrf_token','<?= e(csrfToken()) ?>');fd.append('vote_id',this.dataset.vote);fd.append('action','raise');fd.append('reason',p.value);const r=await fetch('ajax_ballot_challenge.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:fd}).then(x=>x.json());if(r.success)location.reload();else Swal.fire('Challenge Error',r.message,'error');});
 const vdf=document.getElementById('votingDocForm');if(vdf)vdf.onsubmit=async e=>{e.preventDefault();const r=await fetch('ajax_upload_document.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:new FormData(vdf)}).then(x=>x.json());if(r.success)location.reload();else Swal.fire('Upload Error',r.message,'error');};
 <?php endif; ?>

 // Ollama AI Threshold Advisor
 const btnAdvise = document.getElementById('btnAiAdviseThreshold');
 const threshModalEl = document.getElementById('aiThresholdModal');
 if (btnAdvise && threshModalEl) {
   const tmodal = new bootstrap.Modal(threshModalEl);
   btnAdvise.addEventListener('click', async () => {
     btnAdvise.disabled = true;
     const origHtml = btnAdvise.innerHTML;
     btnAdvise.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Consulting Ollama...';
     document.getElementById('aiThresholdModalBody').innerHTML = '<div class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-1"></div> Consulting Ollama on RA 7160 parliamentary rules...</div>';
     tmodal.show();

     try {
       const fd = new FormData();
       fd.append('csrf_token', '<?= e(csrfToken()) ?>');
       fd.append('event_id', '<?= $id ?>');
       fd.append('action', 'advise_threshold');

       const res = await fetch('ajax_ai_voting.php', {
         method: 'POST',
         headers: {'X-Requested-With': 'XMLHttpRequest'},
         body: fd
       }).then(x => x.json());

       if (!res.success) {
         document.getElementById('aiThresholdModalBody').innerHTML = `<div class="alert alert-danger">${res.message || 'Advisory service error.'}</div>`;
         return;
       }

       const d = res.data || {};
       document.getElementById('aiThresholdModalBody').innerHTML = `
         <div class="mb-3">
           <small class="text-muted d-block">Recommended Statutory Threshold:</small>
           <h4 class="text-primary mt-1 mb-2"><i class="bi bi-shield-check me-2"></i>${escapeHtml(d.recommended_threshold || 'Simple Majority')}</h4>
           <div class="alert alert-light border py-2 px-3 small">${escapeHtml(d.statutory_basis || '')}</div>
         </div>
         <div class="row g-2 mb-3">
           <div class="col-md-6">
             <div class="border rounded p-2 bg-light">
               <small class="text-muted d-block">Recommended Method:</small>
               <strong>${escapeHtml(d.recommended_method || 'Roll Call')}</strong>
             </div>
           </div>
           <div class="col-md-6">
             <div class="border rounded p-2 bg-light">
               <small class="text-muted d-block">Recommended Tie Rule:</small>
               <strong>${escapeHtml(d.tie_rule_recommendation || 'Presiding Officer Decides')}</strong>
             </div>
           </div>
         </div>
         ${d.parliamentary_guidance ? `
           <div class="small border-start border-warning border-4 p-2 bg-light">
             <strong>Parliamentary Guidance:</strong> ${escapeHtml(d.parliamentary_guidance)}
           </div>
         ` : ''}
       `;
     } catch (err) {
       document.getElementById('aiThresholdModalBody').innerHTML = `<div class="alert alert-danger">Error: ${err.message}</div>`;
     } finally {
       btnAdvise.disabled = false;
       btnAdvise.innerHTML = origHtml;
     }
   });
 }

 function escapeHtml(str) {
   return String(str || '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[m]);
 }
});
</script>
<?php include __DIR__.'/../../layouts/footer.php'; ?>
