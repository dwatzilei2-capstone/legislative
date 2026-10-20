<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

requireRole([
    ROLE_ADMIN,
    ROLE_STAFF,
    ROLE_COMMITTEE,
]);

$pageTitle = $moduleTitle ?? 'VQDSS Module';
$activeMenu = $moduleKey ?? '';
$extraCss = [
    appUrl('assets/css/dashboard.css'),
];

include __DIR__ . '/../layouts/header.php';
?>

<div class="app-wrapper">
    <?php include __DIR__ . '/../layouts/sidebar.php'; ?>

    <main class="main-content">

        <section class="dashboard-page-header">
            <div>
                <div class="dashboard-eyebrow">
                    <i class="bi <?= e($moduleIcon ?? 'bi-grid') ?>"></i>
                    VQDSS Navigation
                </div>

                <h1><?= e($moduleTitle ?? 'VQDSS Module') ?></h1>

                <p><?= e(
                    $moduleDescription
                    ?? 'This module interface will be built in the next development step.'
                ) ?></p>
            </div>

            <a
                href="<?= e(appUrl('dashboard.php')) ?>"
                class="btn btn-outline-secondary"
            >
                <i class="bi bi-arrow-left"></i>
                Back to Dashboard
            </a>
        </section>

        <section class="placeholder-module-card">
            <span class="placeholder-module-icon">
                <i class="bi <?= e($moduleIcon ?? 'bi-grid') ?>"></i>
            </span>

            <h2><?= e($moduleTitle ?? 'VQDSS Module') ?></h2>

            <p>
                Shared authentication, database access, navigation,
                and the reusable VQDSS layout are already connected.
                The complete client-ready interface for this module
                will replace this placeholder in the next steps.
            </p>

            <div class="placeholder-status-list">
                <span>
                    <i class="bi bi-check-circle"></i>
                    Shared authentication connected
                </span>

                <span>
                    <i class="bi bi-check-circle"></i>
                    Shared legislative database connected
                </span>

                <span>
                    <i class="bi bi-clock"></i>
                    Module interface pending
                </span>
            </div>
        </section>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
