<?php
/**
 * Required variables:
 *
 * $moduleTitle
 * $moduleDescription
 * $moduleIcon
 * $moduleFeatures
 */

if (!isset(
    $moduleTitle,
    $moduleDescription,
    $moduleIcon,
    $moduleFeatures
)) {
    throw new RuntimeException(
        'Missing module placeholder configuration.'
    );
}
?>

<div class="app-wrapper">
    <?php include __DIR__ . '/../layouts/sidebar.php'; ?>

    <div class="main-content">
        <div class="breadcrumb-bar">
            <h5 class="mb-1">
                <i class="bi <?= e($moduleIcon) ?>"></i>
                <?= e($moduleTitle) ?>
            </h5>

            <small class="text-muted">
                <?= e($moduleDescription) ?>
            </small>
        </div>

        <div class="card">
            <div class="card-header">
                Module Functions
            </div>

            <div class="card-body">
                <div class="row g-3">
                    <?php foreach ($moduleFeatures as $feature): ?>
                        <div class="col-md-6 col-xl-4">
                            <div class="module-feature-card">
                                <i class="bi bi-check-circle"></i>
                                <span><?= e($feature) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="alert alert-warning mt-4 mb-0">
                    <i class="bi bi-tools"></i>
                    This module is currently under development.
                </div>
            </div>
        </div>
    </div>
</div>