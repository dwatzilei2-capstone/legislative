<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
requirePermission('orlms.users.manage');

$stmt=db()->prepare(
 "SELECT u.full_name,u.email,u.username,u.status,r.name role_name,
         o.name office_name,d.name department_name,
         COALESCE(usa.status,'Legacy') orlms_access,
         COALESCE(usa.access_level,'Legacy / Inherited') access_level,
         u.last_login_at
  FROM users u
  JOIN roles r ON r.id=u.role_id
  LEFT JOIN offices o ON o.id=u.office_id
  LEFT JOIN departments d ON d.id=u.department_id
  LEFT JOIN user_system_access usa
    ON usa.user_id=u.id AND usa.system_id=:system
  WHERE u.deleted_at IS NULL
  ORDER BY u.full_name"
);
$stmt->execute([':system'=>orlmsSystemId()]);$rows=$stmt->fetchAll();
?>
<!doctype html><html><head><meta charset="utf-8"><title>ORLMS Users</title><link rel="icon" type="image/png" href="../assets/images/manila.png"><style>body{font:11px Arial;margin:25px;color:#111827}.head{text-align:center;border-bottom:3px solid #0f2137;padding-bottom:12px;margin-bottom:15px}.head img{height:54px;width:54px;object-fit:contain;margin-bottom:6px}table{width:100%;border-collapse:collapse}th,td{border:1px solid #d1d5db;padding:5px;vertical-align:top}th{background:#f3f4f6}</style></head><body onload="window.print()"><div class="head"><img src="../assets/images/manila.png" alt="City of Manila Seal"><br><strong><?= e(APP_NAME) ?></strong><h2>Shared User & ORLMS Access Report</h2><div>Generated <?= date('F j, Y g:i A') ?></div></div><table><thead><tr><th>User</th><th>Email / Username</th><th>Role</th><th>Office / Department</th><th>Account</th><th>ORLMS Access</th><th>Level</th><th>Last Login</th></tr></thead><tbody><?php foreach($rows as $r): ?><tr><td><?= e($r['full_name']) ?></td><td><?= e($r['email']) ?><br><?= e($r['username']?:'') ?></td><td><?= e($r['role_name']) ?></td><td><?= e(($r['office_name']?:'').($r['department_name']?' / '.$r['department_name']:'')) ?></td><td><?= e($r['status']) ?></td><td><?= e($r['orlms_access']) ?></td><td><?= e($r['access_level']) ?></td><td><?= e($r['last_login_at']?:'Never') ?></td></tr><?php endforeach; ?></tbody></table></body></html>
