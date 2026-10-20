<?php
declare(strict_types=1);
$pageTitle='Access Denied';
$activeMenu='';
$permissionLabel=$requiredPermission??'required ORLMS permission';
include __DIR__.'/../layouts/header.php';
?>
<div class="orlms-app-layout"><?php include __DIR__.'/../layouts/sidebar.php'; ?><main class="orlms-main-content">
<div class="container-fluid py-5"><div class="card shadow-sm border-0 mx-auto" style="max-width:760px"><div class="card-body p-5 text-center">
<div class="display-5 text-danger mb-3"><i class="bi bi-shield-lock"></i></div>
<h1 class="h3">Access Denied</h1>
<p class="text-muted">Your account is signed in, but it does not have the ORLMS permission required for this page or action.</p>
<div class="alert alert-light border small"><strong>Required permission:</strong> <?= e($permissionLabel) ?></div>
<a class="btn btn-primary" href="<?= e(APP_URL.'/dashboard.php') ?>"><i class="bi bi-house-door"></i> Return to Dashboard</a>
</div></div></div>
</main></div>
<?php include __DIR__.'/../layouts/footer.php'; ?>
