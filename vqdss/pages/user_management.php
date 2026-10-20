<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/user_management_reader.php';

requireRole([
    ROLE_ADMIN,
]);

$pageTitle = 'User Management';
$activeMenu = 'user_management';

$extraCss = [
    appUrl('assets/css/user-management.css'),
];

$extraJs = [
    appUrl('assets/js/user-management.js'),
];

$pdo = db();

$users = vqdssLoadUsers($pdo);
$roles = vqdssLoadRoles($pdo);

$offices = [];

try {
    if (tableExists('offices')) {
        $offices = $pdo->query(
            "SELECT id, name
             FROM offices
             ORDER BY name"
        )->fetchAll();
    }
} catch (Throwable $exception) {
    error_log(
        '[VQDSS User Management Offices] ' .
        $exception->getMessage()
    );
}

$totalUsers = count($users);
$activeUsers = 0;
$inactiveUsers = 0;
$suspendedUsers = 0;
$pendingUsers = 0;
$roleCounts = [];
$officeCounts = [];

foreach ($users as $user) {
    match ($user['status']) {
        'Active' => $activeUsers++,
        'Inactive' => $inactiveUsers++,
        'Suspended' => $suspendedUsers++,
        'Pending' => $pendingUsers++,
        default => null,
    };

    $roleLabel = $user['role_label'];
    $roleCounts[$roleLabel] =
        ($roleCounts[$roleLabel] ?? 0) + 1;

    $officeLabel = $user['office_name'];
    $officeCounts[$officeLabel] =
        ($officeCounts[$officeLabel] ?? 0) + 1;
}

arsort($roleCounts);
arsort($officeCounts);

$recentUsers = array_values($users);

usort(
    $recentUsers,
    static function (
        array $left,
        array $right
    ): int {
        $leftTime = strtotime(
            $left['created_at']
            ?: $left['updated_at']
            ?: '1970-01-01'
        ) ?: 0;

        $rightTime = strtotime(
            $right['created_at']
            ?: $right['updated_at']
            ?: '1970-01-01'
        ) ?: 0;

        return $rightTime <=> $leftTime;
    }
);

$recentUsers = array_slice(
    $recentUsers,
    0,
    6
);

$summaryCards = [
    [
        'value' => $totalUsers,
        'label' => 'Total Accounts',
        'icon' => 'bi-people',
        'class' => '',
        'note' => 'Shared platform users',
    ],
    [
        'value' => $activeUsers,
        'label' => 'Active Users',
        'icon' => 'bi-person-check',
        'class' => 'active',
        'note' => 'Enabled accounts',
    ],
    [
        'value' => $inactiveUsers,
        'label' => 'Inactive Users',
        'icon' => 'bi-person-dash',
        'class' => 'inactive',
        'note' => 'Disabled accounts',
    ],
    [
        'value' => $suspendedUsers,
        'label' => 'Suspended Users',
        'icon' => 'bi-person-x',
        'class' => 'suspended',
        'note' => 'Access restricted',
    ],
    [
        'value' => $pendingUsers,
        'label' => 'Pending Users',
        'icon' => 'bi-person-exclamation',
        'class' => 'pending',
        'note' => 'Awaiting activation',
    ],
    [
        'value' => count($roles),
        'label' => 'Available Roles',
        'icon' => 'bi-shield-lock',
        'class' => '',
        'note' => 'Shared role records',
    ],
];

$permissionMatrix = [
    [
        'module' => 'Quorum Verification',
        'icon' => 'bi-person-check',
        'permissions' => [
            'View records',
            'Record attendance',
            'Verify quorum',
            'Certify quorum',
        ],
    ],
    [
        'module' => 'Voting Management',
        'icon' => 'bi-ui-checks',
        'permissions' => [
            'View sessions',
            'Create session',
            'Manage voting',
            'Close voting',
        ],
    ],
    [
        'module' => 'Vote Tallying',
        'icon' => 'bi-bar-chart',
        'permissions' => [
            'View tallies',
            'Create tally',
            'Recount votes',
            'Certify tally',
        ],
    ],
    [
        'module' => 'Decision Recording',
        'icon' => 'bi-journal-check',
        'permissions' => [
            'View decisions',
            'Draft decision',
            'Certify decision',
            'Release decision',
        ],
    ],
    [
        'module' => 'Result Validation',
        'icon' => 'bi-patch-check',
        'permissions' => [
            'View validation',
            'Record findings',
            'Certify validation',
            'Generate report',
        ],
    ],
];

include __DIR__ . '/../layouts/header.php';
?>

<div class="app-wrapper">
    <?php include __DIR__ . '/../layouts/sidebar.php'; ?>

    <main class="main-content">

        <section class="users-page-header">
            <div>
                <div class="users-eyebrow">
                    <i class="bi bi-shield-lock"></i>
                    Access and Account Administration
                </div>

                <h1>User Management</h1>

                <p>
                    Review shared user accounts, roles, offices,
                    account status, VQDSS module access, and
                    administrative activity.
                </p>
            </div>

            <div class="users-header-actions">
                <a
                    href="user_management_export.php"
                    id="usersExportLink"
                    class="btn btn-outline-secondary"
                >
                    <i class="bi bi-download"></i>
                    Export Users
                </a>

                <button
                    type="button"
                    class="btn btn-outline-secondary"
                    id="btnPermissionMatrix"
                >
                    <i class="bi bi-grid-3x3-gap"></i>
                    Permission Matrix
                </button>

                <button
                    type="button"
                    class="btn btn-primary"
                    id="btnCreateUser"
                >
                    <i class="bi bi-person-plus"></i>
                    New User
                </button>
            </div>
        </section>

        <section class="row g-3 mb-4">
            <?php foreach ($summaryCards as $card): ?>
                <div class="col-sm-6 col-xl">
                    <div
                        class="users-summary-card
                        <?= e($card['class']) ?>"
                    >
                        <span class="summary-icon">
                            <i class="bi <?= e($card['icon']) ?>"></i>
                        </span>

                        <strong>
                            <?= (int)$card['value'] ?>
                        </strong>

                        <span><?= e($card['label']) ?></span>
                        <small><?= e($card['note']) ?></small>
                    </div>
                </div>
            <?php endforeach; ?>
        </section>

        <div class="row g-4 mb-4">
            <div class="col-xl-7">
                <section class="users-panel h-100">
                    <div class="users-panel-heading">
                        <div>
                            <h2>
                                <i class="bi bi-pie-chart"></i>
                                Users by Role
                            </h2>

                            <p>
                                Distribution of shared accounts
                                across available platform roles.
                            </p>
                        </div>
                    </div>

                    <div class="users-chart-large">
                        <canvas id="usersRoleChart"></canvas>
                    </div>
                </section>
            </div>

            <div class="col-xl-5">
                <section class="users-panel h-100">
                    <div class="users-panel-heading">
                        <div>
                            <h2>
                                <i class="bi bi-person-lines-fill"></i>
                                Recently Added Accounts
                            </h2>

                            <p>
                                Latest shared user records.
                            </p>
                        </div>
                    </div>

                    <div class="users-recent-list">
                        <?php foreach ($recentUsers as $user): ?>
                            <div class="users-recent-item">
                                <span class="users-avatar">
                                    <?= e(strtoupper(
                                        substr(
                                            $user['full_name'],
                                            0,
                                            1
                                        )
                                    )) ?>
                                </span>

                                <div>
                                    <strong>
                                        <?= e($user['full_name']) ?>
                                    </strong>

                                    <small>
                                        <?= e($user['role_label']) ?>
                                        ·
                                        <?= e($user['office_name']) ?>
                                    </small>
                                </div>

                                <span
                                    class="users-status-badge
                                    <?= e(strtolower(
                                        $user['status']
                                    )) ?>"
                                >
                                    <?= e($user['status']) ?>
                                </span>
                            </div>
                        <?php endforeach; ?>

                        <?php if (!$recentUsers): ?>
                            <div class="users-empty-state compact">
                                <i class="bi bi-person-plus"></i>

                                <strong>No user records available</strong>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
            </div>
        </div>

        <section class="users-filter-panel">
            <div class="row g-3">
                <div class="col-lg-4">
                    <label
                        class="form-label"
                        for="usersSearch"
                    >
                        Search Accounts
                    </label>

                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="bi bi-search"></i>
                        </span>

                        <input
                            type="search"
                            id="usersSearch"
                            class="form-control"
                            placeholder="Name, email, role, office..."
                        >
                    </div>
                </div>

                <div class="col-md-4 col-lg-2">
                    <label
                        class="form-label"
                        for="usersRoleFilter"
                    >
                        Role
                    </label>

                    <select
                        id="usersRoleFilter"
                        class="form-select"
                    >
                        <option value="">All Roles</option>

                        <?php foreach (array_keys($roleCounts) as $role): ?>
                            <option value="<?= e($role) ?>">
                                <?= e($role) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4 col-lg-2">
                    <label
                        class="form-label"
                        for="usersOfficeFilter"
                    >
                        Office
                    </label>

                    <select
                        id="usersOfficeFilter"
                        class="form-select"
                    >
                        <option value="">All Offices</option>

                        <?php foreach (array_keys($officeCounts) as $office): ?>
                            <option value="<?= e($office) ?>">
                                <?= e($office) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4 col-lg-2">
                    <label
                        class="form-label"
                        for="usersStatusFilter"
                    >
                        Status
                    </label>

                    <select
                        id="usersStatusFilter"
                        class="form-select"
                    >
                        <option value="">All Statuses</option>
                        <option>Active</option>
                        <option>Inactive</option>
                        <option>Pending</option>
                        <option>Suspended</option>
                    </select>
                </div>

                <div class="col-lg-2 d-flex align-items-end">
                    <button
                        type="button"
                        class="btn btn-outline-secondary w-100"
                        id="btnResetUserFilters"
                    >
                        <i class="bi bi-arrow-counterclockwise"></i>
                        Reset
                    </button>
                </div>
            </div>
        </section>

        <section class="users-panel">
            <div class="users-panel-heading">
                <div>
                    <h2>
                        <i class="bi bi-people"></i>
                        User Account Register
                    </h2>

                    <p>
                        Shared platform accounts available to VQDSS.
                    </p>
                </div>

                <span class="users-source-badge">
                    <i class="bi bi-database"></i>
                    Shared users and roles
                </span>
            </div>

            <div class="table-responsive">
                <table class="table users-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Role</th>
                            <th>Office</th>
                            <th>Status</th>
                            <th>Last Login</th>
                            <th>Created</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <?php
                            $payload = [
                                'id' => $user['id'],
                                'fullName' => $user['full_name'],
                                'email' => $user['email'],
                                'username' => $user['username'],
                                'phone' => $user['phone'],
                                'roleId' => $user['role_id'],
                                'roleName' => $user['role_name'],
                                'roleLabel' => $user['role_label'],
                                'officeId' => $user['office_id'],
                                'officeName' => $user['office_name'],
                                'status' => $user['status'],
                                'createdAt' => formatDateTime(
                                    $user['created_at']
                                ),
                                'updatedAt' => formatDateTime(
                                    $user['updated_at']
                                ),
                                'lastLoginAt' => formatDateTime(
                                    $user['last_login_at']
                                ),
                            ];
                            ?>

                            <tr
                                class="users-data-row"
                                data-search="<?= e($user['search']) ?>"
                                data-role="<?= e($user['role_label']) ?>"
                                data-office="<?= e($user['office_name']) ?>"
                                data-status="<?= e($user['status']) ?>"
                            >
                                <td>
                                    <div class="users-user-cell">
                                        <span class="users-avatar">
                                            <?= e(strtoupper(
                                                substr(
                                                    $user['full_name'],
                                                    0,
                                                    1
                                                )
                                            )) ?>
                                        </span>

                                        <div>
                                            <strong>
                                                <?= e($user['full_name']) ?>
                                            </strong>

                                            <small>
                                                <?= e(
                                                    $user['email']
                                                    ?: (
                                                        $user['username']
                                                        ?: 'No email recorded'
                                                    )
                                                ) ?>
                                            </small>
                                        </div>
                                    </div>
                                </td>

                                <td>
                                    <span class="users-role-badge">
                                        <?= e($user['role_label']) ?>
                                    </span>
                                </td>

                                <td>
                                    <?= e($user['office_name']) ?>
                                </td>

                                <td>
                                    <span
                                        class="users-status-badge
                                        <?= e(strtolower(
                                            $user['status']
                                        )) ?>"
                                    >
                                        <?= e($user['status']) ?>
                                    </span>
                                </td>

                                <td>
                                    <?= e(
                                        $user['last_login_at']
                                        ? formatDateTime(
                                            $user['last_login_at']
                                        )
                                        : 'No login recorded'
                                    ) ?>
                                </td>

                                <td>
                                    <?= e(
                                        $user['created_at']
                                        ? formatDateTime(
                                            $user['created_at']
                                        )
                                        : 'Not recorded'
                                    ) ?>
                                </td>

                                <td>
                                    <div class="users-row-actions">
                                        <button
                                            type="button"
                                            class="users-action-button"
                                            data-view-user="<?= e(json_encode(
                                                $payload,
                                                JSON_UNESCAPED_UNICODE |
                                                JSON_UNESCAPED_SLASHES
                                            )) ?>"
                                            title="View account"
                                        >
                                            <i class="bi bi-eye"></i>
                                        </button>

                                        <button
                                            type="button"
                                            class="users-action-button"
                                            data-edit-user="<?= e(json_encode(
                                                $payload,
                                                JSON_UNESCAPED_UNICODE |
                                                JSON_UNESCAPED_SLASHES
                                            )) ?>"
                                            title="Edit account"
                                        >
                                            <i class="bi bi-pencil-square"></i>
                                        </button>

                                        <button
                                            type="button"
                                            class="users-action-button"
                                            data-user-permissions="<?= e(json_encode(
                                                $payload,
                                                JSON_UNESCAPED_UNICODE |
                                                JSON_UNESCAPED_SLASHES
                                            )) ?>"
                                            title="Manage permissions"
                                        >
                                            <i class="bi bi-shield-lock"></i>
                                        </button>

                                        <button
                                            type="button"
                                            class="users-action-button danger"
                                            data-user-status-toggle="<?= e(json_encode(
                                                $payload,
                                                JSON_UNESCAPED_UNICODE |
                                                JSON_UNESCAPED_SLASHES
                                            )) ?>"
                                            title="Change account status"
                                        >
                                            <i class="bi bi-person-slash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (!$users): ?>
                            <tr>
                                <td colspan="7">
                                    <div class="users-empty-state">
                                        <i class="bi bi-people"></i>

                                        <strong>
                                            No user accounts available
                                        </strong>

                                        <span>
                                            Shared users will appear here
                                            when the users table contains data.
                                        </span>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>

                        <tr
                            id="usersNoFilterResult"
                            class="d-none"
                        >
                            <td colspan="7">
                                <div class="users-empty-state compact">
                                    <i class="bi bi-search"></i>

                                    <strong>
                                        No matching user accounts
                                    </strong>

                                    <span>
                                        Adjust the search or selected filters.
                                    </span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="users-table-footer">
                <span id="usersRecordCount">
                    Showing <?= $totalUsers ?> account(s)
                </span>

                <span>
                    Account changes remain browser previews until
                    backend write endpoints are connected.
                </span>
            </div>
        </section>

        <div
            class="modal fade"
            id="userEditorModal"
            tabindex="-1"
            aria-hidden="true"
        >
            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" style="max-width: 680px;">
                <div class="modal-content shadow-lg border-0" style="border-radius: 14px; overflow: hidden;">
                    <form id="userEditorForm">
                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= e(csrfToken()) ?>"
                        >

                        <input
                            type="hidden"
                            id="userEditorId"
                            name="user_id"
                        >

                        <div class="modal-header">
                            <div>
                                <h5
                                    class="modal-title"
                                    id="userEditorTitle"
                                >
                                    Create User Account
                                </h5>

                                <small class="text-muted">
                                    Configure account identity, role,
                                    office, status, and VQDSS access.
                                </small>
                            </div>

                            <button
                                type="button"
                                class="btn-close"
                                data-bs-dismiss="modal"
                            ></button>
                        </div>

                        <div class="modal-body">
                            <div class="users-form-steps">
                                <button
                                    type="button"
                                    class="users-step active"
                                    data-user-step="1"
                                >
                                    <span>1</span>
                                    Account Details
                                </button>

                                <button
                                    type="button"
                                    class="users-step"
                                    data-user-step="2"
                                >
                                    <span>2</span>
                                    Role and Office
                                </button>

                                <button
                                    type="button"
                                    class="users-step"
                                    data-user-step="3"
                                >
                                    <span>3</span>
                                    VQDSS Access
                                </button>
                            </div>

                            <section
                                class="users-form-section active"
                                data-user-form-section="1"
                            >
                                <div class="users-section-heading">
                                    <h6>Account Identity</h6>

                                    <p>
                                        Enter the user’s basic contact and
                                        authentication information.
                                    </p>
                                </div>

                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">
                                            Full Name
                                        </label>

                                        <input
                                            type="text"
                                            class="form-control"
                                            id="userFullName"
                                            required
                                        >
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label">
                                            Email Address
                                        </label>

                                        <input
                                            type="email"
                                            class="form-control"
                                            id="userEmail"
                                        >
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label">
                                            Username
                                        </label>

                                        <input
                                            type="text"
                                            class="form-control"
                                            id="userUsername"
                                        >
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label">
                                            Contact Number
                                        </label>

                                        <input
                                            type="text"
                                            class="form-control"
                                            id="userPhone"
                                        >
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label">
                                            Temporary Password
                                        </label>

                                        <input
                                            type="password"
                                            class="form-control"
                                            id="userPassword"
                                            placeholder="Leave blank when editing"
                                        >
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label">
                                            Password Confirmation
                                        </label>

                                        <input
                                            type="password"
                                            class="form-control"
                                            id="userPasswordConfirm"
                                            placeholder="Repeat temporary password"
                                        >
                                    </div>
                                </div>

                                <div class="users-security-options">
                                    <label>
                                        <input
                                            type="checkbox"
                                            id="userForcePasswordChange"
                                            checked
                                        >

                                        <span>
                                            Require password change on first login.
                                        </span>
                                    </label>

                                    <label>
                                        <input
                                            type="checkbox"
                                            id="userEnableMfa"
                                        >

                                        <span>
                                            Require multi-factor authentication when available.
                                        </span>
                                    </label>

                                    <label>
                                        <input
                                            type="checkbox"
                                            id="userSendWelcome"
                                            checked
                                        >

                                        <span>
                                            Send account activation or welcome notice.
                                        </span>
                                    </label>
                                </div>
                            </section>

                            <section
                                class="users-form-section"
                                data-user-form-section="2"
                            >
                                <div class="users-section-heading">
                                    <h6>Role, Office, and Account Status</h6>

                                    <p>
                                        Assign shared organizational access
                                        and the account’s initial state.
                                    </p>
                                </div>

                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label">
                                            Shared Role
                                        </label>

                                        <select
                                            class="form-select"
                                            id="userRole"
                                        >
                                            <option value="">
                                                -- Select role --
                                            </option>

                                            <?php foreach ($roles as $role): ?>
                                                <option
                                                    value="<?= e($role['id']) ?>"
                                                    data-role-name="<?= e($role['name']) ?>"
                                                    data-role-label="<?= e($role['label']) ?>"
                                                >
                                                    <?= e($role['label']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="col-md-4">
                                        <label class="form-label">
                                            Office or Department
                                        </label>

                                        <select
                                            class="form-select"
                                            id="userOffice"
                                        >
                                            <option value="">
                                                -- Not assigned --
                                            </option>

                                            <?php foreach ($offices as $office): ?>
                                                <option value="<?= (int)$office['id'] ?>">
                                                    <?= e($office['name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="col-md-4">
                                        <label class="form-label">
                                            Account Status
                                        </label>

                                        <select
                                            class="form-select"
                                            id="userStatus"
                                        >
                                            <option>Active</option>
                                            <option>Pending</option>
                                            <option>Inactive</option>
                                            <option>Suspended</option>
                                        </select>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label">
                                            Access Expiration
                                        </label>

                                        <input
                                            type="date"
                                            class="form-control"
                                            id="userAccessExpiration"
                                        >
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label">
                                            Administrative Notes
                                        </label>

                                        <input
                                            type="text"
                                            class="form-control"
                                            id="userAdminNotes"
                                            placeholder="Reason, assignment, access restriction..."
                                        >
                                    </div>
                                </div>

                                <div class="users-role-summary">
                                    <span class="users-role-summary-icon">
                                        <i class="bi bi-shield-lock"></i>
                                    </span>

                                    <div>
                                        <strong id="userRoleSummaryTitle">
                                            No role selected
                                        </strong>

                                        <span id="userRoleSummaryText">
                                            Select a role to preview the
                                            recommended VQDSS access level.
                                        </span>
                                    </div>
                                </div>
                            </section>

                            <section
                                class="users-form-section"
                                data-user-form-section="3"
                            >
                                <div class="users-section-heading">
                                    <h6>VQDSS Module Access</h6>

                                    <p>
                                        Configure a client-demo permission
                                        preview for the five primary modules.
                                    </p>
                                </div>

                                <div class="users-permission-builder">
                                    <?php foreach ($permissionMatrix as $moduleIndex => $module): ?>
                                        <div
                                            class="users-permission-card"
                                            data-permission-module
                                        >
                                            <div class="users-permission-card-header">
                                                <span>
                                                    <i class="bi <?= e($module['icon']) ?>"></i>
                                                </span>

                                                <div>
                                                    <strong>
                                                        <?= e($module['module']) ?>
                                                    </strong>

                                                    <small>
                                                        Module-level privileges
                                                    </small>
                                                </div>

                                                <label class="form-check form-switch">
                                                    <input
                                                        type="checkbox"
                                                        class="form-check-input"
                                                        data-module-toggle
                                                        checked
                                                    >
                                                </label>
                                            </div>

                                            <div class="users-permission-options">
                                                <?php foreach ($module['permissions'] as $permissionIndex => $permission): ?>
                                                    <label>
                                                        <input
                                                            type="checkbox"
                                                            value="<?= e($permission) ?>"
                                                            data-permission-checkbox
                                                            <?= $permissionIndex === 0
                                                                ? 'checked'
                                                                : ''
                                                            ?>
                                                        >

                                                        <span>
                                                            <?= e($permission) ?>
                                                        </span>
                                                    </label>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <div class="users-permission-summary">
                                    <div>
                                        <span>Enabled Modules</span>
                                        <strong id="userEnabledModuleCount">
                                            5
                                        </strong>
                                    </div>

                                    <div>
                                        <span>Selected Permissions</span>
                                        <strong id="userPermissionCount">
                                            5
                                        </strong>
                                    </div>

                                    <div>
                                        <span>Account Status</span>
                                        <strong id="userPermissionStatus">
                                            Active
                                        </strong>
                                    </div>

                                    <div>
                                        <span>Role Preview</span>
                                        <strong id="userPermissionRole">
                                            Unassigned
                                        </strong>
                                    </div>
                                </div>
                            </section>
                        </div>

                        <div class="modal-footer">
                            <button
                                type="button"
                                class="btn btn-outline-secondary me-auto"
                                id="btnPreviousUserStep"
                                disabled
                            >
                                <i class="bi bi-arrow-left"></i>
                                Previous
                            </button>

                            <button
                                type="button"
                                class="btn btn-primary"
                                id="btnNextUserStep"
                            >
                                Next
                                <i class="bi bi-arrow-right"></i>
                            </button>

                            <button
                                type="submit"
                                class="btn btn-primary d-none"
                                id="btnSaveUserPreview"
                            >
                                <i class="bi bi-person-check"></i>
                                Save Account Preview
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div
            class="modal fade"
            id="userViewModal"
            tabindex="-1"
            aria-hidden="true"
        >
            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" style="max-width: 620px;">
                <div class="modal-content shadow-lg border-0" style="border-radius: 14px; overflow: hidden;">
                    <div class="modal-header">
                        <div>
                            <h5 class="modal-title">
                                User Account Details
                            </h5>

                            <small
                                class="text-muted"
                                id="userViewId"
                            >
                                Shared account record
                            </small>
                        </div>

                        <button
                            type="button"
                            class="btn-close"
                            data-bs-dismiss="modal"
                        ></button>
                    </div>

                    <div class="modal-body">
                        <div class="users-profile-card">
                            <span
                                class="users-profile-avatar"
                                id="userViewAvatar"
                            >
                                U
                            </span>

                            <div>
                                <strong id="userViewName">
                                    User Name
                                </strong>

                                <span id="userViewEmail">
                                    No email recorded
                                </span>

                                <small id="userViewRole">
                                    Unassigned
                                </small>
                            </div>

                            <span
                                class="users-status-badge active"
                                id="userViewStatus"
                            >
                                Active
                            </span>
                        </div>

                        <div class="users-profile-grid">
                            <div>
                                <span>Username</span>
                                <strong id="userViewUsername">
                                    Not recorded
                                </strong>
                            </div>

                            <div>
                                <span>Contact Number</span>
                                <strong id="userViewPhone">
                                    Not recorded
                                </strong>
                            </div>

                            <div>
                                <span>Office</span>
                                <strong id="userViewOffice">
                                    Not assigned
                                </strong>
                            </div>

                            <div>
                                <span>Created</span>
                                <strong id="userViewCreated">
                                    Not recorded
                                </strong>
                            </div>

                            <div>
                                <span>Last Updated</span>
                                <strong id="userViewUpdated">
                                    Not recorded
                                </strong>
                            </div>

                            <div>
                                <span>Last Login</span>
                                <strong id="userViewLastLogin">
                                    No login recorded
                                </strong>
                            </div>
                        </div>

                        <section class="users-profile-section">
                            <h6>
                                <i class="bi bi-shield-check"></i>
                                VQDSS Access Summary
                            </h6>

                            <div class="users-access-summary">
                                <span>Quorum Verification</span>
                                <span>Voting Management</span>
                                <span>Vote Tallying</span>
                                <span>Decision Recording</span>
                                <span>Result Validation</span>
                            </div>

                            <p>
                                Detailed permissions will be stored during
                                the backend role-and-permission phase.
                            </p>
                        </section>
                    </div>

                    <div class="modal-footer">
                        <button
                            type="button"
                            class="btn btn-outline-secondary"
                            data-bs-dismiss="modal"
                        >
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div
            class="modal fade"
            id="permissionMatrixModal"
            tabindex="-1"
            aria-hidden="true"
        >
            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" style="max-width: 720px;">
                <div class="modal-content shadow-lg border-0" style="border-radius: 14px; overflow: hidden;">
                    <div class="modal-header">
                        <div>
                            <h5 class="modal-title">
                                VQDSS Permission Matrix
                            </h5>

                            <small class="text-muted">
                                Recommended role privileges for the
                                client-demo and future backend.
                            </small>
                        </div>

                        <button
                            type="button"
                            class="btn-close"
                            data-bs-dismiss="modal"
                        ></button>
                    </div>

                    <div class="modal-body">
                        <div class="table-responsive">
                            <table class="table users-matrix-table">
                                <thead>
                                    <tr>
                                        <th>Module Permission</th>
                                        <th>Administrator</th>
                                        <th>Legislative Staff</th>
                                        <th>Committee Member</th>
                                        <th>Validator</th>
                                        <th>Read-Only User</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    <?php foreach ($permissionMatrix as $module): ?>
                                        <tr class="users-matrix-module-row">
                                            <td colspan="6">
                                                <i class="bi <?= e($module['icon']) ?>"></i>
                                                <?= e($module['module']) ?>
                                            </td>
                                        </tr>

                                        <?php foreach ($module['permissions'] as $permissionIndex => $permission): ?>
                                            <tr>
                                                <td><?= e($permission) ?></td>

                                                <td class="text-center">
                                                    <i class="bi bi-check-circle-fill matrix-yes"></i>
                                                </td>

                                                <td class="text-center">
                                                    <i
                                                        class="bi
                                                        <?= $permissionIndex < 3
                                                            ? 'bi-check-circle-fill matrix-yes'
                                                            : 'bi-dash-circle matrix-limited'
                                                        ?>"
                                                    ></i>
                                                </td>

                                                <td class="text-center">
                                                    <i
                                                        class="bi
                                                        <?= $permissionIndex < 2
                                                            ? 'bi-check-circle-fill matrix-yes'
                                                            : 'bi-dash-circle matrix-limited'
                                                        ?>"
                                                    ></i>
                                                </td>

                                                <td class="text-center">
                                                    <i
                                                        class="bi
                                                        <?= $module['module'] === 'Result Validation'
                                                            ? 'bi-check-circle-fill matrix-yes'
                                                            : (
                                                                $permissionIndex === 0
                                                                    ? 'bi-check-circle-fill matrix-yes'
                                                                    : 'bi-x-circle matrix-no'
                                                            )
                                                        ?>"
                                                    ></i>
                                                </td>

                                                <td class="text-center">
                                                    <i
                                                        class="bi
                                                        <?= $permissionIndex === 0
                                                            ? 'bi-check-circle-fill matrix-yes'
                                                            : 'bi-x-circle matrix-no'
                                                        ?>"
                                                    ></i>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="users-matrix-note">
                            <i class="bi bi-info-circle"></i>

                            <span>
                                This matrix is a recommended VQDSS model.
                                Final permissions should be stored in
                                dedicated role, permission, and role-permission
                                tables during backend implementation.
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

<script>
window.VQDSS_USER_DATA = <?= json_encode(
    [
        'roles' => array_map(
            static fn (
                string $label,
                int $total
            ): array => [
                'label' => $label,
                'total' => $total,
            ],
            array_keys($roleCounts),
            array_values($roleCounts)
        ),
        'summary' => [
            'total' => $totalUsers,
            'active' => $activeUsers,
            'inactive' => $inactiveUsers,
            'pending' => $pendingUsers,
            'suspended' => $suspendedUsers,
        ],
    ],
    JSON_UNESCAPED_UNICODE |
    JSON_UNESCAPED_SLASHES
) ?>;
</script>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
