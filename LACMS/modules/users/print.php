<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
requireRole([ROLE_ADMIN]);

$users = [];

try {
    $users = db()->query(
        "SELECT
            u.id,
            u.full_name,
            u.email,
            u.status,
            u.created_at,
            r.name AS role_name
         FROM users u
         LEFT JOIN roles r ON r.id=u.role_id
         ORDER BY u.full_name"
    )->fetchAll();
} catch (Throwable $exception) {
    error_log('[LACMS Users Print] '.$exception->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>LACMS User Directory</title>
<style>
body{font-family:Arial,sans-serif;color:#0f172a;padding:22px;font-size:11px}
header{text-align:center;border-bottom:3px solid #0b3d6e;padding-bottom:12px;margin-bottom:15px}
h1{color:#0b3d6e;margin:0}
table{width:100%;border-collapse:collapse}
th,td{border:1px solid #cbd5e1;padding:7px;text-align:left}
th{background:#0b3d6e;color:#fff;text-transform:uppercase}
.no-print{text-align:right;margin-bottom:10px}
@media print{.no-print{display:none}body{padding:0}@page{margin:10mm}}
</style>
</head>
<body onload="window.print()">
<div class="no-print"><button onclick="window.print()">Print</button></div>
<header>
    <h1>LACMS Shared User Directory</h1>
    <p>Generated <?= e(date('F j, Y g:i A')) ?></p>
</header>
<table>
<thead>
<tr><th>#</th><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Created</th></tr>
</thead>
<tbody>
<?php foreach ($users as $index => $user): ?>
<tr>
<td><?= $index + 1 ?></td>
<td><?= e($user['full_name']) ?></td>
<td><?= e($user['email']) ?></td>
<td><?= e($user['role_name'] ?: 'Unassigned') ?></td>
<td><?= e($user['status']) ?></td>
<td><?= !empty($user['created_at']) ? e(formatDateTime($user['created_at'])) : 'Not recorded' ?></td>
</tr>
<?php endforeach; ?>
<?php if (!$users): ?><tr><td colspan="6">No users available.</td></tr><?php endif; ?>
</tbody>
</table>
</body>
</html>
