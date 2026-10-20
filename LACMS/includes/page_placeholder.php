<?php
/**
 * Reusable placeholder page for navigation testing.
 *
 * Required variables:
 * $placeholderTitle
 * $placeholderDescription
 * $placeholderIcon
 * $activeMenu
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';

requireLogin();

$placeholderTitle = $placeholderTitle ?? 'Module';
$placeholderDescription = $placeholderDescription
    ?? 'This module will be developed in the next step.';
$placeholderIcon = $placeholderIcon ?? 'bi-window';
$activeMenu = $activeMenu ?? '';

$pageTitle = $placeholderTitle;

$extraCss = [
    appUrl('assets/css/placeholders.css'),
];

include APP_ROOT . '/layouts/header.php';
?>

<div class="app-wrapper">
    <?php include APP_ROOT . '/layouts/sidebar.php'; ?>

    <main class="main-content">
        <section class="placeholder-page-header">
            <div>
                <div class="placeholder-eyebrow">
                    <i class="bi bi-window-stack"></i>
                    LACMS Navigation
                </div>

                <h1><?= e($placeholderTitle) ?></h1>

                <p><?= e($placeholderDescription) ?></p>
            </div>

            <a
                href="<?= e(appUrl('dashboard.php')) ?>"
                class="btn btn-outline-secondary"
            >
                <i class="bi bi-arrow-left"></i>
                Back to Dashboard
            </a>
        </section>

        <section class="placeholder-card">
            <span class="placeholder-icon">
                <i class="bi <?= e($placeholderIcon) ?>"></i>
            </span>

            <h2><?= e($placeholderTitle) ?></h2>

            <p>
                The navigation and reusable layout are already connected.
                The complete client-ready interface for this module will
                replace this placeholder during the next development steps.
            </p>

            <div class="placeholder-status">
                <span>
                    <i class="bi bi-check-circle"></i>
                    Shared authentication connected
                </span>

                <span>
                    <i class="bi bi-check-circle"></i>
                    Sidebar navigation connected
                </span>

                <span>
                    <i class="bi bi-clock"></i>
                    Module interface pending
                </span>
            </div>
        </section>

<?php include APP_ROOT . '/layouts/footer.php'; ?>
