<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/vqdss_operational_helpers.php';

requirePermission('vqd.quorum.view');

$pageTitle='Quorum Verification';
$activeMenu='quorum';
$extraCss=[appUrl('assets/css/vqdss-operational.css')];
$pdo=db();

$items=$pdo->query(
 "SELECT li.id,li.reference_number,li.title,lit.name type_name
  FROM legislative_items li
  JOIN legislative_item_types lit ON lit.id=li.item_type_id
  WHERE li.deleted_at IS NULL
    AND lit.code IN ('ordinance','resolution')
  ORDER BY li.updated_at DESC LIMIT 400"
)->fetchAll();

$committees=$pdo->query('SELECT id,name FROM committees ORDER BY name')->fetchAll();
$offices=$pdo->query("SELECT id,name FROM offices WHERE status='Active' ORDER BY name")->fetchAll();
$users=$pdo->query(
 "SELECT u.id,u.full_name,u.email,r.name role_name
  FROM users u JOIN roles r ON r.id=u.role_id
  WHERE u.status='Active' AND u.deleted_at IS NULL
  ORDER BY u.full_name"
)->fetchAll();

$cmMap=[];
$cmRows=$pdo->query(
 "SELECT cm.committee_id, cm.user_id, u.full_name
  FROM committee_members cm
  JOIN users u ON u.id=cm.user_id
  WHERE cm.is_active=1 AND u.status='Active' AND u.deleted_at IS NULL
  UNION
  SELECT c.id committee_id, u.id user_id, u.full_name
  FROM users u
  JOIN committees c ON (c.office_id=u.office_id OR (c.id=4 AND u.office_id=6))
  WHERE u.status='Active' AND u.deleted_at IS NULL AND u.role_id=3"
)->fetchAll();
foreach($cmRows as $cm){
 $cmMap[(int)$cm['committee_id']][]=[
  'id'=>(int)$cm['user_id'],
  'name'=>$cm['full_name']
 ];
}

$sessions=$pdo->query(
 "SELECT s.*,li.reference_number legislative_reference,li.title legislative_title,
         c.name committee_name,
         (SELECT COUNT(*) FROM vqd_session_members sm WHERE sm.session_id=s.id) member_count,
         (SELECT qc.id FROM vqd_quorum_checks qc WHERE qc.session_id=s.id ORDER BY qc.id DESC LIMIT 1) latest_check_id,
         (SELECT qc.reference_number FROM vqd_quorum_checks qc WHERE qc.session_id=s.id ORDER BY qc.id DESC LIMIT 1) quorum_reference,
         (SELECT qc.quorum_result FROM vqd_quorum_checks qc WHERE qc.session_id=s.id ORDER BY qc.id DESC LIMIT 1) quorum_result,
         (SELECT qc.verification_status FROM vqd_quorum_checks qc WHERE qc.session_id=s.id ORDER BY qc.id DESC LIMIT 1) verification_status
  FROM vqd_sessions s
  LEFT JOIN legislative_items li ON li.id=s.legislative_item_id
  LEFT JOIN committees c ON c.id=s.committee_id
  ORDER BY s.session_date DESC,s.start_time DESC,s.id DESC"
)->fetchAll();

$stats=$pdo->query(
 "SELECT
   (SELECT COUNT(*) FROM vqd_sessions) sessions,
   (SELECT COUNT(*) FROM vqd_quorum_checks) checks_total,
   (SELECT COUNT(*) FROM vqd_quorum_checks WHERE quorum_result='Met') quorum_met,
   (SELECT COUNT(*) FROM vqd_quorum_checks WHERE verification_status='Certified') certified"
)->fetch();

include __DIR__.'/../../layouts/header.php';
?>
<div class="app-wrapper">
<?php include __DIR__.'/../../layouts/sidebar.php'; ?>
<main class="main-content">

<div class="vqdx-head">
<div>
<div class="vqdx-eyebrow"><i class="bi bi-person-check"></i> Step 2 · Operational Backend</div>
<h1>Quorum Verification</h1>
<p>Create official voting sessions, establish the member roster, record attendance and eligibility, calculate the quorum threshold automatically, then verify and certify voting readiness.</p>
</div>
<?php if(vqdOperationalManager()): ?><button class="btn btn-primary" id="btnNewSession"><i class="bi bi-calendar-plus"></i> New Voting Session</button><?php endif; ?>
</div>

<div class="vqdx-flow">
<div><strong>1. Voting Session</strong><small>Schedule and legislative item</small></div>
<div><strong>2. Official Roster</strong><small>Voting members and committee</small></div>
<div><strong>3. Attendance</strong><small>Present, remote, absent, excused</small></div>
<div><strong>4. Calculation</strong><small>Required vs present eligible</small></div>
<div><strong>5. Certification</strong><small>Unlock Voting Management</small></div>
</div>

<div class="row g-3 mb-3">
<?php foreach([
 ['Voting Sessions',$stats['sessions']??0,'bi-calendar3'],
 ['Quorum Checks',$stats['checks_total']??0,'bi-calculator'],
 ['Quorum Met',$stats['quorum_met']??0,'bi-check-circle'],
 ['Certified',$stats['certified']??0,'bi-patch-check'],
] as [$l,$v,$i]): ?><div class="col-6 col-lg-3"><div class="vqdx-stat"><i class="bi <?= e($i) ?>"></i><div><strong><?= (int)$v ?></strong><small><?= e($l) ?></small></div></div></div><?php endforeach; ?>
</div>

<div class="card vqdx-card">
<div class="card-header d-flex justify-content-between"><span><i class="bi bi-clipboard-check"></i> Session & Quorum Registry</span><span><?= count($sessions) ?> session(s)</span></div>
<div class="table-responsive">
<table class="table table-hover vqdx-table mb-0">
<thead><tr><th>Session</th><th>Legislative Item</th><th>Committee</th><th>Schedule</th><th>Roster</th><th>Quorum</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
<tbody>
<?php if(!$sessions): ?><tr><td colspan="8" class="text-center text-muted py-5">No voting sessions have been created yet.</td></tr><?php endif; ?>
<?php foreach($sessions as $s): ?>
<tr>
<td><span class="vqdx-code"><?= e($s['reference_number']) ?></span><div><strong><?= e($s['session_title']) ?></strong></div><div class="small text-muted"><?= e($s['session_mode']) ?></div></td>
<td><?php if($s['legislative_reference']): ?><strong><?= e($s['legislative_reference']) ?></strong><div class="small text-muted"><?= e(mb_strimwidth($s['legislative_title'],0,90,'…')) ?></div><?php else: ?><span class="text-muted">Not linked</span><?php endif; ?></td>
<td><?= e($s['committee_name']?:'—') ?></td>
<td><?= formatDate($s['session_date']) ?><div class="small text-muted"><?= formatTime($s['start_time']) ?><?= $s['end_time']?' - '.formatTime($s['end_time']):'' ?></div></td>
<td><strong><?= (int)$s['member_count'] ?></strong> member(s)</td>
<td><?php if($s['latest_check_id']): ?><span class="vqdx-result <?= $s['quorum_result']==='Met'?'met':($s['quorum_result']==='Not Met'?'not-met':'pending') ?>"><?= e($s['quorum_result']) ?></span><div class="small text-muted"><?= e($s['verification_status']) ?> · <?= e($s['quorum_reference']) ?></div><?php else: ?><span class="text-muted">Not checked</span><?php endif; ?></td>
<td><span class="badge text-bg-light"><?= e($s['status']) ?></span></td>
<td class="text-end"><div class="btn-group btn-group-sm">
<?php if($s['latest_check_id']): ?><a class="btn btn-outline-primary" href="view.php?id=<?= (int)$s['latest_check_id'] ?>"><i class="bi bi-eye"></i></a><?php endif; ?>
<?php if(vqdOperationalManager()): ?><button class="btn btn-outline-success btn-new-check" data-session="<?= (int)$s['id'] ?>"><i class="bi bi-calculator"></i></button><?php endif; ?>
</div></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div></div>

</main></div>

<?php if(vqdOperationalManager()): ?>
<div class="modal fade" id="sessionModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" style="max-width: 680px;"><div class="modal-content shadow-lg border-0" style="border-radius: 14px; overflow: hidden;">
<form id="sessionForm"><?= csrfField() ?><input type="hidden" name="id" value="0">
<div class="modal-header bg-dark text-white py-2.5 px-3.5"><h6 class="modal-title fw-bold mb-0">Create Voting Session</h6><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
<div class="modal-body p-3.5">
<div class="row g-2">
<div class="col-lg-7"><label class="form-label small fw-semibold text-secondary mb-1">Session Title *</label><input class="form-control form-control-sm" name="session_title" required></div>
<div class="col-lg-5"><label class="form-label small fw-semibold text-secondary mb-1">Legislative Item</label><select class="form-select form-select-sm" name="legislative_item_id"><option value="">Not linked</option><?php foreach($items as $i): ?><option value="<?= (int)$i['id'] ?>"><?= e('['.$i['type_name'].'] '.$i['reference_number'].' - '.$i['title']) ?></option><?php endforeach; ?></select></div>
<div class="col-md-4"><label class="form-label small fw-semibold text-secondary mb-1">Session Type</label><select class="form-select form-select-sm" name="session_type"><option>Regular Session</option><option>Special Session</option><option>Committee Session</option><option>Emergency Session</option></select></div>
<div class="col-md-4"><label class="form-label small fw-semibold text-secondary mb-1">Session Mode</label><select class="form-select form-select-sm" name="session_mode"><option>On-site</option><option>Online</option><option>Hybrid</option></select></div>
<div class="col-md-4"><label class="form-label small fw-semibold text-secondary mb-1">Committee</label><select class="form-select form-select-sm" name="committee_id" id="sessionCommittee"><option value="">No committee</option><?php foreach($committees as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
<div class="col-md-3"><label class="form-label small fw-semibold text-secondary mb-1">Date *</label><input type="date" class="form-control form-control-sm" name="session_date" required></div>
<div class="col-md-3"><label class="form-label small fw-semibold text-secondary mb-1">Start *</label><input type="time" class="form-control form-control-sm" name="start_time" required></div>
<div class="col-md-3"><label class="form-label small fw-semibold text-secondary mb-1">End</label><input type="time" class="form-control form-control-sm" name="end_time"></div>
<div class="col-md-3"><label class="form-label small fw-semibold text-secondary mb-1">Office</label><select class="form-select form-select-sm" name="office_id"><option value="">No office</option><?php foreach($offices as $o): ?><option value="<?= (int)$o['id'] ?>"><?= e($o['name']) ?></option><?php endforeach; ?></select></div>
<div class="col-md-6"><label class="form-label small fw-semibold text-secondary mb-1">Venue</label><input class="form-control form-control-sm" name="venue"></div>
<div class="col-md-6"><label class="form-label small fw-semibold text-secondary mb-1">Online Meeting Link</label><input type="url" class="form-control form-control-sm" name="meeting_link" placeholder="https://..."></div>
<div class="col-md-6"><label class="form-label small fw-semibold text-secondary mb-1">Presiding Officer</label><select class="form-select form-select-sm" name="presiding_officer_id"><option value="">Unassigned</option><?php foreach($users as $u): ?><option value="<?= (int)$u['id'] ?>"><?= e($u['full_name'].' · '.$u['role_name']) ?></option><?php endforeach; ?></select></div>
<div class="col-md-6"><label class="form-label small fw-semibold text-secondary mb-1">Secretary / Clerk</label><select class="form-select form-select-sm" name="secretary_user_id"><option value="">Unassigned</option><?php foreach($users as $u): ?><option value="<?= (int)$u['id'] ?>"><?= e($u['full_name'].' · '.$u['role_name']) ?></option><?php endforeach; ?></select></div>
<div class="col-12">
  <div class="d-flex justify-content-between align-items-center mb-1">
    <label class="form-label small fw-semibold text-secondary mb-0">Official Session Members *</label>
    <div class="btn-group btn-group-sm">
      <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2" id="btnSelectAllMembers" style="font-size:0.75rem;">Select All</button>
      <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2" id="btnClearAllMembers" style="font-size:0.75rem;">Clear All</button>
    </div>
  </div>
  <select class="form-select form-select-sm" name="member_ids[]" id="sessionMembers" multiple size="6" required>
    <?php foreach($users as $u): ?>
      <option value="<?= (int)$u['id'] ?>" data-name="<?= e($u['full_name']) ?>"><?= e($u['full_name'].' · '.$u['email'].' · '.$u['role_name']) ?></option>
    <?php endforeach; ?>
  </select>
  <div class="form-check mt-1.5 d-flex align-items-center gap-1.5">
    <input class="form-check-input mt-0" type="checkbox" name="include_committee_roster" id="includeCommittee">
    <label class="form-check-label small fw-semibold text-dark" for="includeCommittee">Also import all active members of the selected committee</label>
  </div>
  <div id="committeeImportBadge" class="small text-success mt-1 fw-medium" style="display:none;"><i class="bi bi-check-circle-fill me-1"></i><span id="committeeImportText"></span></div>
</div>
<div class="col-12"><label class="form-label small fw-semibold text-secondary mb-1">Notes</label><textarea class="form-control form-control-sm" name="notes" rows="2"></textarea></div>
</div></div>
<div class="modal-footer py-2 px-3.5"><button class="btn btn-sm btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancel</button><button class="btn btn-sm btn-primary">Save Session</button></div>
</form></div></div></div>

<div class="modal fade" id="checkModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered" style="max-width: 540px;"><div class="modal-content shadow-lg border-0" style="border-radius: 14px; overflow: hidden;">
<form id="checkForm"><?= csrfField() ?><input type="hidden" name="session_id" id="checkSessionId">
<div class="modal-header bg-dark text-white py-2.5 px-3.5"><h6 class="modal-title fw-bold mb-0">Create Quorum Check</h6><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
<div class="modal-body p-3.5"><div class="row g-2">
<div class="col-md-6"><label class="form-label">Quorum Rule</label><select class="form-select" name="quorum_rule" id="quorumRule"><option>Majority</option><option>Two-Thirds</option><option>Three-Fourths</option><option>Unanimous</option><option>Custom Percentage</option><option>Fixed Count</option></select></div>
<div class="col-md-6"><label class="form-label">Basis</label><select class="form-select" name="quorum_basis"><option>Eligible Voting Members</option><option>Official Members</option></select></div>
<div class="col-md-6"><label class="form-label">Custom Percentage</label><input type="number" step=".01" min="0" max="100" class="form-control" name="custom_percentage" placeholder="Example: 66.67"></div>
<div class="col-md-6"><label class="form-label">Fixed Required Count</label><input type="number" min="1" class="form-control" name="fixed_required_count"></div>
<div class="col-12"><label class="form-label">Initial Notes</label><textarea class="form-control" name="certification_notes" rows="3"></textarea></div>
</div></div>
<div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Create Check</button></div>
</form></div></div></div>

<script>
document.addEventListener('DOMContentLoaded',function(){
 const smodal=new bootstrap.Modal(document.getElementById('sessionModal'));
 const qmodal=new bootstrap.Modal(document.getElementById('checkModal'));
 const committeeMap = <?= json_encode($cmMap) ?>;
 const includeCommEl = document.getElementById('includeCommittee');
 const commSelectEl = document.getElementById('sessionCommittee');
 const membersSelectEl = document.getElementById('sessionMembers');
 const importBadge = document.getElementById('committeeImportBadge');
 const importText = document.getElementById('committeeImportText');

 function syncCommitteeMembers() {
  if (!includeCommEl || !includeCommEl.checked) {
   if (importBadge) importBadge.style.display = 'none';
   return;
  }
  const cid = parseInt(commSelectEl.value, 10);
  if (!cid || !committeeMap[cid] || committeeMap[cid].length === 0) {
   if (importBadge) {
    importBadge.style.display = 'block';
    importBadge.className = 'small text-warning mt-1 fw-medium';
    importText.innerHTML = '<i class="bi bi-exclamation-circle me-1"></i>No active committee members found for this committee.';
   }
   return;
  }
  const members = committeeMap[cid];
  const importedNames = [];
  const targetIds = new Set(members.map(m => String(m.id)));
  for (let opt of membersSelectEl.options) {
   if (targetIds.has(opt.value)) {
    opt.selected = true;
    importedNames.push(opt.getAttribute('data-name') || opt.text.split(' · ')[0]);
   }
  }
  if (importBadge) {
   importBadge.style.display = 'block';
   importBadge.className = 'small text-success mt-1 fw-medium';
   importText.innerHTML = `<i class="bi bi-check-circle-fill me-1"></i>Imported <strong>${importedNames.length}</strong> active committee member(s): ${importedNames.join(', ')}`;
  }
 }

 if (includeCommEl) includeCommEl.addEventListener('change', syncCommitteeMembers);
 if (commSelectEl) commSelectEl.addEventListener('change', function(){
  if (includeCommEl && includeCommEl.checked) syncCommitteeMembers();
 });

 const btnSelectAll = document.getElementById('btnSelectAllMembers');
 if (btnSelectAll) {
  btnSelectAll.addEventListener('click', () => {
   for (let opt of membersSelectEl.options) opt.selected = true;
  });
 }
 const btnClearAll = document.getElementById('btnClearAllMembers');
 if (btnClearAll) {
  btnClearAll.addEventListener('click', () => {
   for (let opt of membersSelectEl.options) opt.selected = false;
   if (includeCommEl) includeCommEl.checked = false;
   if (importBadge) importBadge.style.display = 'none';
  });
 }

 btnNewSession.onclick=()=>{
  sessionForm.reset();
  if (importBadge) importBadge.style.display = 'none';
  smodal.show();
 };
 sessionForm.onsubmit=async e=>{e.preventDefault();const r=await fetch('ajax_session_save.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:new FormData(sessionForm)}).then(x=>x.json());if(r.success){await Swal.fire('Saved',r.message,'success');location.reload();}else Swal.fire('Session Error',r.message,'error');};
 document.querySelectorAll('.btn-new-check').forEach(b=>b.onclick=function(){checkForm.reset();checkSessionId.value=this.dataset.session;qmodal.show();});
 checkForm.onsubmit=async e=>{e.preventDefault();const r=await fetch('ajax_create_check.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:new FormData(checkForm)}).then(x=>x.json());if(r.success){location.href='view.php?id='+r.id;}else Swal.fire('Quorum Error',r.message,'error');};
});
</script>
<?php endif; ?>

<?php include __DIR__.'/../../layouts/footer.php'; ?>
