<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/vqdss_operational_helpers.php';

requirePermission('vqd.quorum.view');

$pdo=db();$id=(int)($_GET['id']??0);

$stmt=$pdo->prepare(
 "SELECT qc.*,s.reference_number session_reference,s.session_title,s.session_type,s.session_mode,
         s.session_date,s.start_time,s.end_time,s.venue,s.meeting_link,s.status session_status,
         li.reference_number legislative_reference,li.title legislative_title,
         c.name committee_name,
         cb.full_name checked_by_name,cert.full_name certified_by_name
  FROM vqd_quorum_checks qc
  JOIN vqd_sessions s ON s.id=qc.session_id
  LEFT JOIN legislative_items li ON li.id=qc.legislative_item_id
  LEFT JOIN committees c ON c.id=s.committee_id
  LEFT JOIN users cb ON cb.id=qc.checked_by
  LEFT JOIN users cert ON cert.id=qc.certified_by
  WHERE qc.id=:id"
);
$stmt->execute([':id'=>$id]);$check=$stmt->fetch();
if(!$check){setFlash('warning','Quorum check not found.');redirect(appUrl('modules/quorum/index.php'));}

$members=$pdo->prepare(
 "SELECT qcm.*,u.full_name,u.email,sm.position_title
  FROM vqd_quorum_check_members qcm
  JOIN users u ON u.id=qcm.user_id
  LEFT JOIN vqd_session_members sm ON sm.id=qcm.session_member_id
  WHERE qcm.quorum_check_id=:id ORDER BY u.full_name"
);
$members->execute([':id'=>$id]);$memberRows=$members->fetchAll();

$docs=$pdo->prepare('SELECT * FROM vqd_quorum_documents WHERE quorum_check_id=:id ORDER BY uploaded_at DESC');
$docs->execute([':id'=>$id]);$documents=$docs->fetchAll();

$history=$pdo->prepare(
 "SELECT h.*,u.full_name
  FROM vqd_quorum_history h LEFT JOIN users u ON u.id=h.changed_by
  WHERE h.quorum_check_id=:id ORDER BY h.created_at DESC,h.id DESC"
);
$history->execute([':id'=>$id]);$historyRows=$history->fetchAll();

$percentage=$check['eligible_member_count']>0
 ? min(100,round(((int)$check['present_eligible_count']/(int)$check['eligible_member_count'])*100))
 : 0;

$pageTitle=$check['reference_number'];
$activeMenu='quorum';
$extraCss=[appUrl('assets/css/vqdss-operational.css')];

include __DIR__.'/../../layouts/header.php';
?>
<div class="app-wrapper"><?php include __DIR__.'/../../layouts/sidebar.php'; ?><main class="main-content">

<div class="vqdx-head">
<div><a class="small text-decoration-none" href="index.php"><i class="bi bi-arrow-left"></i> Quorum Verification</a><div class="vqdx-eyebrow mt-2"><?= e($check['reference_number']) ?></div><h1><?= e($check['session_title']) ?></h1><p><?= e(($check['legislative_reference']?:'No legislative item').' · '.($check['committee_name']?:'No committee')) ?></p></div>
<div class="d-flex gap-2 align-items-start flex-wrap"><span class="vqdx-result <?= $check['quorum_result']==='Met'?'met':($check['quorum_result']==='Not Met'?'not-met':'pending') ?>"><?= e($check['quorum_result']) ?></span><span class="badge text-bg-light fs-6"><?= e($check['verification_status']) ?></span><a class="btn btn-outline-secondary btn-sm" target="_blank" href="print.php?id=<?= $id ?>"><i class="bi bi-printer"></i> Print</a></div>
</div>

<div class="row g-3 mb-3">
<?php foreach([
 ['Official Members',$check['official_member_count'],'bi-people'],
 ['Eligible',$check['eligible_member_count'],'bi-person-check'],
 ['Present Eligible',$check['present_eligible_count'],'bi-person-check-fill'],
 ['Required',$check['required_count'],'bi-calculator'],
 ['Remote Present',$check['remote_present_count'],'bi-camera-video'],
] as [$l,$v,$i]): ?><div class="col-6 col-md"><div class="vqdx-stat"><i class="bi <?= e($i) ?>"></i><div><strong><?= (int)$v ?></strong><small><?= e($l) ?></small></div></div></div><?php endforeach; ?>
</div>

<div class="card vqdx-card mb-3"><div class="card-header">Quorum Calculation</div><div class="card-body">
<div class="vqdx-grid mb-3"><div><small>Rule</small><strong><?= e($check['quorum_rule']) ?></strong></div><div><small>Basis</small><strong><?= e($check['quorum_basis']) ?></strong></div><div><small>Required</small><strong><?= (int)$check['required_count'] ?> of <?= (int)$check['eligible_member_count'] ?> eligible</strong></div><div><small>Present</small><strong><?= (int)$check['present_eligible_count'] ?> eligible voting member(s)</strong></div></div>
<div class="d-flex justify-content-between small mb-1"><span>Present eligible percentage</span><strong><?= $percentage ?>%</strong></div><div class="vqdx-meter"><span style="width:<?= $percentage ?>%"></span></div>
</div></div>

<div class="card vqdx-card mb-3">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span>Official Roster & Attendance</span>
    <div class="d-flex align-items-center gap-2">
      <span class="text-secondary small"><?= count($memberRows) ?> member(s)</span>
      <?php if($check['verification_status']!=='Certified' && vqdOperationalManager()): ?>
        <button type="button" class="btn btn-sm btn-outline-success py-0.5 px-2" id="btnMarkAllPresent" style="font-size:0.78rem;">
          <i class="bi bi-check-all"></i> Mark All Present & Eligible
        </button>
      <?php endif; ?>
    </div>
  </div>
<div>
<?php foreach($memberRows as $m): ?>
<form class="vqdx-roster member-form">
<?= csrfField() ?><input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
<div><strong><?= e($m['full_name']) ?></strong><div class="small text-muted"><?= e($m['position_title']?:$m['email']) ?></div></div>
<div><select class="form-select form-select-sm" name="attendance_status" <?= $check['verification_status']==='Certified'?'disabled':'' ?>><?php foreach(['Pending','Present','Remote Present','Absent','Excused'] as $a): ?><option <?= $m['attendance_status']===$a?'selected':'' ?>><?= e($a) ?></option><?php endforeach; ?></select></div>
<div><select class="form-select form-select-sm" name="voting_eligibility" <?= $check['verification_status']==='Certified'?'disabled':'' ?>><?php foreach(['Eligible','Ineligible','Abstain Only','Conflict'] as $a): ?><option <?= $m['voting_eligibility']===$a?'selected':'' ?>><?= e($a) ?></option><?php endforeach; ?></select></div>
<div><div class="form-check"><input class="form-check-input" type="checkbox" name="included_in_quorum_count" <?= $m['included_in_quorum_count']?'checked':'' ?> <?= $check['verification_status']==='Certified'?'disabled':'' ?>><label class="form-check-label small">Count</label></div></div>
<div><?php if($check['verification_status']!=='Certified'): ?><button class="btn btn-sm btn-outline-primary w-100">Save</button><?php else: ?><span class="badge text-bg-success">Locked</span><?php endif; ?></div>
</form>
<?php endforeach; ?>
</div></div>

<?php if(vqdOperationalManager()): ?>
<div class="card vqdx-card mb-3"><div class="card-header">Verification & Certification</div><div class="card-body">
<form id="transitionForm"><?= csrfField() ?><input type="hidden" name="id" value="<?= $id ?>"><textarea class="form-control mb-2" name="notes" rows="2" placeholder="Verification, certification or revocation notes"></textarea><div class="d-flex gap-2 flex-wrap">
<?php if(in_array($check['verification_status'],['Draft','Verified'],true)): ?><button type="button" class="btn btn-outline-primary transition-btn" data-action="verify"><i class="bi bi-check2-square"></i> Verify</button><?php endif; ?>
<?php if(in_array($check['verification_status'],['Draft','Verified'],true)): ?><button type="button" class="btn btn-success transition-btn" data-action="certify"><i class="bi bi-patch-check"></i> Certify Quorum</button><?php endif; ?>
<?php if($check['verification_status']==='Certified'): ?><button type="button" class="btn btn-outline-danger transition-btn" data-action="revoke"><i class="bi bi-x-octagon"></i> Revoke Certification</button><a class="btn btn-primary" href="<?= e(appUrl('modules/voting/index.php?quorum_check_id='.$id)) ?>"><i class="bi bi-box-arrow-right"></i> Continue to Voting</a><?php endif; ?>
</div></form>
</div></div>

<div class="card vqdx-card mb-3"><div class="card-header">Quorum Evidence</div><div class="card-body"><form id="docForm" class="row g-2 align-items-end" enctype="multipart/form-data"><?= csrfField() ?><input type="hidden" name="quorum_check_id" value="<?= $id ?>"><div class="col-lg-4"><label class="form-label small">Document</label><input type="file" class="form-control form-control-sm" name="document" required></div><div class="col-lg-3"><label class="form-label small">Type</label><input class="form-control form-control-sm" name="document_type" value="Quorum Evidence"></div><div class="col-lg-2"><label class="form-label small">Visibility</label><select class="form-select form-select-sm" name="visibility"><option>Internal</option><option>Public</option><option>Restricted</option></select></div><div class="col-lg-3"><button class="btn btn-primary btn-sm w-100"><i class="bi bi-upload"></i> Upload</button></div><div class="col-12"><input class="form-control form-control-sm" name="description" placeholder="Optional description"></div></form></div></div>
<?php endif; ?>

<div class="row g-3">
<div class="col-xl-7"><div class="card vqdx-card h-100"><div class="card-header">Documents</div><div class="list-group list-group-flush"><?php if(!$documents): ?><div class="p-4 text-muted text-center">No quorum evidence uploaded.</div><?php endif; ?><?php foreach($documents as $d): ?><a class="list-group-item list-group-item-action" target="_blank" href="<?= e(appUrl('assets/uploads/'.$d['file_path'])) ?>"><strong><?= e($d['file_name']) ?></strong><div class="small text-muted"><?= e($d['document_type'].' · '.$d['visibility']) ?> · <?= formatDateTime($d['uploaded_at']) ?></div></a><?php endforeach; ?></div></div></div>
<div class="col-xl-5"><div class="card vqdx-card h-100"><div class="card-header">Verification History</div><div class="card-body vqdx-history"><?php foreach($historyRows as $h): ?><div><strong><?= e($h['action']) ?> · <?= e($h['full_name']?:'System') ?></strong><small><?= e($h['details']?:'') ?><br><?= formatDateTime($h['created_at']) ?></small></div><?php endforeach; ?></div></div></div>
</div>

</main></div>

<script>
document.addEventListener('DOMContentLoaded',function(){
 const btnAllPresent = document.getElementById('btnMarkAllPresent');
 if (btnAllPresent) {
  btnAllPresent.onclick = async function() {
   const ask = await Swal.fire({
    title: 'Mark all members present?',
    text: 'All session roster members will be marked as Present and Eligible to vote.',
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Yes, mark all present'
   });
   if (!ask.isConfirmed) return;
   const fd = new FormData();
   fd.append('csrf_token', '<?= csrfToken() ?>');
   fd.append('action', 'mark_all_present');
   fd.append('quorum_check_id', '<?= $id ?>');
   const r = await fetch('ajax_member_update.php', {
    method: 'POST',
    headers: {'X-Requested-With': 'XMLHttpRequest'},
    body: fd
   }).then(x => x.json());
   if (r.success) {
    await Swal.fire('Updated', r.message, 'success');
    location.reload();
   } else {
    Swal.fire('Error', r.message, 'error');
   }
  };
 }
 document.querySelectorAll('.member-form').forEach(f=>f.onsubmit=async e=>{e.preventDefault();const r=await fetch('ajax_member_update.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:new FormData(f)}).then(x=>x.json());if(r.success){if(typeof appToast==='function')appToast('success',r.message);setTimeout(()=>location.reload(),250);}else Swal.fire('Roster Error',r.message,'error');});
 document.querySelectorAll('.transition-btn').forEach(b=>b.onclick=async function(){const fd=new FormData(transitionForm);fd.append('action',this.dataset.action);const r=await fetch('ajax_transition.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:fd}).then(x=>x.json());if(r.success){await Swal.fire('Quorum Updated',r.message,'success');location.reload();}else Swal.fire('Workflow Error',r.message,'error');});
 const df=document.getElementById('docForm');if(df)df.onsubmit=async e=>{e.preventDefault();const r=await fetch('ajax_upload_document.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:new FormData(df)}).then(x=>x.json());if(r.success)location.reload();else Swal.fire('Upload Error',r.message,'error');};
});
</script>
<?php include __DIR__.'/../../layouts/footer.php'; ?>
