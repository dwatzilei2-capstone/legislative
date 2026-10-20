<?php
$activeMenu=$activeMenu??'';

$moduleItems=[
 ['key'=>'ordinances','label'=>'Ordinance Drafting & Encoding','icon'=>'bi-file-earmark-text','url'=>'/modules/ordinances/index.php','permission'=>'orlms.drafting.view'],
 ['key'=>'resolutions','label'=>'Resolution Drafting & Submission','icon'=>'bi-file-text','url'=>'/modules/resolutions/index.php','permission'=>'orlms.drafting.view'],
 ['key'=>'reviews','label'=>'Review & Committee Endorsement','icon'=>'bi-clipboard-check','url'=>'/modules/reviews/index.php','permission'=>'orlms.reviews.view'],
 ['key'=>'approvals','label'=>'Approval & Enactment','icon'=>'bi-patch-check','url'=>'/modules/approvals/index.php','permission'=>'orlms.approvals.view'],
 ['key'=>'publications','label'=>'Publication & Dissemination','icon'=>'bi-megaphone','url'=>'/modules/publications/index.php','permission'=>'orlms.publications.view'],
 ['key'=>'implementation','label'=>'Implementation Monitoring','icon'=>'bi-activity','url'=>'/modules/implementation/index.php','permission'=>'orlms.implementation.view'],
 ['key'=>'amendments','label'=>'Amendment & Revision Tracking','icon'=>'bi-clock-history','url'=>'/modules/amendments/index.php','permission'=>'orlms.amendments.view'],
];

$featureItems=[
 ['key'=>'workflow_overview','label'=>'Life Cycle Workflow','icon'=>'bi-diagram-3','url'=>'/pages/workflow_overview.php','badge'=>'LIVE','permission'=>'orlms.workflow.view'],
 ['key'=>'ai_classification','label'=>'AI Document Classification','icon'=>'bi-stars','url'=>'/pages/ai_classification.php','badge'=>'PLANNED','permission'=>'orlms.ai.view'],
];

$adminItems=[
 ['key'=>'reports','label'=>'Reports & Analytics','icon'=>'bi-file-earmark-bar-graph','url'=>'/reports/index.php','permission'=>'orlms.reports.view'],
 ['key'=>'activity_logs','label'=>'Activity Logs','icon'=>'bi-clock-history','url'=>'/pages/activity_logs.php','permission'=>'orlms.activity_logs.view'],
 ['key'=>'users','label'=>'User Management','icon'=>'bi-person-gear','url'=>'/pages/users.php','permission'=>'orlms.users.manage'],
 ['key'=>'system_health','label'=>'System Health','icon'=>'bi-heart-pulse','url'=>'/pages/system_health.php','permission'=>'orlms.system_health.view'],
];
?>
<aside class="orlms-sidebar" id="orlmsSidebar">
<div class="orlms-sidebar-brand">
<img src="<?= e(APP_URL . '/assets/images/manila.png') ?>" alt="City of Manila Seal" class="orlms-sidebar-logo" style="width:60px !important;height:60px !important;max-width:60px !important;max-height:60px !important;object-fit:contain;">
<div><strong>ORLMS</strong><small>Ordinance & Resolution Life Cycle Management System</small></div>
</div>
<nav class="orlms-sidebar-nav">
<div class="orlms-sidebar-section">Overview</div>
<?php if(hasPermission('orlms.dashboard.view')): ?><a href="<?= e(APP_URL.'/dashboard.php') ?>" class="orlms-sidebar-link <?= $activeMenu==='dashboard'?'active':'' ?>" title="Dashboard"><i class="bi bi-speedometer2"></i><span>Dashboard</span></a><?php endif; ?>

<div class="orlms-sidebar-section">Ordinance & Resolution Life Cycle</div>
<?php foreach($moduleItems as $item): if(!hasPermission($item['permission']))continue; ?>
<a href="<?= e(APP_URL.$item['url']) ?>" class="orlms-sidebar-link <?= $activeMenu===$item['key']?'active':'' ?>" title="<?= e($item['label']) ?>"><i class="bi <?= e($item['icon']) ?>"></i><span><?= e($item['label']) ?></span><?php if($activeMenu===$item['key']):?><b></b><?php endif;?></a>
<?php endforeach; ?>

<div class="orlms-sidebar-section">Intelligence & Automation</div>
<?php foreach($featureItems as $item): if(!hasPermission($item['permission']))continue; ?>
<a href="<?= e(APP_URL.$item['url']) ?>" class="orlms-sidebar-link <?= $activeMenu===$item['key']?'active':'' ?>" title="<?= e($item['label']) ?>"><i class="bi <?= e($item['icon']) ?>"></i><span><?= e($item['label']) ?></span><em><?= e($item['badge']) ?></em><?php if($activeMenu===$item['key']):?><b></b><?php endif;?></a>
<?php endforeach; ?>

<div class="orlms-sidebar-section">Administration</div>
<?php foreach($adminItems as $item): if(!hasPermission($item['permission']))continue; ?>
<a href="<?= e(APP_URL.$item['url']) ?>" class="orlms-sidebar-link <?= $activeMenu===$item['key']?'active':'' ?>" title="<?= e($item['label']) ?>"><i class="bi <?= e($item['icon']) ?>"></i><span><?= e($item['label']) ?></span><?php if($activeMenu===$item['key']):?><b></b><?php endif;?></a>
<?php endforeach; ?>
</nav>
<div class="orlms-sidebar-footer" title="Fine-Grained RBAC · Shared session"><i class="bi bi-shield-check"></i><div><strong>Fine-Grained RBAC</strong><small>Shared session · ORLMS permission control</small></div></div>
</aside>
<div class="orlms-sidebar-backdrop" id="orlmsSidebarBackdrop"></div>
