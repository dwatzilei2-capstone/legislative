<?php
/**
 * layouts/top_controls.php
 * ------------------------------------------------------------------
 * Shared Top Bar Controls for Dashboard & All Modules
 * Matched 100% to VQDSS / ORLMS Subsystem Navigation Design
 * ------------------------------------------------------------------
 */
$currentUser = function_exists('currentUser') ? (currentUser() ?? []) : ($_SESSION['user'] ?? []);
$userName = trim((string)($currentUser['full_name'] ?? $currentUser['name'] ?? 'Authorized User'));
$userRole = trim((string)($currentUser['role_name'] ?? $currentUser['role'] ?? 'Administrator'));
$userEmail = trim((string)($currentUser['email'] ?? ''));
$userInitial = strtoupper(substr($userName !== '' ? $userName : 'A', 0, 1));

$portalBaseUrl = rtrim(dirname(defined('APP_URL') ? APP_URL : 'http://localhost/legislative/lph'), '/');
$subsystems = [
    ['short' => 'ORLMS', 'name' => 'Ordinance & Resolution Life Cycle', 'url' => $portalBaseUrl . '/ORLMS/', 'icon' => 'bi-file-earmark-text', 'active' => false],
    ['short' => 'SLMMS', 'name' => 'Session & Legislative Meeting', 'url' => $portalBaseUrl . '/subsystem_info.php?code=slmms', 'icon' => 'bi-calendar-event', 'active' => false],
    ['short' => 'LACMS', 'name' => 'Legislative Agenda & Calendar', 'url' => $portalBaseUrl . '/LACMS/', 'icon' => 'bi-calendar3', 'active' => false],
    ['short' => 'CMAS', 'name' => 'Committee Management & Assignment', 'url' => $portalBaseUrl . '/subsystem_info.php?code=cmas', 'icon' => 'bi-diagram-3', 'active' => false],
    ['short' => 'VQDSS', 'name' => 'Voting, Quorum & Decisions', 'url' => $portalBaseUrl . '/vqdss/', 'icon' => 'bi-check2-square', 'active' => false],
    ['short' => 'LRDMS', 'name' => 'Records & Document Management', 'url' => $portalBaseUrl . '/subsystem_info.php?code=lrdms', 'icon' => 'bi-folder-check', 'active' => false],
    ['short' => 'LPH', 'name' => 'Public Hearing & Consultation', 'url' => $portalBaseUrl . '/lph/', 'icon' => 'bi-people', 'active' => true],
    ['short' => 'LAHRS', 'name' => 'Archives & Historical Repository', 'url' => $portalBaseUrl . '/subsystem_info.php?code=lahrs', 'icon' => 'bi-archive', 'active' => false],
    ['short' => 'LRPAIES', 'name' => 'Research, Policy & Impact Evaluation', 'url' => $portalBaseUrl . '/subsystem_info.php?code=lrpaies', 'icon' => 'bi-graph-up-arrow', 'active' => false],
    ['short' => 'CEPFMS', 'name' => 'Citizen Engagement & Feedback', 'url' => $portalBaseUrl . '/CEPFMS/', 'icon' => 'bi-chat-square-heart', 'active' => false],
    ['short' => 'PORTAL', 'name' => 'Legislative Citizen Portal', 'url' => $portalBaseUrl . '/citizen_portal/', 'icon' => 'bi-person-badge', 'active' => false],
];
?>
<div class="lph-top-controls d-flex align-items-center justify-content-between gap-2 mb-3 w-100 py-1">
  <!-- Standard Topbar Menu Button for Mobile/Desktop Toggle -->
  <button
    type="button"
    class="topbar-menu-button orlms-menu-button"
    id="sidebarToggleBtn"
    onclick="lphToggleSidebar(event)"
    title="Toggle Sidebar Navigation"
    aria-label="Toggle navigation"
  >
    <i class="bi bi-list fs-5"></i>
  </button>

  <!-- Right Controls Section -->
  <div class="d-flex align-items-center gap-3 ms-auto">
    <!-- Subsystems Switcher Dropdown (Matched to VQDSS / ORLMS / LACMS) -->
    <div class="dropdown">
      <button class="orlms-system-switcher dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
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

    <!-- Admin / User Profile Dropdown (Matched to VQDSS / ORLMS) -->
    <div class="dropdown">
      <button
        class="topbar-user-button orlms-user-button dropdown-toggle"
        type="button"
        data-bs-toggle="dropdown"
        aria-expanded="false"
      >
        <span class="orlms-avatar">
          <?= e($userInitial) ?>
        </span>
        <span class="orlms-user-copy">
          <strong><?= e($userName) ?></strong>
          <small><?= e($userRole) ?></small>
        </span>
      </button>

      <ul class="dropdown-menu dropdown-menu-end shadow-lg mt-2" style="font-size: 0.85rem; z-index: 1060; border: 1.5px solid #a97900 !important; border-radius: 12px; min-width: 210px; box-shadow: 0 12px 35px rgba(0,0,0,.10), 0 0 10px rgba(169,121,0,.12) !important;">
        <?php if ($userEmail !== ''): ?>
        <li>
          <span class="dropdown-item-text small text-muted">
            <?= e($userEmail) ?>
          </span>
        </li>
        <li><hr class="dropdown-divider my-1"></li>
        <?php endif; ?>
        <li><a class="dropdown-item py-2" href="<?= e(APP_URL) ?>/dashboard.php"><i class="bi bi-speedometer2 me-2 text-warning"></i> Dashboard</a></li>
        <li><a class="dropdown-item py-2" href="<?= e(APP_URL) ?>/pages/profile.php"><i class="bi bi-person me-2 text-warning"></i> My Profile</a></li>
        <li><hr class="dropdown-divider my-1"></li>
        <li>
          <a class="dropdown-item py-2 text-danger" href="<?= e(APP_URL) ?>/logout.php">
            <i class="bi bi-box-arrow-right me-2"></i>
            Sign Out
          </a>
        </li>
      </ul>
    </div>
  </div>
</div>
