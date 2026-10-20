<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
requirePermission('vqd.users.manage');

$pdo=db();$pageTitle='User Management';$activeMenu='users';
$extraCss=[appUrl('assets/css/vqdss-admin-final.css')];

$search=clean($_GET['search']??'');
$roleFilter=(int)($_GET['role_id']??0);
$statusFilter=clean($_GET['status']??'');
$accessFilter=clean($_GET['access']??'');

$where=['u.deleted_at IS NULL'];
$params=[':system'=>vqdSystemId()];

if($search!==''){
    $where[]='(u.full_name LIKE :s1 OR u.email LIKE :s2 OR u.username LIKE :s3)';
    $like='%'.$search.'%';$params[':s1']=$like;$params[':s2']=$like;$params[':s3']=$like;
}
if($roleFilter>0){$where[]='u.role_id=:role';$params[':role']=$roleFilter;}
if($statusFilter!==''){$where[]='u.status=:status';$params[':status']=$statusFilter;}
if($accessFilter!==''){$where[]="COALESCE(usa.status,'Legacy')=:access";$params[':access']=$accessFilter;}

$stmt=$pdo->prepare(
 "SELECT u.id,u.username,u.full_name,u.email,u.phone,u.role_id,u.office_id,
         u.status,u.created_at,u.last_login_at,u.updated_at,
         r.name role_name,o.name office_name,
         COALESCE(usa.status,'Legacy') vqd_access_status,
         COALESCE(usa.access_level,'Legacy / Inherited') vqd_access_level,
         (SELECT COUNT(*)
            FROM role_permissions rp
            JOIN permissions p ON p.id=rp.permission_id
           WHERE rp.role_id=u.role_id AND p.code LIKE 'vqd.%') vqd_permission_count
  FROM users u
  JOIN roles r ON r.id=u.role_id
  LEFT JOIN offices o ON o.id=u.office_id
  LEFT JOIN user_system_access usa ON usa.user_id=u.id AND usa.system_id=:system
  WHERE ".implode(' AND ',$where)."
  ORDER BY u.full_name,u.id"
);
$stmt->execute($params);$users=$stmt->fetchAll();

$roles=$pdo->query('SELECT id,name FROM roles ORDER BY id')->fetchAll();
$offices=$pdo->query("SELECT id,name FROM offices WHERE status='Active' ORDER BY name")->fetchAll();

$sum=$pdo->prepare(
 "SELECT COUNT(*) total,SUM(u.status='Active') active_users,
         SUM(COALESCE(usa.status,'Legacy')='Active') explicit_access,
         SUM(COALESCE(usa.status,'Legacy')='Inactive') denied_access
  FROM users u
  LEFT JOIN user_system_access usa ON usa.user_id=u.id AND usa.system_id=:system
  WHERE u.deleted_at IS NULL"
);
$sum->execute([':system'=>vqdSystemId()]);$summary=$sum->fetch();

include __DIR__.'/../layouts/header.php';
?>
<div class="app-wrapper"><?php include __DIR__.'/../layouts/sidebar.php'; ?><main class="main-content">

<div class="vqda-head"><div><div class="vqda-eyebrow"><i class="bi bi-person-gear"></i> Step 8 · Administration</div><h1>User Management</h1><p>Manage shared legislative accounts while explicitly controlling VQDSS access, role, office, account status and role-derived voting permissions.</p></div><button class="btn btn-primary" id="btnNewUser"><i class="bi bi-person-plus"></i> New User</button></div>

<div class="row g-3 mb-3">
<?php foreach([
 ['Users',$summary['total']??0,'bi-people'],['Active Accounts',$summary['active_users']??0,'bi-person-check'],
 ['Explicit VQDSS Access',$summary['explicit_access']??0,'bi-shield-check'],['Access Denied',$summary['denied_access']??0,'bi-shield-x'],
] as [$l,$v,$i]): ?><div class="col-6 col-lg-3"><div class="vqda-stat"><i class="bi <?= e($i) ?>"></i><div><strong><?= (int)$v ?></strong><small><?= e($l) ?></small></div></div></div><?php endforeach; ?>
</div>

<div class="card vqda-card mb-3"><div class="card-body"><form class="row g-2 align-items-end">
<div class="col-lg-4"><label class="form-label small">Search</label><input class="form-control form-control-sm" name="search" value="<?= e($search) ?>" placeholder="Name, email or username"></div>
<div class="col-lg-2"><label class="form-label small">Role</label><select class="form-select form-select-sm" name="role_id"><option value="">All roles</option><?php foreach($roles as $r): ?><option value="<?= (int)$r['id'] ?>" <?= $roleFilter===(int)$r['id']?'selected':'' ?>><?= e($r['name']) ?></option><?php endforeach; ?></select></div>
<div class="col-lg-2"><label class="form-label small">Account</label><select class="form-select form-select-sm" name="status"><option value="">All</option><option <?= $statusFilter==='Active'?'selected':'' ?>>Active</option><option <?= $statusFilter==='Inactive'?'selected':'' ?>>Inactive</option></select></div>
<div class="col-lg-2"><label class="form-label small">VQDSS Access</label><select class="form-select form-select-sm" name="access"><option value="">All</option><option <?= $accessFilter==='Active'?'selected':'' ?>>Active</option><option <?= $accessFilter==='Inactive'?'selected':'' ?>>Inactive</option><option <?= $accessFilter==='Legacy'?'selected':'' ?>>Legacy</option></select></div>
<div class="col-lg-2"><button class="btn btn-outline-primary btn-sm w-100"><i class="bi bi-search"></i> Filter</button></div>
</form></div></div>

<div class="card vqda-card"><div class="card-header d-flex justify-content-between"><span>User Registry</span><span><?= count($users) ?> result(s)</span></div><div class="table-responsive"><table class="table table-hover vqda-table mb-0"><thead><tr><th>User</th><th>Role / Office</th><th>Account</th><th>VQDSS Access</th><th>Permissions</th><th>Last Login</th><th class="text-end">Actions</th></tr></thead><tbody>
<?php if(!$users): ?><tr><td colspan="7" class="text-center text-muted py-5">No users match the filters.</td></tr><?php endif; ?>
<?php foreach($users as $u): ?><tr>
<td><strong><?= e($u['full_name']) ?></strong><?php if((int)$u['id']===(int)currentUserId()): ?> <span class="badge text-bg-warning">You</span><?php endif; ?><div class="small text-muted"><?= e($u['email']) ?><?= $u['username']?' · @'.e($u['username']):'' ?></div></td>
<td><?= e($u['role_name']) ?><div class="small text-muted"><?= e($u['office_name']?:'No office') ?></div></td>
<td><span class="badge text-bg-<?= $u['status']==='Active'?'success':'secondary' ?>"><?= e($u['status']) ?></span></td>
<td><span class="vqda-access <?= e(strtolower($u['vqd_access_status'])) ?>"><i class="bi bi-shield-check"></i><?= e($u['vqd_access_status']) ?></span><div class="small text-muted mt-1"><?= e($u['vqd_access_level']) ?></div></td>
<td><strong><?= (int)$u['vqd_permission_count'] ?></strong><div class="small text-muted">role permission(s)</div></td>
<td><?= $u['last_login_at']?formatDateTime($u['last_login_at']):'Never' ?></td>
<td class="text-end"><div class="btn-group btn-group-sm"><button class="btn btn-outline-primary edit-user" data-id="<?= (int)$u['id'] ?>"><i class="bi bi-pencil"></i></button><button class="btn btn-outline-danger deactivate-user" data-id="<?= (int)$u['id'] ?>" data-name="<?= e($u['full_name']) ?>"><i class="bi bi-person-x"></i></button></div></td>
</tr><?php endforeach; ?>
</tbody></table></div></div>
</main></div>

<div class="modal fade" id="userModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width: 580px;">
    <div class="modal-content border-0 shadow-lg" style="border-radius: 14px; overflow: hidden;">
      <form id="userForm">
        <?= csrfField() ?>
        <input type="hidden" name="id" id="u_id" value="0">
        
        <!-- Pro Header -->
        <div class="modal-header py-2.5 px-3.5" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); border-bottom: 2px solid #3b82f6;">
          <div class="d-flex align-items-center gap-2">
            <div class="rounded-circle d-flex align-items-center justify-content-center bg-primary bg-opacity-25 text-info" style="width: 32px; height: 32px;">
              <i class="bi bi-person-gear fs-6"></i>
            </div>
            <div>
              <h6 class="modal-title text-white fw-bold mb-0" id="modalUserTitle" style="font-size: 0.95rem;">VQDSS User Account</h6>
              <small class="text-white-50" style="font-size: 0.72rem;">Configure legislative user identity, role, and system access</small>
            </div>
          </div>
          <button class="btn-close btn-close-white" type="button" data-bs-dismiss="modal"></button>
        </div>

        <!-- Pro Compact Body -->
        <div class="modal-body p-3.5 bg-white">
          <!-- Section 1: User Identity -->
          <div class="d-flex align-items-center gap-2 mb-2 pb-1 border-bottom">
            <span class="badge bg-secondary-subtle text-secondary" style="font-size: 0.68rem; letter-spacing: 0.5px;">ACCOUNT IDENTITY</span>
          </div>
          <div class="row g-2 mb-3">
            <div class="col-md-6">
              <label class="form-label small fw-semibold text-secondary mb-1" style="font-size: 0.76rem;">Full Name *</label>
              <input class="form-control form-control-sm" name="full_name" id="u_name" placeholder="Juan Dela Cruz" required>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold text-secondary mb-1" style="font-size: 0.76rem;">Username</label>
              <input class="form-control form-control-sm" name="username" id="u_username" placeholder="jdelacruz">
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold text-secondary mb-1" style="font-size: 0.76rem;">Email *</label>
              <input type="email" class="form-control form-control-sm" name="email" id="u_email" placeholder="user@manila.gov.ph" required>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold text-secondary mb-1" style="font-size: 0.76rem;">Phone</label>
              <input class="form-control form-control-sm" name="phone" id="u_phone" placeholder="0917-000-0000">
            </div>
          </div>

          <!-- Section 2: Role & Subsystem Access -->
          <div class="d-flex align-items-center gap-2 mb-2 pb-1 border-bottom">
            <span class="badge bg-primary-subtle text-primary" style="font-size: 0.68rem; letter-spacing: 0.5px;">ROLE & ACCESS PERMISSIONS</span>
          </div>
          <div class="row g-2 mb-3">
            <div class="col-md-6">
              <label class="form-label small fw-semibold text-secondary mb-1" style="font-size: 0.76rem;">Role *</label>
              <select class="form-select form-select-sm" name="role_id" id="u_role" required>
                <?php foreach($roles as $r): ?>
                  <option value="<?= (int)$r['id'] ?>"><?= e($r['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold text-secondary mb-1" style="font-size: 0.76rem;">Office</label>
              <select class="form-select form-select-sm" name="office_id" id="u_office">
                <option value="">No office</option>
                <?php foreach($offices as $o): ?>
                  <option value="<?= (int)$o['id'] ?>"><?= e($o['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-semibold text-secondary mb-1" style="font-size: 0.76rem;">Account Status</label>
              <select class="form-select form-select-sm" name="status" id="u_status">
                <option>Active</option>
                <option>Inactive</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-semibold text-secondary mb-1" style="font-size: 0.76rem;">VQDSS Access</label>
              <select class="form-select form-select-sm" name="vqd_access_status" id="u_access">
                <option>Active</option>
                <option>Inactive</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-semibold text-secondary mb-1" style="font-size: 0.76rem;">Access Level</label>
              <select class="form-select form-select-sm" name="access_level" id="u_level">
                <option>Administrator</option>
                <option>Staff</option>
                <option>Committee</option>
                <option>Stakeholder</option>
                <option>Standard</option>
              </select>
            </div>
          </div>

          <!-- Section 3: Password -->
          <div class="p-2.5 rounded-3 bg-light border">
            <div class="d-flex justify-content-between align-items-center mb-1">
              <label class="form-label small fw-semibold text-secondary mb-0" style="font-size: 0.76rem;">Password</label>
              <span class="text-muted" id="passwordHelpText" style="font-size: 0.7rem;">Leave blank to keep current password</span>
            </div>
            <input type="password" class="form-control form-control-sm" name="password" id="u_password" autocomplete="new-password" placeholder="Enter password (min. 10 chars)">
            <div class="text-muted mt-1" style="font-size: 0.68rem;">Must contain at least 10 characters with uppercase, lowercase, and a number.</div>
          </div>
        </div>

        <!-- Pro Footer -->
        <div class="modal-footer py-2 px-3.5 bg-light border-top d-flex justify-content-end gap-2">
          <button class="btn btn-sm btn-outline-secondary px-3" type="button" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-sm btn-primary px-3 fw-semibold shadow-sm" id="btnSaveUser">
            <i class="bi bi-check2-circle me-1"></i> Save User
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded',function(){
 const modalEl = document.getElementById('userModal');
 const modal = new bootstrap.Modal(modalEl);
 const userForm = document.getElementById('userForm');
 const btnSaveUser = document.getElementById('btnSaveUser');
 const modalUserTitle = document.getElementById('modalUserTitle');
 const passwordHelpText = document.getElementById('passwordHelpText');

 function resetUser(){
   userForm.reset();
   u_id.value = '0';
   u_status.value = 'Active';
   u_access.value = 'Active';
   u_level.value = 'Standard';
   u_password.value = '';
   u_password.required = true;
   if(modalUserTitle) modalUserTitle.textContent = 'Create VQDSS User Account';
   if(passwordHelpText) {
     passwordHelpText.textContent = 'Required for new user (min. 10 chars)';
     passwordHelpText.className = 'badge bg-warning-subtle text-warning-emphasis';
   }
   btnSaveUser.innerHTML = '<i class="bi bi-person-plus me-1"></i> Create User';
 }

 const btnNew = document.getElementById('btnNewUser');
 if(btnNew) btnNew.onclick = () => { resetUser(); modal.show(); };

 document.querySelectorAll('.edit-user').forEach(b => b.onclick = async function(){
   const id = this.dataset.id;
   try {
     const r = await fetch('ajax_user_get.php?id=' + encodeURIComponent(id), {
       headers: {'X-Requested-With': 'XMLHttpRequest'}
     }).then(x => x.json());

     if(!r.success) {
       Swal.fire('User Error', r.message, 'error');
       return;
     }

     const u = r.user;
     u_id.value = u.id;
     u_name.value = u.full_name || '';
     u_username.value = u.username || '';
     u_email.value = u.email || '';
     u_phone.value = u.phone || '';
     u_role.value = u.role_id || '';
     u_office.value = u.office_id || '';
     u_status.value = u.status || 'Active';
     u_access.value = u.vqd_access_status || 'Active';
     u_level.value = u.access_level || 'Standard';
     u_password.value = '';
     u_password.required = false;

     if(modalUserTitle) modalUserTitle.textContent = 'Edit VQDSS User Account';
     if(passwordHelpText) {
       passwordHelpText.textContent = 'Leave blank to keep current password';
       passwordHelpText.className = 'text-muted';
     }
     btnSaveUser.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Update User';

     modal.show();
   } catch(err) {
     Swal.fire('Error', err.message || 'Failed to load user details.', 'error');
   }
 });

 userForm.onsubmit = async function(e){
   e.preventDefault();

   const pw = u_password.value.trim();
   if (u_id.value === '0' && !pw) {
     Swal.fire('Password Required', 'Password is required when creating a new user.', 'warning');
     u_password.focus();
     return;
   }
   if (pw !== '') {
     if (pw.length < 10) {
       Swal.fire('Password Length', 'Password must be at least 10 characters long.', 'warning');
       u_password.focus();
       return;
     }
     if (!/[A-Z]/.test(pw) || !/[a-z]/.test(pw) || !/[0-9]/.test(pw)) {
       Swal.fire('Password Complexity', 'Password must include uppercase, lowercase, and a number.', 'warning');
       u_password.focus();
       return;
     }
   }

   const origBtnHtml = btnSaveUser.innerHTML;
   btnSaveUser.disabled = true;
   btnSaveUser.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Saving...';

   try {
     const res = await fetch('ajax_user_save.php', {
       method: 'POST',
       headers: {'X-Requested-With': 'XMLHttpRequest'},
       body: new FormData(userForm)
     });

     const r = await res.json();
     btnSaveUser.disabled = false;
     btnSaveUser.innerHTML = origBtnHtml;

     if (r.success) {
       await Swal.fire({
         icon: 'success',
         title: 'User Saved',
         text: r.message,
         timer: 1500,
         showConfirmButton: false
       });
       location.reload();
     } else {
       Swal.fire('User Error', r.message, 'error');
     }
   } catch(err) {
     btnSaveUser.disabled = false;
     btnSaveUser.innerHTML = origBtnHtml;
     Swal.fire('Save Error', err.message || 'An unexpected error occurred while communicating with the server.', 'error');
   }
 };

 document.querySelectorAll('.deactivate-user').forEach(b => b.onclick = async function(){
   const ask = await Swal.fire({
     title: 'Deactivate user?',
     text: 'The account and VQDSS access will be disabled while historical records remain intact.',
     icon: 'warning',
     showCancelButton: true,
     confirmButtonText: 'Deactivate'
   });
   if(!ask.isConfirmed) return;
   const fd = new FormData();
   fd.append('csrf_token', '<?= e(csrfToken()) ?>');
   fd.append('id', this.dataset.id);
   try {
     const r = await fetch('ajax_user_deactivate.php', {
       method: 'POST',
       headers: {'X-Requested-With': 'XMLHttpRequest'},
       body: fd
     }).then(x => x.json());
     if(r.success) location.reload();
     else Swal.fire('User Error', r.message, 'error');
   } catch(err) {
     Swal.fire('Error', err.message || 'Failed to deactivate user.', 'error');
   }
 });
});
</script>
<?php include __DIR__.'/../layouts/footer.php'; ?>
