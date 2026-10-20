<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
requirePermission('vqd.users.manage');

if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(false,'Invalid request method.');
requireCsrf();

$pdo=db();$id=(int)($_POST['id']??0);
$name=clean($_POST['full_name']??'');
$username=clean($_POST['username']??'');
$email=strtolower(clean($_POST['email']??''));
$phone=clean($_POST['phone']??'');
$roleId=(int)($_POST['role_id']??0);
$officeId=(int)($_POST['office_id']??0)?:null;
$status=clean($_POST['status']??'Active');
$access=clean($_POST['vqd_access_status']??'Active');
$level=clean($_POST['access_level']??'Standard');
$password=(string)($_POST['password']??'');

$errors=[];
if($name==='')$errors[]='Full name is required.';
if($email===''||!filter_var($email,FILTER_VALIDATE_EMAIL))$errors[]='A valid email is required.';
if($roleId<=0)$errors[]='Select a role.';
if(!in_array($status,['Active','Inactive'],true))$errors[]='Invalid account status.';
if(!in_array($access,['Active','Inactive'],true))$errors[]='Invalid VQDSS access status.';
if(!in_array($level,['Administrator','Staff','Committee','Stakeholder','Standard'],true))$errors[]='Invalid access level.';
if($id===0&&$password==='')$errors[]='Password is required for a new user.';
if($password!==''){
    if(strlen($password)<10)$errors[]='Password must be at least 10 characters.';
    if(!preg_match('/[A-Z]/',$password))$errors[]='Password must include uppercase.';
    if(!preg_match('/[a-z]/',$password))$errors[]='Password must include lowercase.';
    if(!preg_match('/[0-9]/',$password))$errors[]='Password must include a number.';
}
if($errors)jsonResponse(false,implode(' ',$errors));

$role=$pdo->prepare('SELECT name FROM roles WHERE id=:id');$role->execute([':id'=>$roleId]);$roleName=$role->fetchColumn();
if(!$roleName)jsonResponse(false,'Selected role does not exist.');

$dup=$pdo->prepare(
 'SELECT id FROM users
  WHERE deleted_at IS NULL AND id<>:id
    AND (LOWER(email)=LOWER(:email)
      OR (:username_present<>"" AND LOWER(username)=LOWER(:username_match)))
  LIMIT 1'
);
$dup->execute([
  ':id'=>$id,
  ':email'=>$email,
  ':username_present'=>$username,
  ':username_match'=>$username
]);
if($dup->fetch())jsonResponse(false,'Another active account already uses that email or username.');

$existing=null;
if($id>0){
    $q=$pdo->prepare('SELECT u.*,r.name role_name FROM users u JOIN roles r ON r.id=u.role_id WHERE u.id=:id AND u.deleted_at IS NULL');
    $q->execute([':id'=>$id]);$existing=$q->fetch();
    if(!$existing)jsonResponse(false,'User not found.');

    if($id===(int)currentUserId()){
        if($status!=='Active'||$access!=='Active')jsonResponse(false,'You cannot deactivate your own account or VQDSS access.');
        if($existing['role_name']==='Administrator'&&$roleName!=='Administrator')jsonResponse(false,'You cannot remove your own Administrator role.');
    }

    if($existing['role_name']==='Administrator'&&($roleName!=='Administrator'||$status!=='Active'||$access!=='Active')){
        $count=(int)$pdo->query(
          "SELECT COUNT(*) FROM users u JOIN roles r ON r.id=u.role_id
           WHERE r.name='Administrator' AND u.status='Active' AND u.deleted_at IS NULL"
        )->fetchColumn();
        if($count<=1)jsonResponse(false,'At least one active Administrator must remain.');
    }
}

try{
    $pdo->beginTransaction();

    if($existing){
        $sql='UPDATE users SET username=:username,full_name=:name,email=:email,phone=:phone,
              role_id=:role,office_id=:office,status=:status,deleted_at=NULL,updated_at=NOW()';
        $params=[':username'=>$username?:null,':name'=>$name,':email'=>$email,':phone'=>$phone?:null,
                 ':role'=>$roleId,':office'=>$officeId,':status'=>$status,':id'=>$id];
        if($password!==''){$sql.=',password=:password';$params[':password']=password_hash($password,PASSWORD_DEFAULT);}
        $sql.=' WHERE id=:id';
        $pdo->prepare($sql)->execute($params);
        $message='User updated successfully.';
    }else{
        $pdo->prepare(
          'INSERT INTO users
           (username,full_name,email,phone,password,role_id,office_id,status,created_at,last_login_at,updated_at,deleted_at)
           VALUES(:username,:name,:email,:phone,:password,:role,:office,:status,NOW(),NULL,NOW(),NULL)'
        )->execute([
          ':username'=>$username?:null,':name'=>$name,':email'=>$email,':phone'=>$phone?:null,
          ':password'=>password_hash($password,PASSWORD_DEFAULT),':role'=>$roleId,
          ':office'=>$officeId,':status'=>$status
        ]);
        $id=(int)$pdo->lastInsertId();$message='User created successfully.';
    }

    $pdo->prepare('UPDATE user_roles SET is_primary=0 WHERE user_id=:user')->execute([':user'=>$id]);
    $pdo->prepare(
      'INSERT INTO user_roles (user_id,role_id,is_primary,assigned_by,assigned_at)
       VALUES(:user,:role,1,:by,NOW())
       ON DUPLICATE KEY UPDATE is_primary=1,assigned_by=VALUES(assigned_by),assigned_at=NOW()'
    )->execute([':user'=>$id,':role'=>$roleId,':by'=>currentUserId()]);

    $pdo->prepare(
      'INSERT INTO user_system_access
       (user_id,system_id,access_level,status,granted_by,granted_at,updated_at)
       VALUES(:user,:system,:level,:status,:by,NOW(),NOW())
       ON DUPLICATE KEY UPDATE access_level=VALUES(access_level),status=VALUES(status),
          granted_by=VALUES(granted_by),updated_at=NOW()'
    )->execute([
      ':user'=>$id,':system'=>vqdSystemId(),':level'=>$level,':status'=>$access,':by'=>currentUserId()
    ]);

    if($id===(int)currentUserId()){
        $_SESSION['full_name']=$name;$_SESSION['email']=$email;$_SESSION['role_id']=$roleId;
        $_SESSION['role_name']=$roleName;$_SESSION['role_code']=canonicalRole((string)$roleName);
    }

    vqdLogActivity(currentUserId(),$existing?'Update VQDSS User':'Create VQDSS User',
      "User #{$id} {$name}; role={$roleName}; VQDSS access={$access}.");
    $pdo->commit();
    jsonResponse(true,$message,['id'=>$id]);
}catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();
    jsonResponse(false,defined('APP_DEBUG')&&APP_DEBUG?$e->getMessage():'Unable to save user.');
}
