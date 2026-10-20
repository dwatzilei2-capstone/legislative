<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
requireRole([ROLE_ADMIN]);

$pageTitle = 'User Management';
$activeMenu = 'users';
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

$usersAvailable = false;
$users = [];
$roles = [];

try {
    $usersAvailable = $tableExists($pdo, 'users');

    if ($usersAvailable) {
        $columns = $getColumns($pdo, 'users');

        $id = $pick($columns, ['id','user_id']);
        $name = $pick($columns, ['full_name','name','username','display_name']);
        $email = $pick($columns, ['email','email_address']);
        $status = $pick($columns, ['status','account_status','is_active']);
        $roleId = $pick($columns, ['role_id','user_role_id']);
        $created = $pick($columns, ['created_at','date_created','registered_at']);
        $lastLogin = $pick($columns, ['last_login_at','last_login','last_seen_at']);

        $select = [
            $id ? "u.`$id` AS id" : 'NULL AS id',
            $name ? "u.`$name` AS full_name" : "'Unnamed User' AS full_name",
            $email ? "u.`$email` AS email" : "'' AS email",
            $status ? "u.`$status` AS account_status" : "'Active' AS account_status",
            $roleId ? "u.`$roleId` AS role_id" : 'NULL AS role_id',
            $created ? "u.`$created` AS created_at" : 'NULL AS created_at',
            $lastLogin ? "u.`$lastLogin` AS last_login_at" : 'NULL AS last_login_at',
        ];

        $join = '';
        $roleSelect = "'Unassigned' AS role_name";

        if ($roleId && $tableExists($pdo, 'roles')) {
            $roleColumns = $getColumns($pdo, 'roles');
            $rolePk = $pick($roleColumns, ['id','role_id']);
            $roleName = $pick($roleColumns, ['name','role_name','title']);

            if ($rolePk && $roleName) {
                $join = " LEFT JOIN roles r ON r.`$rolePk`=u.`$roleId` ";
                $roleSelect = "COALESCE(r.`$roleName`,'Unassigned') AS role_name";

                $roles = $pdo->query(
                    "SELECT `$rolePk` AS id, `$roleName` AS name
                     FROM roles
                     ORDER BY `$roleName`"
                )->fetchAll();
            }
        }

        $select[] = $roleSelect;

        $order = $name
            ? " ORDER BY u.`$name` "
            : ($id ? " ORDER BY u.`$id` " : '');

        $users = $pdo->query(
            'SELECT '.implode(', ',$select).
            ' FROM users u '.$join.$order.' LIMIT 500'
        )->fetchAll();
    }
} catch (Throwable $exception) {
    error_log('[LACMS Users] '.$exception->getMessage());
}

$active = 0;
$inactive = 0;
$admins = 0;
$roleNames = [];

foreach ($users as $user) {
    $rawStatus = strtolower(trim((string)$user['account_status']));

    $isActive = !in_array(
        $rawStatus,
        ['0','inactive','disabled','deactivated','suspended'],
        true
    );

    $isActive ? $active++ : $inactive++;

    $roleName = trim((string)$user['role_name']);
    $roleNames[$roleName] = $roleName;

    if (str_contains(strtolower($roleName),'admin')) {
        $admins++;
    }
}

natcasesort($roleNames);

include __DIR__ . '/../../layouts/header.php';
?>

<div class="app-wrapper">
    <?php include __DIR__ . '/../../layouts/sidebar.php'; ?>

    <main class="main-content">

        <section class="admin-page-header">
            <div>
                <div class="admin-eyebrow">
                    <i class="bi bi-people-fill"></i>
                    LACMS Administration
                </div>

                <h1>User Management</h1>

                <p>
                    Review shared system users, account status, roles,
                    access assignments, and security controls.
                </p>
            </div>

            <div class="admin-header-actions">
                <a href="print.php" class="btn btn-outline-secondary" target="_blank">
                    <i class="bi bi-printer"></i>
                    Print Users
                </a>

                <button type="button" class="btn btn-primary" id="btnAddLacmsUser">
                    <i class="bi bi-person-plus"></i>
                    Add User
                </button>
            </div>
        </section>

        <section class="row g-3 mb-4">
            <?php
            $cards = [
                [count($users),'Total Users','bi-people'],
                [$active,'Active Accounts','bi-person-check'],
                [$inactive,'Inactive Accounts','bi-person-x'],
                [$admins,'Administrators','bi-shield-lock'],
                [count($roleNames),'Assigned Roles','bi-person-badge'],
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

        <?php if (!$usersAvailable): ?>
            <div class="alert alert-warning">
                <strong>Users table not found.</strong>
                The interface is ready, but no shared
                <code>users</code> table was detected.
            </div>
        <?php endif; ?>

        <section class="admin-filter-panel">
            <div class="row g-3">
                <div class="col-lg-5">
                    <label class="form-label" for="userManagementSearch">
                        Search Users
                    </label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="bi bi-search"></i>
                        </span>
                        <input
                            type="search"
                            id="userManagementSearch"
                            class="form-control"
                            placeholder="Name, email, or role..."
                        >
                    </div>
                </div>

                <div class="col-md-5 col-lg-3">
                    <label class="form-label" for="userManagementRole">
                        Role
                    </label>
                    <select id="userManagementRole" class="form-select">
                        <option value="">All Roles</option>
                        <?php foreach ($roleNames as $roleName): ?>
                            <option value="<?= e($roleName) ?>">
                                <?= e($roleName) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-5 col-lg-3">
                    <label class="form-label" for="userManagementStatus">
                        Account Status
                    </label>
                    <select id="userManagementStatus" class="form-select">
                        <option value="">All Statuses</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>

                <div class="col-md-2 col-lg-1 d-flex align-items-end">
                    <button
                        type="button"
                        id="btnResetUserManagement"
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
                        <i class="bi bi-person-gear"></i>
                        Shared User Directory
                    </h2>
                    <p>
                        User actions remain UI-only until the shared
                        account endpoints are connected.
                    </p>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table admin-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Last Login</th>
                            <th>System Access</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <?php
                            $rawStatus = strtolower(trim((string)$user['account_status']));

                            $isActive = !in_array(
                                $rawStatus,
                                ['0','inactive','disabled','deactivated','suspended'],
                                true
                            );

                            $statusKey = $isActive ? 'active' : 'inactive';
                            $roleName = (string)$user['role_name'];

                            $search = strtolower(
                                (string)$user['full_name'].' '.
                                (string)$user['email'].' '.
                                $roleName
                            );
                            ?>

                            <tr
                                class="user-management-row"
                                data-search="<?= e($search) ?>"
                                data-role="<?= e($roleName) ?>"
                                data-status="<?= e($statusKey) ?>"
                            >
                                <td>
                                    <div class="admin-user-cell">
                                        <span class="admin-avatar">
                                            <?= e(strtoupper(substr(
                                                (string)($user['full_name'] ?: 'U'),
                                                0,
                                                1
                                            ))) ?>
                                        </span>
                                        <div>
                                            <strong><?= e((string)$user['full_name']) ?></strong>
                                            <small>User ID: <?= e((string)$user['id']) ?></small>
                                        </div>
                                    </div>
                                </td>

                                <td><?= e((string)$user['email']) ?></td>

                                <td>
                                    <span class="admin-role-badge">
                                        <?= e($roleName) ?>
                                    </span>
                                </td>

                                <td>
                                    <span
                                        class="admin-account-badge
                                        <?= $isActive ? 'active' : 'inactive' ?>"
                                    >
                                        <i
                                            class="bi
                                            <?= $isActive
                                                ? 'bi-check-circle'
                                                : 'bi-x-circle'
                                            ?>"
                                        ></i>
                                        <?= $isActive ? 'Active' : 'Inactive' ?>
                                    </span>
                                </td>

                                <td>
                                    <?= !empty($user['created_at'])
                                        ? e(formatDateTime($user['created_at']))
                                        : 'Not recorded'
                                    ?>
                                </td>

                                <td>
                                    <?= !empty($user['last_login_at'])
                                        ? e(formatDateTime($user['last_login_at']))
                                        : 'No recorded login'
                                    ?>
                                </td>

                                <td>
                                    <span class="admin-soft-badge">
                                        Shared Legislative Platform
                                    </span>
                                </td>

                                <td>
                                    <div class="admin-row-actions">
                                        <button
                                            type="button"
                                            class="admin-action-button"
                                            data-user-details
                                            data-user-id="<?= e((string)$user['id']) ?>"
                                            data-user-name="<?= e((string)$user['full_name']) ?>"
                                            data-user-email="<?= e((string)$user['email']) ?>"
                                            data-user-role="<?= e($roleName) ?>"
                                            data-user-status="<?= e($isActive ? 'Active' : 'Inactive') ?>"
                                            title="View user"
                                        >
                                            <i class="bi bi-eye"></i>
                                        </button>

                                        <button
                                            type="button"
                                            class="admin-action-button"
                                            data-edit-user
                                            data-user-id="<?= e((string)$user['id']) ?>"
                                            data-user-name="<?= e((string)$user['full_name']) ?>"
                                            data-user-email="<?= e((string)$user['email']) ?>"
                                            data-user-role="<?= e($roleName) ?>"
                                            data-user-status="<?= e($isActive ? 'Active' : 'Inactive') ?>"
                                            title="Edit user"
                                        >
                                            <i class="bi bi-pencil"></i>
                                        </button>

                                        <button
                                            type="button"
                                            class="admin-action-button"
                                            data-reset-password
                                            data-user-name="<?= e((string)$user['full_name']) ?>"
                                            title="Reset password"
                                        >
                                            <i class="bi bi-key"></i>
                                        </button>

                                        <button
                                            type="button"
                                            class="admin-action-button"
                                            data-toggle-account
                                            data-user-name="<?= e((string)$user['full_name']) ?>"
                                            data-current-status="<?= e($isActive ? 'Active' : 'Inactive') ?>"
                                            title="Change account status"
                                        >
                                            <i class="bi bi-person-lock"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (!$users): ?>
                            <tr>
                                <td colspan="8">
                                    <div class="admin-empty-state">
                                        <i class="bi bi-people"></i>
                                        <strong>No users available</strong>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>

                        <tr id="userManagementNoResult" class="d-none">
                            <td colspan="8">
                                <div class="admin-empty-state compact">
                                    <i class="bi bi-search"></i>
                                    <strong>No matching users</strong>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="admin-table-footer">
                <span id="userManagementCount">
                    Showing <?= count($users) ?> user(s)
                </span>
                <span>Account changes are not stored yet.</span>
            </div>
        </section>

        <div
            class="modal fade"
            id="lacmsUserModal"
            tabindex="-1"
            aria-hidden="true"
        >
            <div class="modal-dialog modal-dialog-centered" style="max-width: 620px;">
                <div class="modal-content border-0 shadow-lg" style="border-radius: 14px; overflow: hidden;">
                    <form id="lacmsUserForm">
                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= e(csrfToken()) ?>"
                        >

                        <input type="hidden" id="lacmsUserId" name="user_id">

                        <div class="modal-header py-2.5 px-3.5" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); border-bottom: 2px solid #3b82f6;">
                            <div class="d-flex align-items-center gap-2">
                                <div class="rounded-circle d-flex align-items-center justify-content-center bg-primary bg-opacity-25 text-info" style="width: 32px; height: 32px;">
                                    <i class="bi bi-person-gear fs-6"></i>
                                </div>
                                <div>
                                    <h6 class="modal-title text-white fw-bold mb-0" id="lacmsUserModalTitle" style="font-size: 0.95rem;">
                                        Add Shared User
                                    </h6>
                                    <small class="text-white-50" style="font-size: 0.72rem;">
                                        Configure identity, role, status, access, and password controls
                                    </small>
                                </div>
                            </div>

                            <button
                                type="button"
                                class="btn-close btn-close-white"
                                data-bs-dismiss="modal"
                            ></button>
                        </div>

                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Full Name</label>
                                    <input
                                        type="text"
                                        id="lacmsUserName"
                                        name="full_name"
                                        class="form-control"
                                        required
                                    >
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Email Address</label>
                                    <input
                                        type="email"
                                        id="lacmsUserEmail"
                                        name="email"
                                        class="form-control"
                                        required
                                    >
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Role</label>
                                    <select
                                        id="lacmsUserRole"
                                        name="role_id"
                                        class="form-select"
                                    >
                                        <option value="">-- Select role --</option>
                                        <?php foreach ($roles as $role): ?>
                                            <option
                                                value="<?= e((string)$role['id']) ?>"
                                                data-role-name="<?= e((string)$role['name']) ?>"
                                            >
                                                <?= e((string)$role['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Account Status</label>
                                    <select
                                        id="lacmsUserStatus"
                                        name="status"
                                        class="form-select"
                                    >
                                        <option>Active</option>
                                        <option>Inactive</option>
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Temporary Password</label>
                                    <input
                                        type="password"
                                        name="temporary_password"
                                        class="form-control"
                                    >
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Confirm Password</label>
                                    <input
                                        type="password"
                                        name="confirm_password"
                                        class="form-control"
                                    >
                                </div>
                            </div>

                            <div class="admin-form-section">
                                <h6>System Access</h6>
                                <div class="admin-access-grid">
                                    <?php foreach (
                                        ['ORLMS','LACMS','VQDSS','PHCMS','Citizen Engagement']
                                        as $system
                                    ): ?>
                                        <label>
                                            <input
                                                type="checkbox"
                                                name="system_access[]"
                                                value="<?= e($system) ?>"
                                                <?= $system === 'LACMS' ? 'checked' : '' ?>
                                            >
                                            <span><?= e($system) ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="admin-form-section">
                                <h6>Security Options</h6>
                                <div class="admin-option-list">
                                    <label>
                                        <input type="checkbox" checked>
                                        <span>Require password change on next login.</span>
                                    </label>

                                    <label>
                                        <input type="checkbox">
                                        <span>Send account information by email.</span>
                                    </label>

                                    <label>
                                        <input type="checkbox" checked>
                                        <span>Record this change in the audit trail.</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="modal-footer py-2 px-3.5 bg-light border-top d-flex justify-content-end gap-2">
                            <button
                                type="button"
                                class="btn btn-sm btn-outline-secondary px-3"
                                data-bs-dismiss="modal"
                            >
                                Cancel
                            </button>

                            <button type="submit" class="btn btn-sm btn-primary px-3 fw-semibold shadow-sm">
                                <i class="bi bi-floppy me-1"></i>
                                Save User
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

<?php include __DIR__ . '/../../layouts/footer.php'; ?>
