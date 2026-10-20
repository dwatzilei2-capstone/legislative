<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

$pageTitle = $pageTitle ?? 'Dashboard';
$activeMenu = $activeMenu ?? '';
$extraCss = $extraCss ?? [];
$extraJs = $extraJs ?? [];

$portalBaseUrl = rtrim(dirname(defined('APP_URL') ? APP_URL : 'http://localhost/legislative/vqdss'), '/');
$subsystems = [
    ['short' => 'ORLMS', 'name' => 'Ordinance & Resolution Life Cycle', 'url' => $portalBaseUrl . '/ORLMS/', 'icon' => 'bi-file-earmark-text', 'active' => false],
    ['short' => 'SLMMS', 'name' => 'Session & Legislative Meeting', 'url' => $portalBaseUrl . '/subsystem_info.php?code=slmms', 'icon' => 'bi-calendar-event', 'active' => false],
    ['short' => 'LACMS', 'name' => 'Legislative Agenda & Calendar', 'url' => $portalBaseUrl . '/LACMS/', 'icon' => 'bi-calendar3', 'active' => false],
    ['short' => 'CMAS', 'name' => 'Committee Management & Assignment', 'url' => $portalBaseUrl . '/subsystem_info.php?code=cmas', 'icon' => 'bi-diagram-3', 'active' => false],
    ['short' => 'VQDSS', 'name' => 'Voting, Quorum & Decisions', 'url' => $portalBaseUrl . '/vqdss/', 'icon' => 'bi-check2-square', 'active' => true],
    ['short' => 'LRDMS', 'name' => 'Records & Document Management', 'url' => $portalBaseUrl . '/subsystem_info.php?code=lrdms', 'icon' => 'bi-folder-check', 'active' => false],
    ['short' => 'LPH', 'name' => 'Public Hearing & Consultation', 'url' => $portalBaseUrl . '/lph/', 'icon' => 'bi-people', 'active' => false],
    ['short' => 'LAHRS', 'name' => 'Archives & Historical Repository', 'url' => $portalBaseUrl . '/subsystem_info.php?code=lahrs', 'icon' => 'bi-archive', 'active' => false],
    ['short' => 'LRPAIES', 'name' => 'Research, Policy & Impact Evaluation', 'url' => $portalBaseUrl . '/subsystem_info.php?code=lrpaies', 'icon' => 'bi-graph-up-arrow', 'active' => false],
    ['short' => 'CEPFMS', 'name' => 'Citizen Engagement & Feedback', 'url' => $portalBaseUrl . '/CEPFMS/', 'icon' => 'bi-chat-square-heart', 'active' => false],
    ['short' => 'PORTAL', 'name' => 'Legislative Citizen Portal', 'url' => $portalBaseUrl . '/citizen_portal/', 'icon' => 'bi-person-badge', 'active' => false],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>
        <?= e($pageTitle) ?> | <?= e(APP_SHORT_NAME) ?>
    </title>

    <link rel="icon" type="image/png" href="<?= e(appUrl('assets/images/manila.png?v=' . time())) ?>">
    <link rel="shortcut icon" type="image/png" href="<?= e(appUrl('assets/images/manila.png?v=' . time())) ?>">
    <link rel="apple-touch-icon" href="<?= e(appUrl('assets/images/manila.png?v=' . time())) ?>">

    <link
        rel="stylesheet"
        href="<?= e(vendorAsset(
            'bootstrap/bootstrap.min.css',
            'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css'
        )) ?>"
    >

    <link
        rel="stylesheet"
        href="<?= e(vendorAsset(
            'bootstrap-icons/bootstrap-icons.css',
            'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css'
        )) ?>"
    >

    <!-- Google Fonts matching landing page and Subsystem 7 -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@500;600;700;800;900&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <link
        rel="stylesheet"
        href="<?= e(appUrl('assets/css/layout.css?v=' . time())) ?>"
    >
    <link
        rel="stylesheet"
        href="<?= e(appUrl('assets/css/orlms-shell.css?v=' . time())) ?>"
    >

    <?php foreach ($extraCss as $css): ?>
        <link rel="stylesheet" href="<?= e($css) ?><?= (strpos($css, '?') !== false ? '&' : '?') ?>v=<?= time() ?>">
    <?php endforeach; ?>

    <style>
        :root {
            --primary-yellow: #b8860b !important;
            --primary-yellow-light: #b8860b !important;
            --gold: #b8860b !important;
            --bs-warning: #b8860b !important;
            --bs-warning-rgb: 184, 134, 11 !important;
        }
        .text-warning, .vqdx-eyebrow, .vqdf-eyebrow, .vqda-eyebrow,
        .quorum-eyebrow, .voting-eyebrow, .tally-eyebrow, .decision-eyebrow,
        .validation-eyebrow, .reports-eyebrow, .activity-eyebrow, .users-eyebrow,
        .dashboard-eyebrow, .readiness-eyebrow, .orlms-sidebar-link.active,
        .sidebar-nav-link.active, .voting-final-highlight strong,
        .validation-final-highlight strong, .tally-result-highlight strong,
        .tally-final-highlight strong, .decision-source-highlight strong,
        .decision-final-highlight strong, .quorum-result-highlight strong,
        .vqdf-score strong, .vqda-score strong, .readiness-score strong,
        .voting-panel-heading h2 i, .quorum-panel-heading h2 i,
        .tally-panel-heading h2 i, .decision-panel-heading h2 i,
        .reports-panel-heading h2 i, .reports-insight-strip i,
        .insight-icon, .voting-back-link, .quorum-back-link,
        .tally-back-link, .decision-back-link, .validation-back-link {
            color: #b8860b !important;
        }
        .bg-warning, .text-bg-warning, .badge.text-bg-warning, .badge.bg-warning,
        .btn-warning, .workflow-number, .option-number-badge, .roster-order,
        .option-order, .check-order, .finding-order, .action-order,
        .voting-step.active span, .quorum-step.active span, .tally-step.active span,
        .decision-step.active span, .validation-step.active span,
        .users-step.active span, .voting-history-item.current > span,
        .quorum-history-item.current > span, .tally-history-item.current > span,
        .decision-history-item.current > span, .validation-history-item.current > span,
        .users-profile-avatar {
            background-color: #b8860b !important;
            border-color: #b8860b !important;
            color: #ffffff !important;
        }
        .btn-outline-warning {
            color: #b8860b !important;
            border-color: #b8860b !important;
        }
        .btn-outline-warning:hover, .btn-outline-warning:active, .btn-outline-warning:focus {
            background-color: #b8860b !important;
            border-color: #b8860b !important;
            color: #ffffff !important;
        }
        .border-warning, .vqdx-head, .vqdf-head, .vqda-head, .quorum-page-header,
        .voting-page-header, .tally-page-header, .decision-page-header,
        .validation-page-header, .reports-page-header, .activity-page-header,
        .users-page-header, .dashboard-page-header, .readiness-head,
        .vqdx-alert, .vqdf-alert, .vqdx-history>div, .vqdf-history>div,
        .users-matrix-module-row td {
            border-left-color: #b8860b !important;
        }
        .card-header, .vqdx-card>.card-header, .vqdf-card>.card-header,
        .vqda-card>.card-header, .quorum-panel-heading, .voting-panel-heading,
        .tally-panel-heading, .decision-panel-heading, .validation-panel-heading,
        .reports-panel-heading, .activity-panel-heading, .users-panel-heading,
        .dashboard-panel-heading {
            background: #ffffff !important;
            color: #0a1628 !important;
            border-bottom: 2px solid #e2e8f0 !important;
        }
        .quorum-panel-heading h2, .voting-panel-heading h2,
        .tally-panel-heading h2, .decision-panel-heading h2,
        .validation-panel-heading h2, .reports-panel-heading h2,
        .activity-panel-heading h2, .users-panel-heading h2,
        .dashboard-panel-heading h2, .card-header h2, .card-header strong {
            color: #0a1628 !important;
        }
        .quorum-panel-heading p, .voting-panel-heading p,
        .tally-panel-heading p, .decision-panel-heading p,
        .validation-panel-heading p, .reports-panel-heading p,
        .activity-panel-heading p, .users-panel-heading p,
        .dashboard-panel-heading p {
            color: #64748b !important;
        }
        .voting-step.active, .quorum-step.active, .tally-step.active,
        .decision-step.active, .validation-step.active, .users-step.active,
        .dashboard-workflow-card:hover {
            border-color: #b8860b !important;
        }

        /* ============================================================
           PRO ENTERPRISE MODAL DESIGN SYSTEM (Unified Across All Subsystems)
           Ensures modals look sleek, compact, centered, and never overwhelm the screen.
           ============================================================ */
        .modal-dialog {
            max-width: 620px !important;
            margin: 1.75rem auto !important;
        }
        .modal-dialog.modal-sm {
            max-width: 440px !important;
        }
        .modal-dialog.modal-lg,
        .modal-dialog.modal-xl,
        .modal-lg,
        .modal-xl {
            max-width: 680px !important;
        }
        .modal-dialog-centered {
            display: flex !important;
            align-items: center !important;
            min-height: calc(100% - 3.5rem) !important;
        }
        .modal-content {
            border-radius: 14px !important;
            border: 1px solid rgba(0, 0, 0, 0.08) !important;
            box-shadow: 0 20px 45px -10px rgba(15, 23, 42, 0.3), 0 0 0 1px rgba(0, 0, 0, 0.04) !important;
            overflow: hidden !important;
            background: #ffffff !important;
        }
        .modal-header {
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%) !important;
            color: #ffffff !important;
            padding: 0.75rem 1.25rem !important;
            border-bottom: 2px solid #3b82f6 !important;
        }
        .modal-header .modal-title {
            font-size: 0.95rem !important;
            font-weight: 700 !important;
            color: #ffffff !important;
            letter-spacing: 0.2px;
            margin: 0 !important;
        }
        .modal-header small, .modal-header .text-muted, .modal-header p {
            color: rgba(255, 255, 255, 0.7) !important;
            font-size: 0.72rem !important;
            margin: 0 !important;
        }
        .modal-header .btn-close {
            filter: invert(1) grayscale(100%) brightness(200%) !important;
            opacity: 0.8 !important;
            padding: 0.5rem !important;
        }
        .modal-header .btn-close:hover {
            opacity: 1 !important;
        }
        .modal-body {
            padding: 1.15rem 1.25rem !important;
            max-height: calc(85vh - 110px) !important;
            overflow-y: auto !important;
            background: #ffffff !important;
        }
        .modal-footer {
            padding: 0.65rem 1.25rem !important;
            background-color: #f8fafc !important;
            border-top: 1px solid #e2e8f0 !important;
        }
        .modal .form-label {
            font-size: 0.76rem !important;
            font-weight: 600 !important;
            color: #334155 !important;
            margin-bottom: 0.25rem !important;
        }
        .modal .form-control,
        .modal .form-select {
            font-size: 0.82rem !important;
            padding: 0.35rem 0.65rem !important;
            border-radius: 6px !important;
            border: 1px solid #cbd5e1 !important;
        }
        .modal .form-control:focus,
        .modal .form-select:focus {
            border-color: #3b82f6 !important;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15) !important;
        }
        .modal textarea.form-control {
            min-height: 70px !important;
            max-height: 160px !important;
        }
        @media (max-width: 768px) {
            .modal-dialog,
            .modal-dialog.modal-lg,
            .modal-dialog.modal-xl,
            .modal-lg,
            .modal-xl {
                max-width: 95% !important;
                margin: 0.75rem auto !important;
            }
        }

        /* Standard Topbar Menu Button for Mobile/Desktop Toggle */
        .topbar-menu-button {
            display: inline-flex !important;
            align-items: center;
            justify-content: center;
            width: 38px;
            height: 38px;
            margin-right: .75rem;
            color: #071426;
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            cursor: pointer;
        }

        .topbar-menu-button:hover {
            color: #b8860b;
            border-color: #b8860b;
            background: #fffdf5;
        }

        /* Root Variable Overrides for Collapsed Sidebar (74px Icon Mode) */
        :root.sidebar-collapsed,
        html.sidebar-collapsed,
        body.sidebar-collapsed {
            --sidebar-width: 74px !important;
            --side: 74px !important;
        }

        .sidebar-collapsed .sidebar,
        .sidebar-collapsed .orlms-sidebar,
        body.sidebar-collapsed .sidebar,
        body.sidebar-collapsed .orlms-sidebar,
        html.sidebar-collapsed .sidebar,
        html.sidebar-collapsed .orlms-sidebar {
            width: 74px !important;
            min-width: 74px !important;
            max-width: 74px !important;
            overflow: hidden !important;
            transition: width 0.2s ease !important;
        }

        .sidebar-collapsed .topbar,
        body.sidebar-collapsed .topbar,
        html.sidebar-collapsed .topbar {
            left: 74px !important;
            transition: left 0.2s ease !important;
        }

        .sidebar-collapsed .app-wrapper,
        .sidebar-collapsed .orlms-main-content,
        body.sidebar-collapsed .app-wrapper,
        body.sidebar-collapsed .orlms-main-content,
        html.sidebar-collapsed .app-wrapper,
        html.sidebar-collapsed .orlms-main-content {
            padding-left: 74px !important;
            margin-left: 0 !important;
            transition: padding-left 0.2s ease !important;
        }

        .sidebar-collapsed .sidebar-brand,
        .sidebar-collapsed .orlms-sidebar-brand {
            padding: .8rem .2rem !important;
            justify-content: center !important;
            text-align: center !important;
            flex-direction: column !important;
            gap: .4rem !important;
            overflow: hidden !important;
        }

        .sidebar-collapsed .sidebar-logo,
        .sidebar-collapsed .orlms-sidebar-logo {
            width: 38px !important;
            height: 38px !important;
            max-width: 38px !important;
            max-height: 38px !important;
            margin: 0 auto !important;
        }

        /* HIDE ALL TEXT, LABELS, TITLES, SUBTEXT IN COLLAPSED MODE */
        .sidebar-collapsed .sidebar-brand > div,
        .sidebar-collapsed .orlms-sidebar-brand > div,
        .sidebar-collapsed .sidebar-brand strong,
        .sidebar-collapsed .orlms-sidebar-brand strong,
        .sidebar-collapsed .sidebar-brand small,
        .sidebar-collapsed .orlms-sidebar-brand small,
        .sidebar-collapsed .sidebar-section-label,
        .sidebar-collapsed .orlms-sidebar-section,
        .sidebar-collapsed .sidebar-link > span,
        .sidebar-collapsed .orlms-sidebar-link > span,
        .sidebar-collapsed .sidebar-link > em,
        .sidebar-collapsed .orlms-sidebar-link > em,
        .sidebar-collapsed .sidebar-link > b,
        .sidebar-collapsed .orlms-sidebar-link > b,
        .sidebar-collapsed .sidebar-session-card > div,
        .sidebar-collapsed .orlms-sidebar-footer > div {
            display: none !important;
            opacity: 0 !important;
            visibility: hidden !important;
            width: 0 !important;
            height: 0 !important;
            overflow: hidden !important;
        }

        /* KEEP MODULE ICONS CENTERED AND VISIBLE */
        .sidebar-collapsed .sidebar-link,
        .sidebar-collapsed .orlms-sidebar-link {
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            padding: .75rem 0 !important;
            min-height: 48px !important;
            border-radius: 10px !important;
            margin: .2rem .4rem .35rem !important;
            position: relative !important;
            text-align: center !important;
            overflow: hidden !important;
        }

        .sidebar-collapsed .sidebar-link > i,
        .sidebar-collapsed .orlms-sidebar-link > i {
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            font-size: 1.35rem !important;
            width: auto !important;
            margin: 0 auto !important;
            color: rgba(255,255,255,.85) !important;
        }

        .sidebar-collapsed .sidebar-link:hover > i,
        .sidebar-collapsed .sidebar-link.active > i,
        .sidebar-collapsed .orlms-sidebar-link:hover > i,
        .sidebar-collapsed .orlms-sidebar-link.active > i {
            color: #b8860b !important;
        }

        .sidebar-collapsed .sidebar-session-card,
        .sidebar-collapsed .orlms-sidebar-footer {
            justify-content: center !important;
            padding: .7rem 0 !important;
            margin: .65rem .4rem !important;
            overflow: hidden !important;
        }

        .sidebar-collapsed .sidebar-session-card > i,
        .sidebar-collapsed .sidebar-session-card > span,
        .sidebar-collapsed .orlms-sidebar-footer > i {
            font-size: 1.25rem !important;
            margin: 0 !important;
        }

        .sidebar-collapsed .sidebar-collapse-toggle,
        .sidebar-collapsed .orlms-collapse-toggle {
            margin: .2rem auto 0 !important;
        }

    </style>
    <script>
        window.vqdToggleSidebar = function(e) {
            if (e && e.preventDefault) {
                e.preventDefault();
                e.stopPropagation();
            }

            var now = Date.now();
            if (window._vqdLastToggleTime && (now - window._vqdLastToggleTime < 250)) {
                return;
            }
            window._vqdLastToggleTime = now;

            var html = document.documentElement;
            var body = document.body;
            
            var isCollapsed = html.classList.toggle('sidebar-collapsed');
            if (body) {
                body.classList.toggle('sidebar-collapsed', isCollapsed);
            }

            var sidebar = document.getElementById('orlmsSidebar') || document.getElementById('sidebar');
            var backdrop = document.getElementById('orlmsSidebarBackdrop') || document.getElementById('sidebarBackdrop');
            if (window.innerWidth <= 991 && sidebar) {
                sidebar.classList.toggle('open');
                if (backdrop) backdrop.classList.toggle('show');
            }

            try {
                localStorage.setItem('vqdss_sidebar_collapsed', isCollapsed ? '1' : '0');
            } catch(err) {}
        };

        (function() {
            try {
                if (localStorage.getItem('vqdss_sidebar_collapsed') === '1') {
                    document.documentElement.classList.add('sidebar-collapsed');
                    document.addEventListener('DOMContentLoaded', function() {
                        if (document.body) document.body.classList.add('sidebar-collapsed');
                    });
                }
            } catch(e) {}
        })();
    </script>
</head>

<body>

<header class="topbar orlms-topbar">
    <button
        type="button"
        class="topbar-menu-button orlms-menu-button"
        id="sidebarToggle"
        onclick="vqdToggleSidebar(event)"
        title="Toggle Sidebar Navigation"
        aria-label="Toggle navigation"
    >
        <i class="bi bi-list fs-5"></i>
    </button>

    <div class="topbar-actions orlms-topbar-actions">
        <div class="dropdown">
            <button class="orlms-system-switcher dropdown-toggle" type="button" data-bs-toggle="dropdown">
                <span>Subsystems</span>
            </button>
            <div class="dropdown-menu dropdown-menu-end orlms-subsystem-menu shadow-lg" style="max-height: 85vh; overflow-y: auto; z-index: 1060;">
                <a href="<?= e($portalBaseUrl . '/index.php') ?>" class="orlms-subsystem-item orlms-subsystem-item-portal">
                    <div><strong>PORTAL</strong><small>Main Landing Page</small></div>
                </a>
                <?php foreach ($subsystems as $system): ?>
                <a href="<?= e($system['url']) ?>" class="orlms-subsystem-item <?= $system['active'] ? 'active' : '' ?>">
                    <div><strong><?= e($system['short']) ?></strong><small><?= e($system['name']) ?></small></div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="dropdown">
            <button
                class="topbar-user-button orlms-user-button dropdown-toggle"
                type="button"
                data-bs-toggle="dropdown"
                aria-expanded="false"
            >
                <span class="topbar-avatar orlms-avatar">
                    <?= e(strtoupper(substr(
                        currentUserName(),
                        0,
                        1
                    ))) ?>
                </span>

                <span class="topbar-user-copy orlms-user-copy">
                    <strong><?= e(currentUserName()) ?></strong>
                    <small><?= e(currentRoleLabel()) ?></small>
                </span>
            </button>

            <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 mt-2" style="font-size: 0.85rem; z-index: 1060; border-radius: 12px; min-width: 210px;">
                <?php if (currentUserEmail() !== ''): ?>
                <li>
                    <span class="dropdown-item-text small text-muted">
                        <?= e(currentUserEmail()) ?>
                    </span>
                </li>
                <li><hr class="dropdown-divider my-1"></li>
                <?php endif; ?>

                <li>
                    <a
                        class="dropdown-item py-2"
                        href="<?= e(appUrl('dashboard.php')) ?>"
                    >
                        <i class="bi bi-speedometer2 me-2 text-warning"></i>
                        Dashboard
                    </a>
                </li>

                <li>
                    <a
                        class="dropdown-item py-2"
                        href="<?= e(appUrl('pages/profile.php')) ?>"
                    >
                        <i class="bi bi-person me-2 text-warning"></i>
                        My Profile
                    </a>
                </li>

                <li><hr class="dropdown-divider my-1"></li>

                <li>
                    <a
                        class="dropdown-item py-2 text-danger"
                        href="<?= e(appUrl('logout.php')) ?>"
                    >
                        <i class="bi bi-box-arrow-right me-2"></i>
                        Sign Out
                    </a>
                </li>
            </ul>
        </div>
    </div>
</header>
