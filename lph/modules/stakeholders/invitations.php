<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/lph_module_helpers.php';
requireLogin();
if (!canManage()) redirect(APP_URL.'/dashboard.php');

$pdo=db();$pageTitle='Hearing Invitations';$activeMenu='stakeholders';
$hearings=$pdo->query("SELECT id,reference_number,title,hearing_date,status FROM hearings WHERE status IN ('Upcoming','Ongoing') ORDER BY hearing_date")->fetchAll();
$filterHearings=$pdo->query(
 "SELECT DISTINCT h.id,h.reference_number,h.title,h.hearing_date
  FROM hearings h JOIN invitations i ON i.hearing_id=h.id
  ORDER BY h.hearing_date DESC,h.title"
)->fetchAll();
$hearingFilter=filter_input(INPUT_GET,'hearing_id',FILTER_VALIDATE_INT);
$hearingFilter=($hearingFilter!==false && $hearingFilter!==null && $hearingFilter>0)?$hearingFilter:0;
$stakeholders=$pdo->query("SELECT id,full_name,email,organization,status FROM stakeholders WHERE status<>'Inactive' ORDER BY full_name")->fetchAll();
$invitationSql=
 "SELECT i.*,s.full_name,s.email,s.organization,h.title hearing_title,h.reference_number,h.hearing_date
  FROM invitations i JOIN stakeholders s ON s.id=i.stakeholder_id
  LEFT JOIN hearings h ON h.id=i.hearing_id";
if($hearingFilter>0)$invitationSql.=" WHERE i.hearing_id=:hearing_id";
$invitationSql.=" ORDER BY i.created_at DESC,i.id DESC";
$invitationStmt=$pdo->prepare($invitationSql);
$invitationStmt->execute($hearingFilter>0?['hearing_id'=>$hearingFilter]:[]);
$rows=$invitationStmt->fetchAll();
include __DIR__.'/../../layouts/header.php';
?>
<link rel="stylesheet" href="<?= e(APP_URL.'/assets/css/lph-complete-modules.css') ?>">
<div class="app-wrapper"><?php include __DIR__.'/../../layouts/sidebar.php'; ?><div class="main-content">
<div class="lphx-head"><div><div class="lphx-eyebrow">Step 3 · Invitations</div><h1>Hearing Invitations</h1><p>Create single or bulk invitations, issue invitation codes, track delivery-ready state and responses.</p></div><a class="btn btn-outline-secondary" href="index.php"><i class="bi bi-arrow-left"></i> Stakeholders</a></div>

<div class="card lphx-card mb-3"><div class="card-header"><i class="bi bi-envelope-plus"></i> Create Invitations</div><div class="card-body">
<form id="inviteForm" class="row g-3"><?= csrfField() ?>
<div class="col-lg-5"><label class="form-label">Hearing *</label><select name="hearing_id" class="form-select" required><option value="">Select hearing</option><?php foreach($hearings as $h): ?><option value="<?= (int)$h['id'] ?>"><?= e(($h['reference_number']?:'').' '.$h['title'].' · '.formatDate($h['hearing_date'])) ?></option><?php endforeach; ?></select></div>
<div class="col-lg-4"><label class="form-label">Expiration</label><input type="datetime-local" name="expires_at" class="form-control"></div>
<div class="col-lg-3 d-flex align-items-end"><button class="btn btn-primary w-100"><i class="bi bi-send"></i> Create Invitation(s)</button></div>
<div class="col-12"><label class="form-label">Stakeholders *</label><select name="stakeholder_ids[]" class="form-select" multiple size="7" required><?php foreach($stakeholders as $s): ?><option value="<?= (int)$s['id'] ?>"><?= e($s['full_name'].' · '.$s['email'].' · '.$s['status']) ?></option><?php endforeach; ?></select><div class="form-text">Use Ctrl/Command to select multiple stakeholders.</div></div>
<div class="col-12"><label class="form-label">Remarks</label><textarea class="form-control" name="remarks" rows="2"></textarea></div>
</form>
</div></div>

<div class="card lphx-card"><div class="card-header"><i class="bi bi-envelope-paper"></i> Invitation Register</div>
<div class="card-body border-bottom py-3">
<form method="get" class="row g-2 align-items-end">
<div class="col-lg-5 col-md-7"><label for="hearingFilter" class="form-label">Filter by Hearing</label><select id="hearingFilter" name="hearing_id" class="form-select"><option value="">All Hearings</option><?php foreach($filterHearings as $h): ?><option value="<?= (int)$h['id'] ?>" <?= $hearingFilter===(int)$h['id']?'selected':'' ?>><?= e(($h['reference_number']?:'').' '.$h['title'].' · '.formatDate($h['hearing_date'])) ?></option><?php endforeach; ?></select></div>
<div class="col-auto"><button class="btn btn-primary" type="submit"><i class="bi bi-funnel"></i> Apply Filter</button></div>
<?php if($hearingFilter>0): ?><div class="col-auto"><a class="btn btn-outline-secondary" href="invitations.php">Clear</a></div><?php endif; ?>
</form>
</div><div class="table-responsive">
<table class="table table-hover lphx-table mb-0"><thead><tr><th>Code</th><th>Stakeholder</th><th>Hearing</th><th>Created / Sent</th><th>Status</th><th class="text-end">Update</th></tr></thead><tbody>
<?php if(!$rows): ?><tr><td colspan="6"><div class="lphx-empty"><i class="bi bi-envelope"></i><?= $hearingFilter>0?'No invitations found for this hearing.':'No invitations yet.' ?></div></td></tr><?php endif; ?>
<?php foreach($rows as $r): ?><tr>
<td><span class="lphx-code"><?= e($r['invitation_code']?:'—') ?></span></td>
<td><strong><?= e($r['full_name']) ?></strong><div class="small text-muted"><?= e($r['email']) ?></div></td>
<td><?= e($r['hearing_title']?:'—') ?><div class="small text-muted"><?= !empty($r['hearing_date'])?formatDate($r['hearing_date']):'' ?></div></td>
<td><?= formatDateTime($r['created_at']) ?><div class="small text-muted"><?= $r['sent_at']?'Sent '.formatDateTime($r['sent_at']):'Not marked sent' ?></div></td>
<td><span class="badge text-bg-light"><?= e($r['status']) ?></span></td>
<td class="text-end"><div class="btn-group btn-group-sm">
<?php foreach(['Sent','Accepted','Declined','Cancelled'] as $st): ?><button class="btn btn-outline-secondary btn-invite-status" data-id="<?= (int)$r['id'] ?>" data-status="<?= e($st) ?>"><?= e($st) ?></button><?php endforeach; ?>
</div></td>
</tr><?php endforeach; ?>
</tbody></table></div></div>
</div></div>
<script>
document.addEventListener('DOMContentLoaded',function(){
 inviteForm.onsubmit=async e=>{e.preventDefault();const r=await appPostForm(APP_URL+'/modules/stakeholders/ajax_invite.php',inviteForm);if(r.success){appToast('success',r.message);setTimeout(()=>location.reload(),400);}else if(!r.session_expired)Swal.fire('Invitation Error',r.message,'error');};
 document.querySelectorAll('.btn-invite-status').forEach(b=>b.onclick=async function(){const fd=new FormData();fd.append('csrf_token',document.querySelector('[name=csrf_token]').value);fd.append('id',this.dataset.id);fd.append('status',this.dataset.status);const r=await fetch(APP_URL+'/modules/stakeholders/ajax_invitation_status.php',{method:'POST',body:fd}).then(x=>x.json());if(r.success)location.reload();else Swal.fire('Update Failed',r.message,'error');});
});
</script>
<?php include __DIR__.'/../../layouts/footer.php'; ?>
