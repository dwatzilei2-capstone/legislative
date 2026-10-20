<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
requirePermission('orlms.users.manage');

if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(false,'Invalid request method.');
requireCsrf();

$id=(int)($_POST['id']??0);
if($id<=0)jsonResponse(false,'Invalid user id.');
if($id===(int)currentUserId())jsonResponse(false,'You cannot deactivate your own shared account.');

$pdo=db();
$q=$pdo->prepare(
 'SELECT u.id,u.full_name,r.name role_name
  FROM users u JOIN roles r ON r.id=u.role_id
  WHERE u.id=:id AND u.deleted_at IS NULL'
);
$q->execute([':id'=>$id]);$u=$q->fetch();
if(!$u)jsonResponse(false,'User not found.');

if($u['role_name']==='Administrator'){
    $count=(int)$pdo->query(
      "SELECT COUNT(*)
       FROM users u JOIN roles r ON r.id=u.role_id
       WHERE r.name='Administrator'
         AND u.status='Active'
         AND u.deleted_at IS NULL"
    )->fetchColumn();
    if($count<=1)jsonResponse(false,'The final active Administrator cannot be deactivated.');

    $access=$pdo->prepare(
      "SELECT COUNT(*)
       FROM users u
       JOIN roles r ON r.id=u.role_id
       LEFT JOIN user_system_access usa
         ON usa.user_id=u.id AND usa.system_id=:system
       WHERE r.name='Administrator'
         AND u.status='Active'
         AND u.deleted_at IS NULL
         AND u.id<>:id
         AND COALESCE(usa.status,'Active')='Active'"
    );
    $access->execute([':system'=>orlmsSystemId(),':id'=>$id]);
    if((int)$access->fetchColumn()<=0){
        jsonResponse(false,'The final ORLMS-accessible Administrator cannot be deactivated.');
    }
}

try{
    $pdo->beginTransaction();

    $pdo->prepare(
        'UPDATE users
         SET status="Inactive",deleted_at=NOW(),updated_at=NOW()
         WHERE id=:id'
    )->execute([':id'=>$id]);

    $pdo->prepare(
        'UPDATE user_system_access
         SET status="Inactive",updated_at=NOW()
         WHERE user_id=:id'
    )->execute([':id'=>$id]);

    logActivity(currentUserId(),'Deactivate Shared User',
        "Soft-deactivated user #{$id} ({$u['full_name']}); all subsystem access disabled.");

    $pdo->commit();
    jsonResponse(true,'Shared user account deactivated. Historical legislative and audit records were preserved.');
}catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();
    jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to deactivate user.');
}
