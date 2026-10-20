<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
requireRole([ROLE_ADMIN, ROLE_STAFF]);

$pageTitle = 'Activity Logs';
$activeMenu = 'activity_logs';
$extraCss = [appUrl('assets/css/administration.css')];
$extraJs = [appUrl('assets/js/administration.js')];

$pdo = db();

$tableExists = static function (PDO $pdo, string $table): bool {
    $stmt = $pdo->prepare('SHOW TABLES LIKE :table_name');
    $stmt->execute([':table_name' => $table]);
    return (bool)$stmt->fetchColumn();
};

$getColumns = static function (PDO $pdo, string $table): array {
    $columns = [];
    foreach ($pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll() as $row) {
        $columns[] = $row['Field'];
    }
    return $columns;
};

$pick = static function (array $columns, array $candidates): ?string {
    foreach ($candidates as $candidate) {
        if (in_array($candidate, $columns, true)) {
            return $candidate;
        }
    }
    return null;
};

$logsAvailable = false;
$logs = [];
$moduleOptions = [];
$actionOptions = [];

try {
    $logsAvailable = $tableExists($pdo, 'activity_logs');

    if ($logsAvailable) {
        $columns = $getColumns($pdo, 'activity_logs');

        $idCol = $pick($columns, ['id','log_id']);
        $userCol = $pick($columns, ['user_id','actor_id','created_by']);
        $actionCol = $pick($columns, ['action','event','activity','action_type']);
        $moduleCol = $pick($columns, ['module','system','source','entity_type']);
        $descriptionCol = $pick($columns, ['description','details','message','activity_description']);
        $ipCol = $pick($columns, ['ip_address','ip','remote_address']);
        $createdCol = $pick($columns, ['created_at','logged_at','timestamp','date_created']);

        $select = [
            $idCol ? "al.`$idCol` AS id" : 'NULL AS id',
            $actionCol ? "al.`$actionCol` AS action_name" : "'Activity' AS action_name",
            $moduleCol ? "al.`$moduleCol` AS module_name" : "'LACMS' AS module_name",
            $descriptionCol ? "al.`$descriptionCol` AS description_text" : "'' AS description_text",
            $ipCol ? "al.`$ipCol` AS ip_address" : "'' AS ip_address",
            $createdCol ? "al.`$createdCol` AS created_at" : 'NULL AS created_at',
        ];

        $join = '';
        $actorSelect = "'System' AS actor_name";
        $emailSelect = "'' AS actor_email";

        if ($userCol && $tableExists($pdo, 'users')) {
            $userColumns = $getColumns($pdo, 'users');
            $userId = $pick($userColumns, ['id','user_id']);
            $userName = $pick($userColumns, ['full_name','name','username','display_name']);
            $userEmail = $pick($userColumns, ['email','email_address']);

            if ($userId) {
                $join = " LEFT JOIN users u ON u.`$userId`=al.`$userCol` ";
                if ($userName) {
                    $actorSelect = "COALESCE(u.`$userName`,'System') AS actor_name";
                }
                if ($userEmail) {
                    $emailSelect = "COALESCE(u.`$userEmail`,'') AS actor_email";
                }
            }
        }

        $select[] = $actorSelect;
        $select[] = $emailSelect;

        $order = $createdCol
            ? " ORDER BY al.`$createdCol` DESC "
            : ($idCol ? " ORDER BY al.`$idCol` DESC " : '');

        $logs = $pdo->query(
            'SELECT '.implode(', ',$select).
            ' FROM activity_logs al '.$join.$order.' LIMIT 500'
        )->fetchAll();

        foreach ($logs as $log) {
            $module = trim((string)$log['module_name']);
            $action = trim((string)$log['action_name']);
            if ($module !== '') $moduleOptions[$module] = $module;
            if ($action !== '') $actionOptions[$action] = $action;
        }

        natcasesort($moduleOptions);
        natcasesort($actionOptions);
    }
} catch (Throwable $exception) {
    error_log('[LACMS Activity Logs] '.$exception->getMessage());
}

$today = 0;
$warnings = 0;
$security = 0;

foreach ($logs as $log) {
    $created = (string)($log['created_at'] ?? '');
    if ($created !== '' && substr($created,0,10) === date('Y-m-d')) {
        $today++;
    }

    $text = strtolower(
        (string)$log['action_name'].' '.
        (string)$log['description_text']
    );

    if (
        str_contains($text,'failed') ||
        str_contains($text,'error') ||
        str_contains($text,'warning') ||
        str_contains($text,'rejected')
    ) {
        $warnings++;
    }

    if (
        str_contains($text,'login') ||
        str_contains($text,'password') ||
        str_contains($text,'permission') ||
        str_contains($text,'access') ||
        str_contains($text,'role')
    ) {
        $security++;
    }
}

include __DIR__ . '/../../layouts/header.php';
?>

<div class="app-wrapper">
    <?php include __DIR__ . '/../../layouts/sidebar.php'; ?>

    <main class="main-content">

        <section class="admin-page-header">
            <div>
                <div class="admin-eyebrow">
                    <i class="bi bi-clock-history"></i>
                    LACMS Administration
                </div>

                <h1>Activity Logs &amp; Audit Trail</h1>

                <p>
                    Review user activity, module actions, security events,
                    timestamps, descriptions, and IP information.
                </p>
            </div>

            <div class="admin-header-actions">
                <a href="print.php" class="btn btn-primary" target="_blank">
                    <i class="bi bi-printer"></i>
                    Print Logs
                </a>
            </div>
        </section>

        <section class="row g-3 mb-4">
            <?php
            $cards = [
                [count($logs),'Loaded Audit Records','bi-list-ul'],
                [$today,'Activities Today','bi-calendar-check'],
                [count($moduleOptions),'Modules Represented','bi-grid'],
                [$warnings,'Warnings or Failures','bi-exclamation-triangle'],
                [$security,'Security-Related Events','bi-shield-lock'],
            ];
            ?>

            <?php foreach ($cards as [$value,$label,$icon]): ?>
                <div class="col-sm-6 col-xl">
                    <div class="admin-summary-card">
                        <span class="summary-icon">
                            <i class="bi <?= e($icon) ?>"></i>
                        </span>
                        <strong><?= (int)$value ?></strong>
                        <span><?= e($label) ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </section>

        <?php if (!$logsAvailable): ?>
            <div class="alert alert-warning">
                <strong>Activity log table not found.</strong>
                The page is ready, but the shared database does not
                currently contain an <code>activity_logs</code> table.
            </div>
        <?php endif; ?>

        <section class="admin-filter-panel">
            <div class="row g-3">
                <div class="col-lg-4">
                    <label class="form-label" for="activityLogSearch">
                        Search Audit Trail
                    </label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="bi bi-search"></i>
                        </span>
                        <input
                            type="search"
                            id="activityLogSearch"
                            class="form-control"
                            placeholder="Actor, action, module, description..."
                        >
                    </div>
                </div>

                <div class="col-md-4 col-lg-2">
                    <label class="form-label" for="activityLogModule">
                        Module
                    </label>
                    <select id="activityLogModule" class="form-select">
                        <option value="">All Modules</option>
                        <?php foreach ($moduleOptions as $module): ?>
                            <option value="<?= e($module) ?>">
                                <?= e($module) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4 col-lg-2">
                    <label class="form-label" for="activityLogAction">
                        Action
                    </label>
                    <select id="activityLogAction" class="form-select">
                        <option value="">All Actions</option>
                        <?php foreach ($actionOptions as $action): ?>
                            <option value="<?= e($action) ?>">
                                <?= e($action) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4 col-lg-3">
                    <label class="form-label" for="activityLogDate">
                        Date
                    </label>
                    <input
                        type="date"
                        id="activityLogDate"
                        class="form-control"
                    >
                </div>

                <div class="col-lg-1 d-flex align-items-end">
                    <button
                        type="button"
                        id="btnResetActivityLogs"
                        class="btn btn-outline-secondary w-100"
                    >
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </button>
                </div>
            </div>
        </section>

        <section class="admin-panel">
            <div class="admin-panel-heading">
                <div>
                    <h2>
                        <i class="bi bi-shield-check"></i>
                        Read-Only Audit Trail
                    </h2>
                    <p>
                        The page adapts to common activity_logs schemas.
                    </p>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table admin-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Date &amp; Time</th>
                            <th>Actor</th>
                            <th>Module</th>
                            <th>Action</th>
                            <th>Description</th>
                            <th>IP Address</th>
                            <th>Severity</th>
                            <th class="text-center">Details</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <?php
                            $text = strtolower(
                                (string)$log['action_name'].' '.
                                (string)$log['description_text']
                            );

                            $severity = 'Information';
                            $severityClass = 'info';

                            if (
                                str_contains($text,'failed') ||
                                str_contains($text,'error') ||
                                str_contains($text,'rejected')
                            ) {
                                $severity = 'Critical';
                                $severityClass = 'critical';
                            } elseif (
                                str_contains($text,'warning') ||
                                str_contains($text,'delete') ||
                                str_contains($text,'deactivate')
                            ) {
                                $severity = 'Warning';
                                $severityClass = 'warning';
                            } elseif (
                                str_contains($text,'login') ||
                                str_contains($text,'password') ||
                                str_contains($text,'permission') ||
                                str_contains($text,'access') ||
                                str_contains($text,'role')
                            ) {
                                $severity = 'Security';
                                $severityClass = 'security';
                            }

                            $created = (string)($log['created_at'] ?? '');
                            $dateValue = $created !== ''
                                ? substr($created,0,10)
                                : '';

                            $search = strtolower(
                                (string)$log['actor_name'].' '.
                                (string)$log['actor_email'].' '.
                                (string)$log['module_name'].' '.
                                (string)$log['action_name'].' '.
                                (string)$log['description_text'].' '.
                                (string)$log['ip_address']
                            );
                            ?>

                            <tr
                                class="activity-log-row"
                                data-search="<?= e($search) ?>"
                                data-module="<?= e((string)$log['module_name']) ?>"
                                data-action="<?= e((string)$log['action_name']) ?>"
                                data-date="<?= e($dateValue) ?>"
                            >
                                <td>
                                    <?= $created !== ''
                                        ? e(formatDateTime($created))
                                        : 'Not recorded'
                                    ?>
                                </td>

                                <td>
                                    <div class="admin-user-cell">
                                        <span class="admin-avatar">
                                            <?= e(strtoupper(substr(
                                                (string)($log['actor_name'] ?: 'S'),
                                                0,
                                                1
                                            ))) ?>
                                        </span>
                                        <div>
                                            <strong>
                                                <?= e((string)($log['actor_name'] ?: 'System')) ?>
                                            </strong>
                                            <small>
                                                <?= e((string)$log['actor_email']) ?>
                                            </small>
                                        </div>
                                    </div>
                                </td>

                                <td>
                                    <span class="admin-soft-badge">
                                        <?= e((string)$log['module_name']) ?>
                                    </span>
                                </td>

                                <td><?= e((string)$log['action_name']) ?></td>

                                <td>
                                    <div class="admin-description-cell">
                                        <?= e((string)$log['description_text']) ?>
                                    </div>
                                </td>

                                <td><?= e((string)$log['ip_address']) ?></td>

                                <td>
                                    <span
                                        class="admin-severity-badge
                                        <?= e($severityClass) ?>"
                                    >
                                        <?= e($severity) ?>
                                    </span>
                                </td>

                                <td>
                                    <div class="admin-row-actions">
                                        <button
                                            type="button"
                                            class="admin-action-button"
                                            data-log-details
                                            data-log-actor="<?= e((string)($log['actor_name'] ?: 'System')) ?>"
                                            data-log-module="<?= e((string)$log['module_name']) ?>"
                                            data-log-action="<?= e((string)$log['action_name']) ?>"
                                            data-log-description="<?= e((string)$log['description_text']) ?>"
                                            data-log-ip="<?= e((string)$log['ip_address']) ?>"
                                            data-log-date="<?= e($created) ?>"
                                        >
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (!$logs): ?>
                            <tr>
                                <td colspan="8">
                                    <div class="admin-empty-state">
                                        <i class="bi bi-clock-history"></i>
                                        <strong>No activity records available</strong>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>

                        <tr id="activityLogNoResult" class="d-none">
                            <td colspan="8">
                                <div class="admin-empty-state compact">
                                    <i class="bi bi-search"></i>
                                    <strong>No matching activity records</strong>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="admin-table-footer">
                <span id="activityLogCount">
                    Showing <?= count($logs) ?> log record(s)
                </span>
                <span>This interface is read-only.</span>
            </div>
        </section>

<?php include __DIR__ . '/../../layouts/footer.php'; ?>
