<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/lph_module_helpers.php';

requireLogin();
if (!canManage()) {
    setFlash('danger','Stakeholder management is limited to authorized staff.');
    redirect(APP_URL . '/dashboard.php');
}

$pdo = db();
$pageTitle = 'Stakeholders & Invitations';
$activeMenu = 'stakeholders';

$categories = $pdo->query('SELECT id,name FROM stakeholder_categories ORDER BY name')->fetchAll();
$stakeholders = $pdo->query(
    'SELECT s.*,sc.name AS category_name,
        (SELECT code_value FROM qr_codes q WHERE q.stakeholder_id=s.id LIMIT 1) AS code_value,
        (SELECT COUNT(*) FROM invitations i WHERE i.stakeholder_id=s.id) AS invitation_count,
        (SELECT COUNT(*) FROM registrations r WHERE r.stakeholder_id=s.id) AS registration_count
     FROM stakeholders s
     LEFT JOIN stakeholder_categories sc ON sc.id=s.category_id
     ORDER BY s.created_at DESC,s.id DESC'
)->fetchAll();

$stats = $pdo->query(
    "SELECT COUNT(*) total,
      SUM(status='Verified') verified,
      SUM(status='Pending') pending,
      SUM(status='Inactive') inactive
     FROM stakeholders"
)->fetch();

include __DIR__ . '/../../layouts/header.php';
?>
<link rel="stylesheet" href="<?= e(APP_URL . '/assets/css/lph-complete-modules.css') ?>">
<div class="app-wrapper">
<?php include __DIR__ . '/../../layouts/sidebar.php'; ?>
<div class="main-content">

<?php include __DIR__ . '/../../layouts/top_controls.php'; ?>

<div class="lphx-head">
    <div>
        <div class="lphx-eyebrow"><i class="bi bi-people"></i> Step 3</div>
        <h1>Stakeholder Invitation & Registration</h1>
        <p>Maintain stakeholder records, verification, invitations, registration approval, capacity control and QR-ready attendance credentials.</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="invitations.php" class="btn btn-outline-secondary"><i class="bi bi-envelope-paper"></i> Invitations</a>
        <a href="registrations.php" class="btn btn-outline-secondary"><i class="bi bi-person-check"></i> Registrations</a>
        <button class="btn btn-primary" id="btnNewStakeholder"><i class="bi bi-person-plus"></i> New Stakeholder</button>
    </div>
</div>

<div class="row g-3 mb-3">
<?php foreach ([
    ['Total',$stats['total'] ?? 0,'bi-people'],
    ['Verified',$stats['verified'] ?? 0,'bi-patch-check'],
    ['Pending',$stats['pending'] ?? 0,'bi-hourglass-split'],
    ['Inactive',$stats['inactive'] ?? 0,'bi-person-x'],
] as [$label,$value,$icon]): ?>
<div class="col-6 col-lg-3"><div class="lphx-stat"><i class="bi <?= e($icon) ?>"></i><div><strong><?= (int)$value ?></strong><small><?= e($label) ?></small></div></div></div>
<?php endforeach; ?>
</div>

<div class="card lphx-card">
<div class="card-header d-flex justify-content-between"><span><i class="bi bi-person-vcard"></i> Stakeholder Registry</span><span><?= count($stakeholders) ?> record(s)</span></div>
<div class="table-responsive">
<table class="table table-hover lphx-table mb-0">
<thead><tr><th>Name</th><th>Category / Sector</th><th>Contact</th><th>Status</th><th>Activity</th><th class="text-end">Actions</th></tr></thead>
<tbody>
<?php if (!$stakeholders): ?><tr><td colspan="6"><div class="lphx-empty"><i class="bi bi-people"></i>No stakeholders yet.</div></td></tr><?php endif; ?>
<?php foreach ($stakeholders as $s): ?>
<tr>
<td>
    <strong><?= e($s['full_name']) ?></strong>
    <div class="small text-muted"><?= e($s['organization'] ?: 'Individual') ?></div>
    <?php if (!empty($s['code_value'])): ?>
        <a href="qr.php?id=<?= (int)$s['id'] ?>" class="badge bg-light text-dark border text-decoration-none mt-1 d-inline-flex align-items-center gap-1 font-monospace" title="Click to view QR Attendance Pass">
            <i class="bi bi-qr-code text-primary"></i> <?= e($s['code_value']) ?>
        </a>
    <?php endif; ?>
</td>
<td><?= e($s['category_name'] ?: 'Uncategorized') ?><div class="small text-muted"><?= e($s['sector'] ?: '—') ?></div></td>
<td><?= e($s['email']) ?><div class="small text-muted"><?= e($s['phone'] ?: '—') ?></div></td>
<td><span class="badge text-bg-<?= $s['status']==='Verified'?'success':($s['status']==='Pending'?'warning':'secondary') ?>"><?= e($s['status']) ?></span></td>
<td><span class="small"><?= (int)$s['invitation_count'] ?> invite(s) · <?= (int)$s['registration_count'] ?> registration(s)</span></td>
<td class="text-end">
    <a href="qr.php?id=<?= (int)$s['id'] ?>" class="btn btn-sm btn-outline-dark" title="View & Print QR Attendance Pass"><i class="bi bi-qr-code"></i></a>
    <button class="btn btn-sm btn-outline-primary btn-edit-stakeholder"
        data-row='<?= e(json_encode($s, JSON_HEX_APOS|JSON_HEX_QUOT)) ?>' title="Edit Stakeholder"><i class="bi bi-pencil"></i></button>
    <button class="btn btn-sm btn-outline-danger"
        data-confirm-delete="stakeholder &quot;<?= e($s['full_name']) ?>&quot;"
        data-delete-url="<?= e(APP_URL) ?>/modules/stakeholders/ajax_stakeholder_delete.php?id=<?= (int)$s['id'] ?>" title="Delete Stakeholder"><i class="bi bi-trash"></i></button>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>

</div></div>

<div class="modal fade" id="stakeholderModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" style="max-width: 620px;">
    <div class="modal-content border-0 shadow-lg" style="border-radius: 14px; overflow: hidden;">
      <form id="stakeholderForm">
        <?= csrfField() ?>
        <input type="hidden" name="id" id="s_id" value="0">
        <div class="modal-header py-2.5 px-3.5" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); border-bottom: 2px solid #3b82f6;">
          <div class="d-flex align-items-center gap-2">
            <div class="rounded-circle d-flex align-items-center justify-content-center bg-primary bg-opacity-25 text-info" style="width: 32px; height: 32px;">
              <i class="bi bi-person-badge fs-6"></i>
            </div>
            <div>
              <h6 class="modal-title text-white fw-bold mb-0" style="font-size: 0.95rem;">Stakeholder Record</h6>
              <small class="text-white-50" style="font-size: 0.72rem;">Manage organization, civic representative, or individual participant</small>
            </div>
          </div>
          <button class="btn-close btn-close-white" type="button" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-3.5">
          <div class="row g-2">
            <div class="col-md-7">
              <label class="form-label small fw-semibold text-secondary mb-1">Full Name *</label>
              <input class="form-control form-control-sm" name="full_name" id="s_name" required>
            </div>
            <div class="col-md-5">
              <label class="form-label small fw-semibold text-secondary mb-1">Email *</label>
              <input type="email" class="form-control form-control-sm" name="email" id="s_email" required>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-semibold text-secondary mb-1">Phone</label>
              <input class="form-control form-control-sm" name="phone" id="s_phone">
            </div>
            <div class="col-md-8">
              <label class="form-label small fw-semibold text-secondary mb-1">Organization</label>
              <input class="form-control form-control-sm" name="organization" id="s_org">
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-semibold text-secondary mb-1">Category</label>
              <select class="form-select form-select-sm" name="category_id" id="s_category">
                <option value="">Uncategorized</option>
                <?php foreach($categories as $c): ?>
                  <option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-semibold text-secondary mb-1">Sector</label>
              <input class="form-control form-control-sm" name="sector" id="s_sector">
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-semibold text-secondary mb-1">Status</label>
              <select class="form-select form-select-sm" name="status" id="s_status">
                <option>Pending</option>
                <option>Verified</option>
                <option>Inactive</option>
                <option>Rejected</option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label small fw-semibold text-secondary mb-1">Address</label>
              <textarea class="form-control form-control-sm" name="address" id="s_address" rows="2"></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer py-2 px-3.5 bg-light border-top d-flex justify-content-end gap-2">
          <button class="btn btn-sm btn-outline-secondary px-3" type="button" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-sm btn-primary px-3 fw-semibold shadow-sm" type="submit">Save Stakeholder</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded',function(){
 const modal=new bootstrap.Modal(document.getElementById('stakeholderModal'));
 const form=document.getElementById('stakeholderForm');
 document.getElementById('btnNewStakeholder').onclick=function(){form.reset();s_id.value='0';s_status.value='Pending';modal.show();};
 document.querySelectorAll('.btn-edit-stakeholder').forEach(btn=>btn.onclick=function(){
   const r=JSON.parse(this.dataset.row);
   s_id.value=r.id||0;s_name.value=r.full_name||'';s_email.value=r.email||'';s_phone.value=r.phone||'';
   s_org.value=r.organization||'';s_category.value=r.category_id||'';s_sector.value=r.sector||'';
   s_status.value=r.status||'Pending';s_address.value=r.address||'';modal.show();
 });
  form.onsubmit=async function(e){
    e.preventDefault();
    const isNew = (!s_id.value || s_id.value === '0');
    const res=await appPostForm(APP_URL+'/modules/stakeholders/ajax_stakeholder_save.php',form);
    if(res.success){
      modal.hide();
      if(isNew && res.qr_url){
        Swal.fire({
          icon: 'success',
          title: 'Stakeholder & QR Pass Created!',
          html: '<p>' + res.message + '</p>' +
                '<div class="p-3 my-2 bg-light rounded border text-center">' +
                '<div class="text-muted small mb-1">Generated Attendance QR Code:</div>' +
                '<span class="badge bg-dark font-monospace fs-6 px-3 py-2"><i class="bi bi-qr-code me-1"></i> ' + (res.code_value || '') + '</span>' +
                '</div>' +
                '<p class="text-muted small">This QR code can be scanned on hearing attendance for instant Time In and Time Out.</p>',
          showCancelButton: true,
          confirmButtonColor: '#1e4a7a',
          confirmButtonText: '<i class="bi bi-qr-code-scan me-1"></i> View & Print QR Pass',
          cancelButtonText: 'Stay on Registry'
        }).then((result)=>{
          if(result.isConfirmed && res.qr_url){
            window.location.href = res.qr_url;
          } else {
            location.reload();
          }
        });
      } else {
        appToast('success',res.message);
        setTimeout(()=>location.reload(),400);
      }
    }else if(!res.session_expired){
      Swal.fire('Unable to Save',res.message,'error');
    }
  };
});
</script>
<?php include __DIR__ . '/../../layouts/footer.php'; ?>
