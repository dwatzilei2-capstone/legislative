<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
requirePermission('vqd.voting.cast');

$pdo=db();$id=(int)($_GET['event_id']??0);

$q=$pdo->prepare(
 "SELECT ve.*,s.session_title,s.reference_number session_reference,
         li.reference_number legislative_reference,li.title legislative_title
  FROM vqd_voting_events ve
  LEFT JOIN vqd_sessions s ON s.id=ve.session_id
  LEFT JOIN legislative_items li ON li.id=ve.legislative_item_id
  WHERE ve.id=:id"
);$q->execute([':id'=>$id]);$event=$q->fetch();
if(!$event){setFlash('warning','Voting event not found.');redirect(appUrl('modules/voting/index.php'));}

$v=$pdo->prepare(
 "SELECT ev.*,u.full_name FROM vqd_voting_event_voters ev
  JOIN users u ON u.id=ev.user_id
  WHERE ev.voting_event_id=:event AND ev.user_id=:user"
);$v->execute([':event'=>$id,':user'=>currentUserId()]);$voter=$v->fetch();

$options=$pdo->prepare('SELECT * FROM vqd_vote_options WHERE voting_event_id=:id AND is_active=1 ORDER BY sequence_number,id');
$options->execute([':id'=>$id]);$optionRows=$options->fetchAll();

$pageTitle='Cast Ballot';$activeMenu='voting';$extraCss=[appUrl('assets/css/vqdss-operational.css')];
include __DIR__.'/../../layouts/header.php';
?>
<div class="app-wrapper"><?php include __DIR__.'/../../layouts/sidebar.php'; ?><main class="main-content">
<div class="vqdx-head"><div><a href="view.php?id=<?= $id ?>" class="small text-decoration-none"><i class="bi bi-arrow-left"></i> Voting Event</a><div class="vqdx-eyebrow mt-2"><?= e($event['reference_number']) ?></div><h1>Electronic Ballot</h1><p><?= e($event['session_reference'].' · '.($event['legislative_reference']?:$event['session_title'])) ?></p></div><span class="badge fs-6 text-bg-<?= $event['status']==='Open'?'success':'secondary' ?>"><?= e($event['status']) ?></span></div>

<div class="row justify-content-center"><div class="col-xl-8">
<?php if(!$voter): ?><div class="alert alert-danger">Your account is not on the eligible voter roster for this voting event.</div>
<?php elseif($event['status']!=='Open'): ?><div class="alert alert-warning">Voting is not currently open.</div>
<?php elseif($voter['voted_at']): ?><div class="alert alert-success"><strong>Your ballot has already been recorded.</strong><br>Recorded <?= formatDateTime($voter['voted_at']) ?>.</div>
<?php else: ?>
<div class="card vqdx-card"><div class="card-header">Ballot for <?= e($voter['full_name']) ?></div><div class="card-body">
<div class="vqdx-alert mb-3"><strong>Motion:</strong><br><?= nl2br(e($event['motion_text'])) ?><?php if($event['vote_visibility']==='Secret'): ?><br><br><strong>Secret Ballot:</strong> the ballot display will not show your selected option after submission.</div><?php else: ?></div><?php endif; ?>
<form id="ballotForm"><?= csrfField() ?><input type="hidden" name="event_id" value="<?= $id ?>"><input type="hidden" name="cast_channel" value="Electronic">
<div class="d-grid gap-2 mb-3"><?php foreach($optionRows as $o): ?><label class="vqdx-ballot-option"><input type="radio" name="option_id" value="<?= (int)$o['id'] ?>" required><strong><?= e($o['option_label']) ?></strong><?php if($o['option_description']): ?><div class="small text-muted"><?= e($o['option_description']) ?></div><?php endif; ?></label><?php endforeach; ?></div>
<?php if($event['reauthentication_required']): ?><div class="mb-3"><label class="form-label">Confirm Account Password</label><input type="password" class="form-control" name="reauth_password" required autocomplete="current-password"></div><?php endif; ?>
<button class="btn btn-primary btn-lg w-100"><i class="bi bi-check2-square"></i> Submit Ballot</button>
</form></div></div>
<?php endif; ?>
</div></div>
</main></div>
<?php if($voter&&$event['status']==='Open'&&!$voter['voted_at']): ?><script>document.addEventListener('DOMContentLoaded',function(){ballotForm.onsubmit=async e=>{e.preventDefault();const ask=await Swal.fire({title:'Submit this ballot?',text:'A submitted vote cannot be changed by the voter.',icon:'question',showCancelButton:true,confirmButtonText:'Submit Vote'});if(!ask.isConfirmed)return;const r=await fetch('ajax_cast_vote.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:new FormData(ballotForm)}).then(x=>x.json());if(r.success){await Swal.fire('Ballot Recorded','Your ballot reference is '+r.ballot_reference,'success');location.reload();}else Swal.fire('Ballot Error',r.message,'error');};});</script><?php endif; ?>
<?php include __DIR__.'/../../layouts/footer.php'; ?>
