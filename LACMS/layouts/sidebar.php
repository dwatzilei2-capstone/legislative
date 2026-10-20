<?php
declare(strict_types=1);
$activeMenu = $activeMenu ?? '';

$moduleItems = [
    ['key'=>'agendas','label'=>'Legislative Agenda Management','icon'=>'bi-list-check','url'=>appUrl('modules/agendas/index.php'),'permission'=>'lacms.agendas.view'],
    ['key'=>'calendar','label'=>'Calendar Scheduling','icon'=>'bi-calendar-week','url'=>appUrl('modules/calendar/index.php'),'permission'=>'lacms.calendar.view'],
    ['key'=>'meetings','label'=>'Meeting Coordination','icon'=>'bi-people','url'=>appUrl('modules/meetings/index.php'),'permission'=>'lacms.meetings.view'],
    ['key'=>'deadlines','label'=>'Deadline Tracking','icon'=>'bi-alarm','url'=>appUrl('modules/deadlines/index.php'),'permission'=>'lacms.deadlines.view'],
    ['key'=>'synchronization','label'=>'Executive-Legislative Sync','icon'=>'bi-arrow-left-right','url'=>appUrl('modules/synchronization/index.php'),'permission'=>'lacms.sync.view'],
];

$automationItems = [
    ['key'=>'ai_planning','label'=>'Ollama Planning Assistant','icon'=>'bi-robot','url'=>appUrl('pages/ai_planning.php'),'badge'=>'AI','permission'=>'lacms.ai.view'],
    ['key'=>'ai_email_reminders','label'=>'AI-Assisted Email Reminders','icon'=>'bi-stars','url'=>appUrl('pages/ai_email_reminders.php'),'badge'=>'AI','permission'=>'lacms.notifications.view'],
    ['key'=>'meeting_notifications','label'=>'Meeting Notifications','icon'=>'bi-envelope-paper','url'=>appUrl('pages/meeting_notifications.php'),'badge'=>'LIVE','permission'=>'lacms.notifications.view'],
    ['key'=>'search','label'=>'Search & Filter','icon'=>'bi-search','url'=>appUrl('pages/search.php'),'badge'=>'','permission'=>'lacms.dashboard.view'],
];

$adminItems = [
    ['key'=>'reports','label'=>'Reports & Workflow','icon'=>'bi-bar-chart-line','url'=>appUrl('reports/index.php'),'permission'=>'lacms.reports.view'],
    ['key'=>'activity_logs','label'=>'Activity Logs','icon'=>'bi-clock-history','url'=>appUrl('pages/activity_logs.php'),'permission'=>'lacms.activity_logs.view'],
    ['key'=>'users','label'=>'Role-Based User Management','icon'=>'bi-person-gear','url'=>appUrl('pages/users.php'),'permission'=>'lacms.users.manage'],
    ['key'=>'system_health','label'=>'System Health','icon'=>'bi-heart-pulse','url'=>appUrl('pages/system_health.php'),'permission'=>'lacms.system_health.view'],
];
?>
<aside class="orlms-sidebar lacms-sidebar sidebar" id="orlmsSidebar">
    <div class="orlms-sidebar-brand lacms-sidebar-brand sidebar-brand">
        <img src="<?= e(appUrl('assets/images/manila.png?v=' . time())) ?>" alt="City of Manila Seal" class="orlms-sidebar-logo lacms-sidebar-logo sidebar-logo" style="width:60px !important;height:60px !important;max-width:60px !important;max-height:60px !important;object-fit:contain;">
        <div><strong>LACMS</strong><small>Agenda & Calendar Management System</small></div>
    </div>

    <nav class="orlms-sidebar-nav lacms-sidebar-nav sidebar-navigation">
        <div class="orlms-sidebar-section lacms-sidebar-section sidebar-section-label">Overview</div>
        <?php if(lacmsHasPermission('lacms.dashboard.view')): ?><a href="<?= e(appUrl('dashboard.php')) ?>" class="orlms-sidebar-link lacms-sidebar-link sidebar-link <?= $activeMenu==='dashboard'?'active':'' ?>" title="Dashboard">
            <i class="bi bi-speedometer2"></i><span>Dashboard</span>
            <?php if ($activeMenu==='dashboard'): ?><b></b><?php endif; ?>
        </a><?php endif; ?>

        <div class="orlms-sidebar-section lacms-sidebar-section sidebar-section-label">Agenda & Calendar Management</div>
        <?php foreach ($moduleItems as $item): ?>
            <?php if (!lacmsHasPermission($item['permission'])) continue; ?>
            <a href="<?= e($item['url']) ?>" class="orlms-sidebar-link lacms-sidebar-link sidebar-link <?= $activeMenu===$item['key']?'active':'' ?>" title="<?= e($item['label']) ?>">
                <i class="bi <?= e($item['icon']) ?>"></i><span><?= e($item['label']) ?></span>
                <?php if ($activeMenu===$item['key']): ?><b></b><?php endif; ?>
            </a>
        <?php endforeach; ?>

        <div class="orlms-sidebar-section lacms-sidebar-section sidebar-section-label">Automation & Communication</div>
        <?php foreach ($automationItems as $item): ?>
            <?php if (!lacmsHasPermission($item['permission'])) continue; ?>
            <a href="<?= e($item['url']) ?>" class="orlms-sidebar-link lacms-sidebar-link sidebar-link <?= $activeMenu===$item['key']?'active':'' ?>" title="<?= e($item['label']) ?>">
                <i class="bi <?= e($item['icon']) ?>"></i><span><?= e($item['label']) ?></span>
                <?php if ($item['badge']!==''): ?><em><?= e($item['badge']) ?></em><?php endif; ?>
                <?php if ($activeMenu===$item['key']): ?><b></b><?php endif; ?>
            </a>
        <?php endforeach; ?>

        <div class="orlms-sidebar-section lacms-sidebar-section sidebar-section-label">Administration</div>
        <?php foreach ($adminItems as $item): ?>
            <?php if (!lacmsHasPermission($item['permission'])) continue; ?>
            <a href="<?= e($item['url']) ?>" class="orlms-sidebar-link lacms-sidebar-link sidebar-link <?= $activeMenu===$item['key']?'active':'' ?>" title="<?= e($item['label']) ?>">
                <i class="bi <?= e($item['icon']) ?>"></i><span><?= e($item['label']) ?></span>
                <?php if ($activeMenu===$item['key']): ?><b></b><?php endif; ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="orlms-sidebar-footer lacms-sidebar-footer sidebar-session-card">
        <i class="bi bi-shield-check"></i>
        <div><strong>Secure Shared Access</strong><small>Role-based authenticated session</small></div>
    </div>
</aside>
<div class="orlms-sidebar-backdrop lacms-sidebar-backdrop sidebar-backdrop" id="orlmsSidebarBackdrop"></div>
