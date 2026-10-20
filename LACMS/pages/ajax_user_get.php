<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/auth.php';require_once __DIR__.'/../includes/lacms_security.php';requireLacmsPermission('lacms.users.manage');
$id=(int)($_GET['id']??0);$q=db()->prepare('SELECT u.id,u.username,u.full_name,u.email,u.phone,u.role_id,u.office_id,u.department_id,u.status,u.created_at,u.last_login_at,r.name role_name,usa.access_level,usa.status lacms_access_status FROM users u LEFT JOIN roles r ON r.id=u.role_id LEFT JOIN user_system_access usa ON usa.user_id=u.id AND usa.system_id=:system WHERE u.id=:id AND u.deleted_at IS NULL');$q->execute([':system'=>lacmsSystemId(),':id'=>$id]);$u=$q->fetch();if(!$u)jsonResponse(false,'User not found.',[],404);jsonResponse(true,'User loaded.',['user'=>$u]);
