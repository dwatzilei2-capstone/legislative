<?php
declare(strict_types=1);

/**
 * VQDSS user-management compatibility reader.
 *
 * The shared database may contain slightly different users and roles
 * schemas. These helpers detect common column names and normalize them
 * for the client-facing User Management module.
 */

function vqdssUserColumns(
    PDO $pdo,
    string $table
): array {
    static $cache = [];

    $cacheKey =
        spl_object_id($pdo) .
        ':' .
        strtolower($table);

    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    try {
        $rows = $pdo
            ->query("SHOW COLUMNS FROM `{$table}`")
            ->fetchAll();

        $columns = [];

        foreach ($rows as $row) {
            $field = trim(
                (string)($row['Field'] ?? '')
            );

            if ($field !== '') {
                $columns[strtolower($field)] =
                    $field;
            }
        }

        return $cache[$cacheKey] = $columns;
    } catch (Throwable $exception) {
        error_log(
            '[VQDSS User Columns] ' .
            $exception->getMessage()
        );

        return $cache[$cacheKey] = [];
    }
}

function vqdssUserColumn(
    array $columns,
    array $candidates
): ?string {
    foreach ($candidates as $candidate) {
        $key = strtolower($candidate);

        if (isset($columns[$key])) {
            return $columns[$key];
        }
    }

    return null;
}

function vqdssUserValue(
    array $row,
    array $candidates,
    mixed $default = ''
): mixed {
    foreach ($candidates as $candidate) {
        if (
            array_key_exists($candidate, $row) &&
            $row[$candidate] !== null &&
            $row[$candidate] !== ''
        ) {
            return $row[$candidate];
        }
    }

    return $default;
}

function vqdssNormalizeUserStatus(
    mixed $status,
    mixed $isActive = null
): string {
    $statusText = strtolower(
        trim((string)$status)
    );

    if ($statusText !== '') {
        if (
            in_array(
                $statusText,
                [
                    'active',
                    'enabled',
                    'approved',
                    '1',
                    'yes',
                ],
                true
            )
        ) {
            return 'Active';
        }

        if (
            in_array(
                $statusText,
                [
                    'inactive',
                    'disabled',
                    '0',
                    'no',
                ],
                true
            )
        ) {
            return 'Inactive';
        }

        if (
            str_contains(
                $statusText,
                'suspend'
            ) ||
            str_contains(
                $statusText,
                'lock'
            )
        ) {
            return 'Suspended';
        }

        if (
            str_contains(
                $statusText,
                'pending'
            )
        ) {
            return 'Pending';
        }

        return ucwords(
            str_replace(
                ['_', '-'],
                ' ',
                $statusText
            )
        );
    }

    if ($isActive !== null) {
        return (bool)$isActive
            ? 'Active'
            : 'Inactive';
    }

    return 'Active';
}

function vqdssNormalizeRoleLabel(
    string $roleName
): string {
    $roleName = trim($roleName);

    if ($roleName === '') {
        return 'Unassigned';
    }

    $normalized = strtolower(
        preg_replace(
            '/[^a-z0-9]+/',
            ' ',
            $roleName
        ) ?? ''
    );

    if (
        str_contains(
            $normalized,
            'admin'
        )
    ) {
        return 'Administrator';
    }

    if (
        str_contains(
            $normalized,
            'staff'
        ) ||
        str_contains(
            $normalized,
            'secretariat'
        )
    ) {
        return 'Legislative Staff';
    }

    if (
        str_contains(
            $normalized,
            'committee'
        )
    ) {
        return 'Committee Member';
    }

    if (
        str_contains(
            $normalized,
            'validator'
        ) ||
        str_contains(
            $normalized,
            'auditor'
        )
    ) {
        return 'Validator';
    }

    if (
        str_contains(
            $normalized,
            'presiding'
        ) ||
        str_contains(
            $normalized,
            'chair'
        )
    ) {
        return 'Presiding Officer';
    }

    if (
        str_contains(
            $normalized,
            'viewer'
        ) ||
        str_contains(
            $normalized,
            'observer'
        )
    ) {
        return 'Read-Only User';
    }

    return ucwords(
        str_replace(
            ['_', '-'],
            ' ',
            $roleName
        )
    );
}

function vqdssLoadRoles(
    PDO $pdo
): array {
    if (!tableExists('roles')) {
        return [];
    }

    $columns = vqdssUserColumns(
        $pdo,
        'roles'
    );

    $idColumn = vqdssUserColumn(
        $columns,
        ['id', 'role_id']
    );

    $nameColumn = vqdssUserColumn(
        $columns,
        ['name', 'role_name', 'title']
    );

    $descriptionColumn = vqdssUserColumn(
        $columns,
        [
            'description',
            'role_description',
            'details',
        ]
    );

    if (!$idColumn || !$nameColumn) {
        return [];
    }

    $descriptionSelect = $descriptionColumn
        ? "r.`{$descriptionColumn}` AS description"
        : "'' AS description";

    try {
        $rows = $pdo->query(
            "SELECT
                r.`{$idColumn}` AS id,
                r.`{$nameColumn}` AS role_name,
                {$descriptionSelect}

             FROM roles r

             ORDER BY
                r.`{$nameColumn}`"
        )->fetchAll();
    } catch (Throwable $exception) {
        error_log(
            '[VQDSS Load Roles] ' .
            $exception->getMessage()
        );

        return [];
    }

    $roles = [];

    foreach ($rows as $row) {
        $roleName = trim(
            (string)$row['role_name']
        );

        $roles[] = [
            'id' => (string)$row['id'],
            'name' => $roleName,
            'label' =>
                vqdssNormalizeRoleLabel(
                    $roleName
                ),
            'description' =>
                trim(
                    (string)$row['description']
                ),
        ];
    }

    return $roles;
}

function vqdssLoadUsers(
    PDO $pdo
): array {
    if (!tableExists('users')) {
        return [];
    }

    $columns = vqdssUserColumns(
        $pdo,
        'users'
    );

    $idColumn = vqdssUserColumn(
        $columns,
        ['id', 'user_id']
    );

    $nameColumn = vqdssUserColumn(
        $columns,
        [
            'full_name',
            'name',
            'display_name',
            'username',
        ]
    );

    $emailColumn = vqdssUserColumn(
        $columns,
        ['email', 'email_address']
    );

    $roleIdColumn = vqdssUserColumn(
        $columns,
        ['role_id', 'user_role_id']
    );

    if (!$idColumn || !$nameColumn) {
        return [];
    }

    $phoneColumn = vqdssUserColumn(
        $columns,
        [
            'phone',
            'phone_number',
            'mobile_number',
            'contact_number',
        ]
    );

    $officeIdColumn = vqdssUserColumn(
        $columns,
        [
            'office_id',
            'department_id',
            'organization_id',
        ]
    );

    $statusColumn = vqdssUserColumn(
        $columns,
        [
            'status',
            'account_status',
            'user_status',
        ]
    );

    $activeColumn = vqdssUserColumn(
        $columns,
        [
            'is_active',
            'active',
            'enabled',
        ]
    );

    $createdColumn = vqdssUserColumn(
        $columns,
        [
            'created_at',
            'date_created',
            'registered_at',
        ]
    );

    $updatedColumn = vqdssUserColumn(
        $columns,
        [
            'updated_at',
            'date_updated',
            'modified_at',
        ]
    );

    $lastLoginColumn = vqdssUserColumn(
        $columns,
        [
            'last_login_at',
            'last_login',
            'last_seen_at',
            'last_activity_at',
        ]
    );

    $usernameColumn = vqdssUserColumn(
        $columns,
        [
            'username',
            'login_name',
        ]
    );

    $selectParts = [
        "u.`{$idColumn}` AS id",
        "u.`{$nameColumn}` AS full_name",
        $emailColumn
            ? "u.`{$emailColumn}` AS email"
            : "'' AS email",
        $phoneColumn
            ? "u.`{$phoneColumn}` AS phone"
            : "'' AS phone",
        $statusColumn
            ? "u.`{$statusColumn}` AS account_status"
            : "'' AS account_status",
        $activeColumn
            ? "u.`{$activeColumn}` AS is_active"
            : "NULL AS is_active",
        $createdColumn
            ? "u.`{$createdColumn}` AS created_at"
            : "NULL AS created_at",
        $updatedColumn
            ? "u.`{$updatedColumn}` AS updated_at"
            : "NULL AS updated_at",
        $lastLoginColumn
            ? "u.`{$lastLoginColumn}` AS last_login_at"
            : "NULL AS last_login_at",
        $usernameColumn
            ? "u.`{$usernameColumn}` AS username"
            : "'' AS username",
    ];

    $joins = [];

    if (
        $roleIdColumn &&
        tableExists('roles')
    ) {
        $roleColumns = vqdssUserColumns(
            $pdo,
            'roles'
        );

        $rolePrimaryKey = vqdssUserColumn(
            $roleColumns,
            ['id', 'role_id']
        );

        $roleNameColumn = vqdssUserColumn(
            $roleColumns,
            ['name', 'role_name', 'title']
        );

        if (
            $rolePrimaryKey &&
            $roleNameColumn
        ) {
            $joins[] =
                "LEFT JOIN roles r
                 ON r.`{$rolePrimaryKey}` =
                    u.`{$roleIdColumn}`";

            $selectParts[] =
                "r.`{$roleNameColumn}` AS role_name";

            $selectParts[] =
                "u.`{$roleIdColumn}` AS role_id";
        } else {
            $selectParts[] =
                "'' AS role_name";

            $selectParts[] =
                "u.`{$roleIdColumn}` AS role_id";
        }
    } else {
        $selectParts[] =
            "'' AS role_name";

        $selectParts[] =
            "NULL AS role_id";
    }

    if (
        $officeIdColumn &&
        tableExists('offices')
    ) {
        $officeColumns = vqdssUserColumns(
            $pdo,
            'offices'
        );

        $officePrimaryKey = vqdssUserColumn(
            $officeColumns,
            ['id', 'office_id']
        );

        $officeNameColumn = vqdssUserColumn(
            $officeColumns,
            ['name', 'office_name', 'title']
        );

        if (
            $officePrimaryKey &&
            $officeNameColumn
        ) {
            $joins[] =
                "LEFT JOIN offices o
                 ON o.`{$officePrimaryKey}` =
                    u.`{$officeIdColumn}`";

            $selectParts[] =
                "o.`{$officeNameColumn}` AS office_name";

            $selectParts[] =
                "u.`{$officeIdColumn}` AS office_id";
        } else {
            $selectParts[] =
                "'' AS office_name";

            $selectParts[] =
                "u.`{$officeIdColumn}` AS office_id";
        }
    } else {
        $selectParts[] =
            "'' AS office_name";

        $selectParts[] =
            "NULL AS office_id";
    }

    try {
        $rows = $pdo->query(
            "SELECT
                " .
                implode(
                    ",\n                ",
                    $selectParts
                ) .
                "

             FROM users u

             " .
             implode(
                 "\n",
                 $joins
             ) .
             "

             ORDER BY
                u.`{$nameColumn}`"
        )->fetchAll();
    } catch (Throwable $exception) {
        error_log(
            '[VQDSS Load Users] ' .
            $exception->getMessage()
        );

        return [];
    }

    $users = [];

    foreach ($rows as $row) {
        $status = vqdssNormalizeUserStatus(
            $row['account_status'] ?? '',
            $row['is_active'] ?? null
        );

        $roleName = trim(
            (string)($row['role_name'] ?? '')
        );

        $fullName = trim(
            (string)($row['full_name'] ?? '')
        );

        $email = trim(
            (string)($row['email'] ?? '')
        );

        $office = trim(
            (string)($row['office_name'] ?? '')
        );

        $search = strtolower(
            implode(
                ' ',
                [
                    $fullName,
                    $email,
                    $roleName,
                    $office,
                    $status,
                    (string)($row['username'] ?? ''),
                    (string)($row['phone'] ?? ''),
                ]
            )
        );

        $users[] = [
            'id' => (string)$row['id'],
            'full_name' =>
                $fullName !== ''
                    ? $fullName
                    : 'Unnamed User',
            'email' => $email,
            'username' =>
                trim(
                    (string)($row['username'] ?? '')
                ),
            'phone' =>
                trim(
                    (string)($row['phone'] ?? '')
                ),
            'role_id' =>
                (string)($row['role_id'] ?? ''),
            'role_name' => $roleName,
            'role_label' =>
                vqdssNormalizeRoleLabel(
                    $roleName
                ),
            'office_id' =>
                (string)($row['office_id'] ?? ''),
            'office_name' =>
                $office !== ''
                    ? $office
                    : 'Not assigned',
            'status' => $status,
            'created_at' =>
                (string)($row['created_at'] ?? ''),
            'updated_at' =>
                (string)($row['updated_at'] ?? ''),
            'last_login_at' =>
                (string)($row['last_login_at'] ?? ''),
            'search' => $search,
        ];
    }

    return $users;
}
