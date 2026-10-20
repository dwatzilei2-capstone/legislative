<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/auth.php';
requireRole([ROLE_ADMIN, ROLE_STAFF]);

$logs = [];

try {
    $pdo = db();
    $columns = [];

    foreach ($pdo->query('SHOW COLUMNS FROM activity_logs')->fetchAll() as $row) {
        $columns[] = $row['Field'];
    }

    $pick = static function (array $columns, array $candidates): ?string {
        foreach ($candidates as $candidate) {
            if (in_array($candidate,$columns,true)) return $candidate;
        }
        return null;
    };

    $action = $pick($columns,['action','event','activity','action_type']);
    $module = $pick($columns,['module','system','source','entity_type']);
    $description = $pick($columns,['description','details','message','activity_description']);
    $ip = $pick($columns,['ip_address','ip','remote_address']);
    $created = $pick($columns,['created_at','logged_at','timestamp','date_created']);
    $id = $pick($columns,['id','log_id']);

    $select = [
        $created ? "`$created` AS created_at" : 'NULL AS created_at',
        $module ? "`$module` AS module_name" : "'LACMS' AS module_name",
        $action ? "`$action` AS action_name" : "'Activity' AS action_name",
        $description ? "`$description` AS description_text" : "'' AS description_text",
        $ip ? "`$ip` AS ip_address" : "'' AS ip_address",
    ];

    $order = $created
        ? " ORDER BY `$created` DESC "
        : ($id ? " ORDER BY `$id` DESC " : '');

    $logs = $pdo->query(
        'SELECT '.implode(', ',$select).
        ' FROM activity_logs '.$order.' LIMIT 500'
    )->fetchAll();
} catch (Throwable $exception) {
    error_log('[LACMS Logs Print] '.$exception->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>LACMS Activity Logs</title>
<style>
body{font-family:Arial,sans-serif;color:#0f172a;padding:22px;font-size:10px}
header{text-align:center;border-bottom:3px solid #0b3d6e;padding-bottom:12px;margin-bottom:15px}
h1{color:#0b3d6e;margin:0}
table{width:100%;border-collapse:collapse}
th,td{border:1px solid #cbd5e1;padding:6px;vertical-align:top}
th{background:#0b3d6e;color:#fff;text-transform:uppercase}
.no-print{text-align:right;margin-bottom:10px}
@page{size:landscape;margin:9mm}
@media print{.no-print{display:none}body{padding:0}thead{display:table-header-group}tr{page-break-inside:avoid}}
</style>
</head>
<body onload="window.print()">
<div class="no-print"><button onclick="window.print()">Print</button></div>
<header>
    <h1>LACMS Activity Logs &amp; Audit Trail</h1>
    <p>Generated <?= e(date('F j, Y g:i A')) ?></p>
</header>
<table>
<thead>
<tr><th>Date</th><th>Module</th><th>Action</th><th>Description</th><th>IP Address</th></tr>
</thead>
<tbody>
<?php foreach ($logs as $log): ?>
<tr>
<td><?= !empty($log['created_at']) ? e(formatDateTime($log['created_at'])) : 'Not recorded' ?></td>
<td><?= e((string)$log['module_name']) ?></td>
<td><?= e((string)$log['action_name']) ?></td>
<td><?= e((string)$log['description_text']) ?></td>
<td><?= e((string)$log['ip_address']) ?></td>
</tr>
<?php endforeach; ?>
<?php if (!$logs): ?><tr><td colspan="5">No activity logs available.</td></tr><?php endif; ?>
</tbody>
</table>
</body>
</html>
