<?php
declare(strict_types=1);

require_once __DIR__.'/../includes/auth.php';
requireLogin();

$pdo=db();
$stmt=$pdo->prepare(
 'SELECT u.id,u.username,u.full_name,u.email,u.phone,u.status,u.created_at,
         u.last_login_at,r.name role_name,o.name office_name,d.name department_name,
         COALESCE(usa.status,"Legacy") orlms_access,
         COALESCE(usa.access_level,"Legacy / Inherited") access_level
  FROM users u
  JOIN roles r ON r.id=u.role_id
  LEFT JOIN offices o ON o.id=u.office_id
  LEFT JOIN departments d ON d.id=u.department_id
  LEFT JOIN user_system_access usa
    ON usa.user_id=u.id AND usa.system_id=:system
  WHERE u.id=:id'
);
$stmt->execute([':system'=>orlmsSystemId(),':id'=>currentUserId()]);
$profile=$stmt->fetch();
if(!$profile){redirect(APP_URL.'/logout.php');}

$pageTitle='My Profile';$activeMenu='';
$extraCss=[APP_URL.'/assets/css/orlms-final-ops.css'];
include __DIR__.'/../layouts/header.php';
?>
<div class="orlms-app-layout"><?php include __DIR__.'/../layouts/sidebar.php'; ?><main class="orlms-main-content">
<div class="of-head"><div><div class="of-eyebrow"><i class="bi bi-person-circle"></i> Shared Legislative Account</div><h1>My Profile</h1><p>Review your shared account details, ORLMS access and security information.</p></div></div>

<div class="row g-3">
<div class="col-xl-7"><div class="card of-card"><div class="card-header">Account Details</div><div class="card-body"><div class="of-grid">
<div><small>Full Name</small><strong><?= e($profile['full_name']) ?></strong></div>
<div><small>Email</small><strong><?= e($profile['email']) ?></strong></div>
<div><small>Username</small><strong><?= e($profile['username']?:'—') ?></strong></div>
<div><small>Phone</small><strong><?= e($profile['phone']?:'—') ?></strong></div>
<div><small>Role</small><strong><?= e($profile['role_name']) ?></strong></div>
<div><small>Office</small><strong><?= e($profile['office_name']?:'—') ?></strong></div>
<div><small>Department</small><strong><?= e($profile['department_name']?:'—') ?></strong></div>
<div><small>Account Status</small><strong><?= e($profile['status']) ?></strong></div>
<div><small>ORLMS Access</small><strong><?= e($profile['orlms_access']) ?></strong></div>
<div><small>ORLMS Access Level</small><strong><?= e($profile['access_level']) ?></strong></div>
<div><small>Last Login</small><strong><?= $profile['last_login_at']?formatDateTime($profile['last_login_at']):'Never' ?></strong></div>
<div><small>Account Created</small><strong><?= formatDateTime($profile['created_at']) ?></strong></div>
</div></div></div></div>

<div class="col-xl-5"><div class="card of-card"><div class="card-header">Change Password</div><div class="card-body">
<form action="<?= e(APP_URL.'/auth/process_change_password.php') ?>" method="post"><?= csrfField() ?>
<label class="form-label">Current Password</label><input type="password" class="form-control mb-3" name="current_password" required autocomplete="current-password">
<label class="form-label">New Password</label><input type="password" class="form-control mb-3" name="new_password" required autocomplete="new-password">
<label class="form-label">Confirm New Password</label><input type="password" class="form-control mb-3" name="confirm_password" required autocomplete="new-password">
<div class="form-text mb-3">At least 10 characters with uppercase, lowercase and a number.</div>
<button class="btn btn-primary w-100"><i class="bi bi-key"></i> Change Password</button>
</form>
</div></div></div>
</div>
</main></div>
<?php include __DIR__.'/../layouts/footer.php'; ?>
