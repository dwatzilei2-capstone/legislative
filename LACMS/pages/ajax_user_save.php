<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/auth.php';require_once __DIR__.'/../includes/lacms_security.php';requireLacmsPermission('lacms.users.manage');
if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(false,'Invalid request method.');requireCsrf();
$pdo=db();$id=(int)($_POST['id']??0);$username=clean($_POST['username']??'');$name=clean($_POST['full_name']??'');$email=strtolower(clean($_POST['email']??''));$phone=clean($_POST['phone']??'');$roleId=(int)($_POST['role_id']??0);$office=(int)($_POST['office_id']??0)?:null;$department=(int)($_POST['department_id']??0)?:null;$accountStatus=clean($_POST['status']??'Active');$accessStatus=clean($_POST['lacms_access_status']??'Active');$accessLevel=clean($_POST['access_level']??'Standard');$password=(string)($_POST['password']??'');
if($name===''||!filter_var($email,FILTER_VALIDATE_EMAIL)||$roleId<=0)jsonResponse(false,'Name, valid email and role are required.');if(!in_array($accountStatus,['Active','Inactive'],true)||!in_array($accessStatus,['Active','Inactive'],true))jsonResponse(false,'Invalid status value.');if($id===0&&strlen($password)<10)jsonResponse(false,'New user password must be at least 10 characters.');if($password!==''&&strlen($password)<10)jsonResponse(false,'Password must be at least 10 characters.');
$q=$pdo->prepare('SELECT COUNT(*) FROM users WHERE id<>:id AND deleted_at IS NULL AND (LOWER(email)=LOWER(:email) OR (:username_present<>"" AND LOWER(username)=LOWER(:username_match)))');$q->execute([':id'=>$id,':email'=>$email,':username_present'=>$username,':username_match'=>$username]);if((int)$q->fetchColumn()>0)jsonResponse(false,'Email or username is already in use.');
$roleQ=$pdo->prepare('SELECT name FROM roles WHERE id=:id');$roleQ->execute([':id'=>$roleId]);$roleName=(string)$roleQ->fetchColumn();if($roleName==='')jsonResponse(false,'Selected role does not exist.');
try{$pdo->beginTransaction();$old=null;
 if($id){$q=$pdo->prepare('SELECT * FROM users WHERE id=:id FOR UPDATE');$q->execute([':id'=>$id]);$old=$q->fetch();if(!$old){$pdo->rollBack();jsonResponse(false,'User not found.');}
  if((int)$old['id']===currentUserId()&&$accountStatus!=='Active'){$pdo->rollBack();jsonResponse(false,'You cannot deactivate your own account.');}
  $oldRoleQ=$pdo->prepare('SELECT name FROM roles WHERE id=:id');$oldRoleQ->execute([':id'=>(int)$old['role_id']]);$oldRoleName=(string)$oldRoleQ->fetchColumn();
  if($oldRoleName==='Administrator'&&($roleName!=='Administrator'||$accountStatus!=='Active'||$accessStatus!=='Active')){
      $qAdmin=$pdo->prepare(
          "SELECT COUNT(*)
           FROM users u
           JOIN roles r ON r.id=u.role_id
           JOIN user_system_access usa
             ON usa.user_id=u.id
            AND usa.system_id=:system
            AND usa.status='Active'
           WHERE r.name='Administrator'
             AND u.status='Active'
             AND u.deleted_at IS NULL
             AND u.id<>:user"
      );
      $qAdmin->execute([':system'=>lacmsSystemId(),':user'=>$id]);
      if((int)$qAdmin->fetchColumn()<1){
          $pdo->rollBack();
          jsonResponse(false,'At least one other active Administrator must retain active LACMS access.');
      }
  }
  $sql='UPDATE users SET username=:username,full_name=:name,email=:email,phone=:phone,role_id=:role,office_id=:office,department_id=:department,status=:status'.($password!==''?',password=:password':'').',updated_at=NOW() WHERE id=:id';$params=[':username'=>$username?:null,':name'=>$name,':email'=>$email,':phone'=>$phone?:null,':role'=>$roleId,':office'=>$office,':department'=>$department,':status'=>$accountStatus,':id'=>$id];if($password!=='')$params[':password']=password_hash($password,PASSWORD_DEFAULT);$pdo->prepare($sql)->execute($params);
 }else{$pdo->prepare('INSERT INTO users (username,full_name,email,phone,password,role_id,office_id,department_id,status,created_at,updated_at) VALUES(:username,:name,:email,:phone,:password,:role,:office,:department,:status,NOW(),NOW())')->execute([':username'=>$username?:null,':name'=>$name,':email'=>$email,':phone'=>$phone?:null,':password'=>password_hash($password,PASSWORD_DEFAULT),':role'=>$roleId,':office'=>$office,':department'=>$department,':status'=>$accountStatus]);$id=(int)$pdo->lastInsertId();}
 $pdo->prepare('UPDATE user_roles SET is_primary=0 WHERE user_id=:user')->execute([':user'=>$id]);$pdo->prepare('INSERT INTO user_roles (user_id,role_id,is_primary,assigned_by,assigned_at) VALUES(:user,:role,1,:by,NOW()) ON DUPLICATE KEY UPDATE is_primary=1,assigned_by=VALUES(assigned_by),assigned_at=NOW()')->execute([':user'=>$id,':role'=>$roleId,':by'=>currentUserId()]);
 $pdo->prepare('INSERT INTO user_system_access (user_id,system_id,access_level,status,granted_by,granted_at,updated_at) VALUES(:user,:system,:level,:status,:by,NOW(),NOW()) ON DUPLICATE KEY UPDATE access_level=VALUES(access_level),status=VALUES(status),granted_by=VALUES(granted_by),updated_at=NOW()')->execute([':user'=>$id,':system'=>lacmsSystemId(),':level'=>$accessLevel,':status'=>$accessStatus,':by'=>currentUserId()]);
 lacmsAudit($old?'Update User':'Create User','Shared User',(string)$id,$old,['full_name'=>$name,'email'=>$email,'role_id'=>$roleId,'account_status'=>$accountStatus,'lacms_access_status'=>$accessStatus,'access_level'=>$accessLevel]);lacmsLogActivity(currentUserId(),$old?'LACMS User Update':'LACMS User Create',"Shared user #{$id} · {$name} · LACMS {$accessStatus}.");$pdo->commit();jsonResponse(true,$old?'User updated.':'User created.',['id'=>$id]);
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to save user.');}
