<?php
require_once __DIR__ . '/auth.php';

$scopePermission=match((string)($moduleKey??'')){
    'ordinances','resolutions' => 'orlms.drafting.view',
    'reviews' => 'orlms.reviews.view',
    'approvals' => 'orlms.approvals.view',
    'publications' => 'orlms.publications.view',
    'implementation' => 'orlms.implementation.view',
    'amendments' => 'orlms.amendments.view',
    default => 'orlms.workflow.view',
};
requirePermission($scopePermission);
$pageTitle = $moduleTitle ?? 'ORLMS Module';
$activeMenu = $moduleKey ?? '';
$extraCss = [APP_URL . '/assets/css/orlms-module-pages.css'];
$moduleFeatures = $moduleFeatures ?? [];
$moduleSections = $moduleSections ?? [];
$workflowSteps = $workflowSteps ?? [];
$quickActions = $quickActions ?? [];
$recordFields = $recordFields ?? [];
$previousModule = $previousModule ?? null;
$nextModule = $nextModule ?? null;
include __DIR__ . '/../layouts/header.php';
?>
<div class="orlms-app-wrapper">
<?php include __DIR__ . '/../layouts/sidebar.php'; ?>
<main class="orlms-main-content">
<section class="scope-module-header">
<div>
<div class="scope-module-eyebrow"><i class="bi <?= e($moduleIcon ?? 'bi-file-earmark-text') ?>"></i>Legislative Life Cycle Module</div>
<h1><?= e($moduleTitle) ?></h1>
<p><?= e($moduleDescription) ?></p>
</div>
<div class="scope-module-actions">
<a href="<?= e(APP_URL . '/pages/workflow_overview.php') ?>" class="btn btn-outline-secondary"><i class="bi bi-diagram-3"></i> Life Cycle</a>
<button type="button" class="btn btn-primary" data-ui-preview><i class="bi bi-plus-circle"></i> <?= e($primaryAction) ?></button>
</div>
</section>

<section class="scope-context-strip">
<div><span><i class="bi bi-building"></i></span><div><small>Office</small><strong>Legislative Services Office</strong></div></div>
<div><span><i class="bi bi-geo-alt"></i></span><div><small>Institution</small><strong>Manila City Hall</strong></div></div>
<div><span><i class="bi bi-arrow-repeat"></i></span><div><small>Workflow</small><strong>Electronic Routing & Status Tracking</strong></div></div>
<div><span><i class="bi bi-stars"></i></span><div><small>AI Classification</small><strong>Ollama Integration · Planned</strong></div></div>
</section>

<div class="row g-4 mb-4">
<div class="col-xl-8">
<section class="scope-panel h-100">
<div class="scope-panel-heading"><div><h2><i class="bi bi-grid-1x2"></i>Module Functions</h2><p>Functions aligned with the approved project scope.</p></div></div>
<div class="scope-feature-grid">
<?php foreach ($moduleFeatures as $feature): ?>
<button type="button" class="scope-feature-card" data-ui-preview>
<span><i class="bi <?= e($feature['icon']) ?>"></i></span>
<div><strong><?= e($feature['title']) ?></strong><small><?= e($feature['description']) ?></small></div>
<i class="bi bi-chevron-right"></i>
</button>
<?php endforeach; ?>
</div>
</section>
</div>
<div class="col-xl-4">
<section class="scope-panel h-100">
<div class="scope-panel-heading"><div><h2><i class="bi bi-lightning-charge"></i>Quick Navigation</h2><p>Common actions for the client demonstration.</p></div></div>
<div class="scope-quick-list">
<?php foreach ($quickActions as $action): ?>
<button type="button" data-ui-preview><i class="bi <?= e($action['icon']) ?>"></i><span><strong><?= e($action['title']) ?></strong><small><?= e($action['description']) ?></small></span></button>
<?php endforeach; ?>
</div>
</section>
</div>
</div>

<section class="scope-panel mb-4">
<div class="scope-panel-heading"><div><h2><i class="bi bi-diagram-3"></i>Module Workflow</h2><p>Recommended electronic routing for this stage.</p></div></div>
<div class="scope-workflow-grid">
<?php foreach ($workflowSteps as $index=>$step): ?>
<div class="scope-workflow-step">
<span><?= str_pad((string)($index+1),2,'0',STR_PAD_LEFT) ?></span>
<div><strong><?= e($step['title']) ?></strong><small><?= e($step['description']) ?></small></div>
<?php if($index<count($workflowSteps)-1):?><i class="bi bi-arrow-right"></i><?php endif;?>
</div>
<?php endforeach; ?>
</div>
</section>

<div class="row g-4 mb-4">
<div class="col-xl-7">
<section class="scope-panel h-100">
<div class="scope-panel-heading"><div><h2><i class="bi bi-layout-text-window-reverse"></i>Workspace Sections</h2><p>Planned pages, panels, and work queues.</p></div></div>
<div class="scope-section-list">
<?php foreach ($moduleSections as $section): ?>
<button type="button" data-ui-preview><span><i class="bi <?= e($section['icon']) ?>"></i></span><div><strong><?= e($section['title']) ?></strong><small><?= e($section['description']) ?></small></div></button>
<?php endforeach; ?>
</div>
</section>
</div>
<div class="col-xl-5">
<section class="scope-panel h-100">
<div class="scope-panel-heading"><div><h2><i class="bi bi-card-checklist"></i>Record Information</h2><p>Key information planned for this module.</p></div></div>
<div class="scope-record-fields">
<?php foreach($recordFields as $field):?><div><i class="bi bi-check2"></i><span><?= e($field) ?></span></div><?php endforeach;?>
</div>
</section>
</div>
</div>

<section class="scope-panel">
<div class="scope-panel-heading"><div><h2><i class="bi bi-table"></i>Module Record Workspace</h2><p>Database CRUD is intentionally postponed during the navigation phase.</p></div></div>
<div class="scope-empty-workspace">
<i class="bi <?= e($moduleIcon) ?>"></i>
<strong><?= e($moduleTitle) ?> navigation is complete</strong>
<span>The module page, workflow, navigation, actions, sections, and data requirements are ready for backend development. No database records are modified by this revision package.</span>
</div>
</section>

<nav class="scope-prev-next">
<?php if($previousModule):?>
<a href="<?= e(APP_URL . $previousModule['href']) ?>"><i class="bi bi-arrow-left"></i><span><small>Previous Module</small><strong><?= e($previousModule['label']) ?></strong></span></a>
<?php else:?><span></span><?php endif;?>
<?php if($nextModule):?>
<a href="<?= e(APP_URL . $nextModule['href']) ?>" class="next"><span><small>Next Module</small><strong><?= e($nextModule['label']) ?></strong></span><i class="bi bi-arrow-right"></i></a>
<?php endif;?>
</nav>
<?php include __DIR__ . '/../layouts/footer.php'; ?>
