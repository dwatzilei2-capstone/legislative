<?php
declare(strict_types=1);

$activeMenu=$activeMenu??'';

$mainItems=[
 ['key'=>'dashboard','label'=>'Dashboard','icon'=>'bi-speedometer2','href'=>appUrl('dashboard.php'),'permission'=>''],
];

$moduleItems=[
 ['key'=>'quorum','label'=>'Quorum Verification','icon'=>'bi-person-check','href'=>appUrl('modules/quorum/index.php'),'permission'=>'vqd.quorum.view'],
 ['key'=>'voting','label'=>'Voting Management','icon'=>'bi-ui-checks','href'=>appUrl('modules/voting/index.php'),'permission'=>'vqd.voting.view'],
 ['key'=>'tallying','label'=>'Vote Tallying','icon'=>'bi-bar-chart','href'=>appUrl('modules/tallying/index.php'),'permission'=>'vqd.tally.view'],
 ['key'=>'decisions','label'=>'Decision Recording','icon'=>'bi-journal-check','href'=>appUrl('modules/decisions/index.php'),'permission'=>'vqd.decisions.view'],
 ['key'=>'validation','label'=>'Result Validation & Reporting','icon'=>'bi-patch-check','href'=>appUrl('modules/validation/index.php'),'permission'=>'vqd.validation.view'],
];

$adminItems=[
 ['key'=>'reports','label'=>'Reports & Analytics','icon'=>'bi-bar-chart-line','href'=>appUrl('reports/index.php'),'permission'=>'vqd.reports.view'],
 ['key'=>'activity_logs','label'=>'Activity Logs','icon'=>'bi-clock-history','href'=>appUrl('pages/activity_logs.php'),'permission'=>'vqd.activity_logs.view'],
 ['key'=>'users','label'=>'User Management','icon'=>'bi-person-gear','href'=>appUrl('pages/users.php'),'permission'=>'vqd.users.manage'],
 ['key'=>'system_health','label'=>'System Health','icon'=>'bi-heart-pulse','href'=>appUrl('pages/system_health.php'),'permission'=>'vqd.system_health.view'],
 ['key'=>'ai_health','label'=>'Ollama AI Health','icon'=>'bi-robot','href'=>appUrl('pages/ai_health.php'),'permission'=>'vqd.system_health.view'],
];

function vqdSidebarCan(array $item): bool
{
    return empty($item['permission'])
        || !function_exists('vqdHasPermission')
        || vqdHasPermission($item['permission']);
}
?>
<aside class="orlms-sidebar sidebar" id="orlmsSidebar">
    <div class="orlms-sidebar-brand sidebar-brand">
        <img src="<?= e(appUrl('assets/images/manila.png')) ?>" alt="City of Manila Seal" class="orlms-sidebar-logo sidebar-logo" style="width:60px !important;height:60px !important;max-width:60px !important;max-height:60px !important;object-fit:contain;">
        <div><strong>VQDSS</strong><small>Voting &amp; Decisions Support System</small></div>
    </div>

    <nav class="orlms-sidebar-nav sidebar-navigation">
        <div class="orlms-sidebar-section sidebar-section-label">Overview</div>
        <?php foreach($mainItems as $item): if(!vqdSidebarCan($item))continue; ?>
        <a href="<?= e($item['href']) ?>" class="orlms-sidebar-link sidebar-link <?= $activeMenu===$item['key']?'active':'' ?>" title="<?= e($item['label']) ?>">
            <i class="bi <?= e($item['icon']) ?>"></i><span><?= e($item['label']) ?></span>
            <?php if($activeMenu===$item['key']):?><b></b><?php endif;?>
        </a>
        <?php endforeach; ?>

        <div class="orlms-sidebar-section sidebar-section-label">Voting and Decisions</div>
        <?php foreach($moduleItems as $item): if(!vqdSidebarCan($item))continue; ?>
        <a href="<?= e($item['href']) ?>" class="orlms-sidebar-link sidebar-link <?= $activeMenu===$item['key']?'active':'' ?>" title="<?= e($item['label']) ?>">
            <i class="bi <?= e($item['icon']) ?>"></i><span><?= e($item['label']) ?></span>
            <?php if($activeMenu===$item['key']):?><b></b><?php endif;?>
        </a>
        <?php endforeach; ?>

        <div class="orlms-sidebar-section sidebar-section-label">Administration</div>
        <?php foreach($adminItems as $item): if(!vqdSidebarCan($item))continue; ?>
        <a href="<?= e($item['href']) ?>" class="orlms-sidebar-link sidebar-link <?= $activeMenu===$item['key']?'active':'' ?>" title="<?= e($item['label']) ?>">
            <i class="bi <?= e($item['icon']) ?>"></i><span><?= e($item['label']) ?></span>
            <?php if($activeMenu===$item['key']):?><b></b><?php endif;?>
        </a>
        <?php endforeach; ?>
    </nav>

    <div class="orlms-sidebar-footer sidebar-session-card" title="Shared Session Enabled"><i class="bi bi-shield-check"></i><div><strong>Shared Access Enabled</strong><small>Session: <?= e(defined('SHARED_SESSION_NAME')?SHARED_SESSION_NAME:'lph_session') ?></small></div></div>
</aside>
<div class="orlms-sidebar-backdrop sidebar-backdrop" id="orlmsSidebarBackdrop"></div>
