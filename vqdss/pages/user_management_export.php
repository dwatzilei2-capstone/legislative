<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/user_management_reader.php';

requireRole([
    ROLE_ADMIN,
]);

$users = vqdssLoadUsers(
    db()
);

$search = strtolower(
    trim(
        (string)(
            $_GET['search']
            ?? ''
        )
    )
);

$role = trim(
    (string)(
        $_GET['role']
        ?? ''
    )
);

$office = trim(
    (string)(
        $_GET['office']
        ?? ''
    )
);

$status = trim(
    (string)(
        $_GET['status']
        ?? ''
    )
);

$users = array_values(
    array_filter(
        $users,
        static function (
            array $user
        ) use (
            $search,
            $role,
            $office,
            $status
        ): bool {
            if (
                $search !== '' &&
                !str_contains(
                    (string)$user['search'],
                    $search
                )
            ) {
                return false;
            }

            if (
                $role !== '' &&
                (string)$user['role_label'] !== $role
            ) {
                return false;
            }

            if (
                $office !== '' &&
                (string)$user['office_name'] !== $office
            ) {
                return false;
            }

            if (
                $status !== '' &&
                (string)$user['status'] !== $status
            ) {
                return false;
            }

            return true;
        }
    )
);

$filename =
    'vqdss-users-' .
    date('Y-m-d-His') .
    '.csv';

header(
    'Content-Type: text/csv; charset=UTF-8'
);

header(
    'Content-Disposition: attachment; filename="' .
    $filename .
    '"'
);

header(
    'Cache-Control: no-store, no-cache, must-revalidate'
);

$output = fopen(
    'php://output',
    'wb'
);

if ($output === false) {
    exit;
}

fwrite(
    $output,
    "\xEF\xBB\xBF"
);

fputcsv(
    $output,
    [
        'User ID',
        'Full Name',
        'Email',
        'Username',
        'Contact Number',
        'Shared Role',
        'Role Label',
        'Office',
        'Account Status',
        'Created At',
        'Updated At',
        'Last Login',
    ]
);

foreach ($users as $user) {
    fputcsv(
        $output,
        [
            $user['id'],
            $user['full_name'],
            $user['email'],
            $user['username'],
            $user['phone'],
            $user['role_name'],
            $user['role_label'],
            $user['office_name'],
            $user['status'],
            $user['created_at'],
            $user['updated_at'],
            $user['last_login_at'],
        ]
    );
}

fclose($output);
exit;
