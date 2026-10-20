<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/auth.php';require_once __DIR__.'/../includes/lacms_security.php';requireLacmsPermission('lacms.users.manage');
$pdo=db();$pageTitle='User Management';$activeMenu='users';$extraCss=[appUrl('assets/css/administration.css'),appUrl('assets/css/lacms-operational.css')];$systemId=lacmsSystemId();
$search=clean($_GET['search']??'');$role=(int)($_GET['role_id']??0);$access=clean($_GET['access_status']??'');$where=['u.deleted_at IS NULL'];$params=[':system'=>$systemId];if($search!==''){$where[]='(u.full_name LIKE :s1 OR u.email LIKE :s2 OR u.username LIKE :s3)';$like='%'.$search.'%';$params[':s1']=$like;$params[':s2']=$like;$params[':s3']=$like;}if($role){$where[]='u.role_id=:role';$params[':role']=$role;}if($access!==''){$where[]='COALESCE(usa.status,"Inactive")=:access';$params[':access']=$access;}
$q=$pdo->prepare('SELECT u.id,u.username,u.full_name,u.email,u.phone,u.role_id,u.office_id,u.department_id,u.status,u.created_at,u.last_login_at,r.name role_name,o.name office_name,d.name department_name,COALESCE(usa.status,"Inactive") lacms_access_status,COALESCE(usa.access_level,"Standard") access_level,(SELECT COUNT(*) FROM role_permissions rp JOIN permissions p ON p.id=rp.permission_id WHERE rp.role_id=u.role_id AND p.system_id=:system) permission_count FROM users u LEFT JOIN roles r ON r.id=u.role_id LEFT JOIN offices o ON o.id=u.office_id LEFT JOIN departments d ON d.id=u.department_id LEFT JOIN user_system_access usa ON usa.user_id=u.id AND usa.system_id=:system_access WHERE '.implode(' AND ',$where).' ORDER BY u.full_name');
$params[':system_access']=$systemId;$q->execute($params);$rows=$q->fetchAll();$roles=$pdo->query('SELECT id,name FROM roles ORDER BY id')->fetchAll();$offices=$pdo->query("SELECT id,name FROM offices WHERE status='Active' ORDER BY name")->fetchAll();$departments=$pdo->query("SELECT id,name FROM departments ORDER BY name")->fetchAll();
$totals=['total'=>0,'active'=>0,'access'=>0,'admins'=>0];foreach($rows as $u){$totals['total']++;if($u['status']==='Active')$totals['active']++;if($u['lacms_access_status']==='Active')$totals['access']++;if($u['role_name']==='Administrator')$totals['admins']++;}
include __DIR__.'/../layouts/header.php';
?>
<div class="lacms-app-wrapper"><?php include __DIR__.'/../layouts/sidebar.php'; ?><main class="lacms-main-content"><div class="lo-head"><div><div class="lo-eyebrow"><i class="bi bi-person-gear"></i> Step 9 · Shared Administration</div><h1>Shared User Management</h1><p>Manage the shared legislative user record and explicit LACMS subsystem access without creating a duplicate LACMS-only user table.</p></div><div class="d-flex gap-2"><a target="_blank" class="btn btn-outline-secondary" href="users_print.php"><i class="bi bi-printer"></i> Print</a><button class="btn btn-primary" id="btnNewUser"><i class="bi bi-person-plus"></i> New User</button></div></div>
<div class="row g-3 mb-3"><?php foreach([[$totals['total'],'Listed Users','bi-people'],[$totals['active'],'Active Accounts','bi-person-check'],[$totals['access'],'Active LACMS Access','bi-shield-check'],[$totals['admins'],'Administrators','bi-shield-lock']] as [$v,$l,$i]): ?><div class="col-md-3"><div class="lo-stat"><i class="bi <?= e($i) ?>"></i><div><strong><?= $v ?></strong><small><?= e($l) ?></small></div></div></div><?php endforeach; ?></div>
<div class="card lo-card mb-3"><div class="card-body"><form class="row g-2 align-items-end"><div class="col-md-5"><label class="form-label small">Search</label><input class="form-control form-control-sm" name="search" value="<?= e($search) ?>" placeholder="Name, email, username"></div><div class="col-md-3"><label class="form-label small">Role</label><select class="form-select form-select-sm" name="role_id"><option value="">All</option><?php foreach($roles as $r): ?><option value="<?= (int)$r['id'] ?>" <?= $role===(int)$r['id']?'selected':'' ?>><?= e($r['name']) ?></option><?php endforeach; ?></select></div><div class="col-md-2"><label class="form-label small">LACMS Access</label><select class="form-select form-select-sm" name="access_status"><option value="">All</option><option <?= $access==='Active'?'selected':'' ?>>Active</option><option <?= $access==='Inactive'?'selected':'' ?>>Inactive</option></select></div><div class="col-md-2"><button class="btn btn-outline-primary btn-sm w-100">Apply</button></div></form></div></div>
<div class="card lo-card"><div class="table-responsive"><table class="table table-hover lo-table mb-0"><thead><tr><th>User</th><th>Role</th><th>Office / Department</th><th>Account</th><th>LACMS Access</th><th>Permissions</th><th>Last Login</th><th></th></tr></thead><tbody><?php foreach($rows as $u): ?><tr><td><strong><?= e($u['full_name']) ?></strong><div class="small text-muted"><?= e($u['email']) ?><?= $u['username']?' · @'.e($u['username']):'' ?></div></td><td><?= e($u['role_name']?:'—') ?></td><td><?= e($u['office_name']?:'—') ?><div class="small text-muted"><?= e($u['department_name']?:'') ?></div></td><td><span class="lo-status <?= $u['status']==='Active'?'good':'bad' ?>"><?= e($u['status']) ?></span></td><td><span class="lo-status <?= $u['lacms_access_status']==='Active'?'good':'bad' ?>"><?= e($u['lacms_access_status']) ?></span><div class="small text-muted"><?= e($u['access_level']) ?></div></td><td><?= (int)$u['permission_count'] ?></td><td><?= $u['last_login_at']?formatDateTime($u['last_login_at']):'Never' ?></td><td><div class="btn-group btn-group-sm"><button class="btn btn-outline-primary edit-user" data-id="<?= (int)$u['id'] ?>">Edit</button><?php if($u['lacms_access_status']==='Active'): ?><button class="btn btn-outline-warning access-action" data-id="<?= (int)$u['id'] ?>" data-mode="revoke_access">Revoke</button><?php else: ?><button class="btn btn-outline-success access-action" data-id="<?= (int)$u['id'] ?>" data-mode="grant_access">Grant</button><?php endif; ?></div></td></tr><?php endforeach; ?></tbody></table></div></div></main></div>
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
              <h6 class="modal-title text-white fw-bold mb-0" style="font-size: 0.95rem;">Shared User & LACMS Access</h6>
              <small class="text-white-50" style="font-size: 0.72rem;">Configure legislative user identity and LACMS subsystem access</small>
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
            <div class="col-md-7">
              <label class="form-label small fw-semibold text-secondary mb-1" style="font-size: 0.76rem;">Full Name *</label>
              <input class="form-control form-control-sm" name="full_name" id="u_name" placeholder="Juan Dela Cruz" required>
            </div>
            <div class="col-md-5">
              <label class="form-label small fw-semibold text-secondary mb-1" style="font-size: 0.76rem;">Username</label>
              <input class="form-control form-control-sm" name="username" id="u_username" placeholder="jdelacruz">
            </div>
            <div class="col-md-7">
              <label class="form-label small fw-semibold text-secondary mb-1" style="font-size: 0.76rem;">Email *</label>
              <input type="email" class="form-control form-control-sm" name="email" id="u_email" placeholder="user@manila.gov.ph" required>
            </div>
            <div class="col-md-5">
              <label class="form-label small fw-semibold text-secondary mb-1" style="font-size: 0.76rem;">Phone</label>
              <input class="form-control form-control-sm" name="phone" id="u_phone" placeholder="0917-000-0000">
            </div>
          </div>

          <!-- Section 2: Role & Subsystem Access -->
          <div class="d-flex align-items-center gap-2 mb-2 pb-1 border-bottom">
            <span class="badge bg-primary-subtle text-primary" style="font-size: 0.68rem; letter-spacing: 0.5px;">ROLE & LACMS ACCESS</span>
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
                <option value="">None</option>
                <?php foreach($offices as $o): ?>
                  <option value="<?= (int)$o['id'] ?>"><?= e($o['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold text-secondary mb-1" style="font-size: 0.76rem;">Department</label>
              <select class="form-select form-select-sm" name="department_id" id="u_department">
                <option value="">None</option>
                <?php foreach($departments as $d): ?>
                  <option value="<?= (int)$d['id'] ?>"><?= e($d['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label small fw-semibold text-secondary mb-1" style="font-size: 0.76rem;">Account Status</label>
              <select class="form-select form-select-sm" name="status" id="u_status">
                <option>Active</option>
                <option>Inactive</option>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label small fw-semibold text-secondary mb-1" style="font-size: 0.76rem;">LACMS Access</label>
              <select class="form-select form-select-sm" name="lacms_access_status" id="u_access">
                <option>Active</option>
                <option>Inactive</option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label small fw-semibold text-secondary mb-1" style="font-size: 0.76rem;">Access Level</label>
              <select class="form-select form-select-sm" name="access_level" id="u_level">
                <option>Administrator</option>
                <option>Staff</option>
                <option>Committee</option>
                <option>Standard</option>
                <option>Stakeholder</option>
              </select>
            </div>
          </div>

          <!-- Section 3: Password -->
          <div class="p-2.5 rounded-3 bg-light border">
            <div class="d-flex justify-content-between align-items-center mb-1">
              <label class="form-label small fw-semibold text-secondary mb-0" style="font-size: 0.76rem;">Password</label>
              <span class="text-muted" style="font-size: 0.7rem;">Leave blank on edit to keep existing</span>
            </div>
            <input type="password" class="form-control form-control-sm" name="password" minlength="10" placeholder="Enter password (min. 10 chars)">
          </div>
        </div>

        <!-- Pro Footer -->
        <div class="modal-footer py-2 px-3.5 bg-light border-top d-flex justify-content-end gap-2">
          <button type="button" class="btn btn-sm btn-outline-secondary px-3" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-sm btn-primary px-3 fw-semibold shadow-sm">
            <i class="bi bi-check2-circle me-1"></i> Save Shared User
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
<script>document.addEventListener('DOMContentLoaded',()=>{const f=document.getElementById('userForm'),m=new bootstrap.Modal(document.getElementById('userModal'));document.getElementById('btnNewUser').onclick=()=>{f.reset();u_id.value=0;u_status.value='Active';u_access.value='Active';m.show()};document.querySelectorAll('.edit-user').forEach(b=>b.onclick=async function(){const r=await fetch('ajax_user_get.php?id='+this.dataset.id,{headers:{'X-Requested-With':'XMLHttpRequest'}}).then(x=>x.json());if(!r.success)return Swal.fire('User Error',r.message,'error');const u=r.user;u_id.value=u.id;u_name.value=u.full_name||'';u_username.value=u.username||'';u_email.value=u.email||'';u_phone.value=u.phone||'';u_role.value=u.role_id||'';u_office.value=u.office_id||'';u_department.value=u.department_id||'';u_status.value=u.status||'Active';u_access.value=u.lacms_access_status||'Inactive';u_level.value=u.access_level||'Standard';m.show()});f.onsubmit=async e=>{e.preventDefault();const r=await fetch('ajax_user_save.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:new FormData(f)}).then(x=>x.json());if(r.success)location.reload();else Swal.fire('User Error',r.message,'error')};document.querySelectorAll('.access-action').forEach(b=>b.onclick=async function(){const c=await Swal.fire({title:(this.dataset.mode==='grant_access'?'Grant':'Revoke')+' LACMS access?',showCancelButton:true});if(!c.isConfirmed)return;const fd=new FormData();fd.append('csrf_token','<?= e(csrfToken()) ?>');fd.append('user_id',this.dataset.id);fd.append('mode',this.dataset.mode);const r=await fetch('ajax_user_deactivate.php',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:fd}).then(x=>x.json());if(r.success)location.reload();else Swal.fire('User Error',r.message,'error')})});</script>
<?php include __DIR__.'/../layouts/footer.php'; ?>
