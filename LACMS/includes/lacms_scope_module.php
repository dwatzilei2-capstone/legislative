<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
requireRole([ROLE_ADMIN,ROLE_STAFF,ROLE_COMMITTEE]);

$pageTitle = $moduleTitle ?? 'LACMS Module';
$activeMenu = $moduleKey ?? '';
$extraCss = [appUrl('assets/css/lacms-module-pages.css')];

$moduleFeatures = $moduleFeatures ?? [];
$moduleSections = $moduleSections ?? [];
$workflowSteps = $workflowSteps ?? [];
$quickActions = $quickActions ?? [];
$recordFields = $recordFields ?? [];
$problemLink = $problemLink ?? '';
$previousModule = $previousModule ?? null;
$nextModule = $nextModule ?? null;

include __DIR__ . '/../layouts/header.php';
?>
<div class="lacms-app-wrapper">
<?php include __DIR__ . '/../layouts/sidebar.php'; ?>
<main class="lacms-main-content">

<section class="lacms-module-header">
    <div>
        <div class="lacms-module-eyebrow"><i class="bi <?= e($moduleIcon ?? 'bi-calendar3') ?>"></i> Legislative Agenda & Calendar Module</div>
        <h1><?= e($moduleTitle ?? 'LACMS Module') ?></h1>
        <p><?= e($moduleDescription ?? 'Client-ready navigation and module workspace.') ?></p>
    </div>
    <div class="lacms-module-actions">
        <a href="<?= e(appUrl('pages/search.php')) ?>" class="btn btn-outline-secondary"><i class="bi bi-search"></i> Search</a>
        <button type="button" class="btn btn-primary" data-ui-preview><i class="bi bi-plus-circle"></i> <?= e($primaryAction ?? 'New Record') ?></button>
    </div>
</section>

<?php if ($problemLink !== ''): ?>
<section class="lacms-problem-link">
    <span><i class="bi bi-bullseye"></i></span>
    <div><small>Problem Addressed</small><strong><?= e($problemLink) ?></strong></div>
</section>
<?php endif; ?>

<section class="lacms-context-strip">
    <div><span><i class="bi bi-database-check"></i></span><div><small>Centralized Records</small><strong>Agenda, Meetings & Deadlines</strong></div></div>
    <div><span><i class="bi bi-bell"></i></span><div><small>Automation</small><strong>Reminder & Notification Ready</strong></div></div>
    <div><span><i class="bi bi-people"></i></span><div><small>Coordination</small><strong>Legislators & Staff</strong></div></div>
    <div><span><i class="bi bi-shield-lock"></i></span><div><small>Access</small><strong>Role-Based & Authenticated</strong></div></div>
</section>

<div class="row g-4 mb-4">
    <div class="col-xl-8">
        <section class="lacms-panel h-100">
            <div class="lacms-panel-heading"><div><h2><i class="bi bi-grid-1x2"></i> Module Functions</h2><p>Functions aligned with the revised project scope.</p></div></div>
            <div class="lacms-feature-grid">
                <?php foreach ($moduleFeatures as $feature): ?>
                <button type="button" class="lacms-feature-card" data-ui-preview>
                    <span><i class="bi <?= e($feature['icon']) ?>"></i></span>
                    <div><strong><?= e($feature['title']) ?></strong><small><?= e($feature['description']) ?></small></div>
                    <i class="bi bi-chevron-right"></i>
                </button>
                <?php endforeach; ?>
            </div>
        </section>
    </div>
    <div class="col-xl-4">
        <section class="lacms-panel h-100">
            <div class="lacms-panel-heading"><div><h2><i class="bi bi-lightning-charge"></i> Quick Navigation</h2><p>Common client-demo actions.</p></div></div>
            <div class="lacms-quick-list">
                <?php foreach ($quickActions as $action): ?>
                <button type="button" data-ui-preview>
                    <i class="bi <?= e($action['icon']) ?>"></i>
                    <span><strong><?= e($action['title']) ?></strong><small><?= e($action['description']) ?></small></span>
                </button>
                <?php endforeach; ?>
            </div>
        </section>
    </div>
</div>

<section class="lacms-panel mb-4">
    <div class="lacms-panel-heading"><div><h2><i class="bi bi-diagram-3"></i> Module Workflow</h2><p>Suggested automated process for this area.</p></div></div>
    <div class="lacms-workflow-grid">
        <?php foreach ($workflowSteps as $index=>$step): ?>
        <div class="lacms-workflow-step">
            <span><?= str_pad((string)($index+1),2,'0',STR_PAD_LEFT) ?></span>
            <div><strong><?= e($step['title']) ?></strong><small><?= e($step['description']) ?></small></div>
            <?php if ($index<count($workflowSteps)-1): ?><i class="bi bi-arrow-right"></i><?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</section>

<div class="row g-4 mb-4">
    <div class="col-xl-7">
        <section class="lacms-panel h-100">
            <div class="lacms-panel-heading"><div><h2><i class="bi bi-layout-text-window-reverse"></i> Workspace Sections</h2><p>Planned pages, views, and processing queues.</p></div></div>
            <div class="lacms-section-list">
                <?php foreach ($moduleSections as $section): ?>
                <button type="button" data-ui-preview>
                    <span><i class="bi <?= e($section['icon']) ?>"></i></span>
                    <div><strong><?= e($section['title']) ?></strong><small><?= e($section['description']) ?></small></div>
                </button>
                <?php endforeach; ?>
            </div>
        </section>
    </div>
    <div class="col-xl-5">
        <section class="lacms-panel h-100">
            <div class="lacms-panel-heading"><div><h2><i class="bi bi-card-checklist"></i> Record Information</h2><p>Key information planned for this module.</p></div></div>
            <div class="lacms-record-fields">
                <?php foreach ($recordFields as $field): ?><div><i class="bi bi-check2"></i><span><?= e($field) ?></span></div><?php endforeach; ?>
            </div>
        </section>
    </div>
</div>

<section class="lacms-panel">
    <div class="lacms-panel-heading"><div><h2><i class="bi bi-table"></i> Module Record Workspace</h2><p>Backend CRUD and automation are intentionally postponed.</p></div></div>
    <div class="lacms-empty-workspace">
        <i class="bi <?= e($moduleIcon ?? 'bi-calendar3') ?>"></i>
        <strong><?= e($moduleTitle ?? 'LACMS Module') ?> navigation is complete</strong>
        <span>The module functions, workflow, searchable workspace concept, notifications, and record requirements are ready for the future database and automation phase.</span>
    </div>
</section>

<nav class="lacms-prev-next">
    <?php if ($previousModule): ?>
    <a href="<?= e(appUrl($previousModule['href'])) ?>"><i class="bi bi-arrow-left"></i><span><small>Previous Module</small><strong><?= e($previousModule['label']) ?></strong></span></a>
    <?php else: ?><span></span><?php endif; ?>

    <?php if ($nextModule): ?>
    <a href="<?= e(appUrl($nextModule['href'])) ?>" class="next"><span><small>Next Module</small><strong><?= e($nextModule['label']) ?></strong></span><i class="bi bi-arrow-right"></i></a>
    <?php endif; ?>
</nav>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
