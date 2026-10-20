<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
requirePermission('vqd.users.manage');

if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(false,'Invalid request method.');
requireCsrf();

$id=(int)($_POST['id']??0);
if($id<=0)jsonResponse(false,'Invalid user id.');
if($id===(int)currentUserId())jsonResponse(false,'You cannot deactivate your own account.');

$pdo=db();
$q=$pdo->prepare('SELECT u.id,u.full_name,r.name role_name FROM users u JOIN roles r ON r.id=u.role_id WHERE u.id=:id AND u.deleted_at IS NULL');
$q->execute([':id'=>$id]);$u=$q->fetch();
if(!$u)jsonResponse(false,'User not found.');

if($u['role_name']==='Administrator'){
    $count=(int)$pdo->query(
      "SELECT COUNT(*) FROM users u JOIN roles r ON r.id=u.role_id
       WHERE r.name='Administrator' AND u.status='Active' AND u.deleted_at IS NULL"
    )->fetchColumn();
    if($count<=1)jsonResponse(false,'The final active Administrator cannot be deactivated.');
}

try{
    $pdo->beginTransaction();
    $pdo->prepare('UPDATE users SET status="Inactive",deleted_at=NOW(),updated_at=NOW() WHERE id=:id')->execute([':id'=>$id]);
    $pdo->prepare('UPDATE user_system_access SET status="Inactive",updated_at=NOW() WHERE user_id=:id AND system_id=:system')
        ->execute([':id'=>$id,':system'=>vqdSystemId()]);
    vqdLogActivity(currentUserId(),'Deactivate VQDSS User',"Soft-deactivated user #{$id} ({$u['full_name']}).");
    $pdo->commit();
    jsonResponse(true,'User deactivated. Historical voting and audit records were preserved.');
}catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();
    jsonResponse(false,defined('APP_DEBUG')&&APP_DEBUG?$e->getMessage():'Unable to deactivate user.');
}
