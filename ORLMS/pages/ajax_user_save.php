<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
requirePermission('orlms.users.manage');

if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(false,'Invalid request method.');
requireCsrf();

$pdo=db();$id=(int)($_POST['id']??0);
$name=clean($_POST['full_name']??'');
$username=clean($_POST['username']??'');
$email=strtolower(clean($_POST['email']??''));
$phone=clean($_POST['phone']??'');
$roleId=(int)($_POST['role_id']??0);
$officeId=(int)($_POST['office_id']??0)?:null;
$departmentId=(int)($_POST['department_id']??0)?:null;
$status=clean($_POST['status']??'Active');
$access=clean($_POST['orlms_access_status']??'Active');
$level=clean($_POST['access_level']??'Standard');
$password=(string)($_POST['password']??'');

$errors=[];
if($name==='')$errors[]='Full name is required.';
if($email===''||!filter_var($email,FILTER_VALIDATE_EMAIL))$errors[]='A valid email is required.';
if($roleId<=0)$errors[]='Select a role.';
if(!in_array($status,['Active','Inactive'],true))$errors[]='Invalid account status.';
if(!in_array($access,['Active','Inactive'],true))$errors[]='Invalid ORLMS access status.';
if(!in_array($level,['Administrator','Staff','Committee','Stakeholder','Standard'],true))$errors[]='Invalid access level.';
if($id===0&&$password==='')$errors[]='Password is required for a new user.';
if($password!==''){
    if(strlen($password)<10)$errors[]='Password must be at least 10 characters.';
    if(!preg_match('/[A-Z]/',$password))$errors[]='Password must include uppercase.';
    if(!preg_match('/[a-z]/',$password))$errors[]='Password must include lowercase.';
    if(!preg_match('/[0-9]/',$password))$errors[]='Password must include a number.';
}
if($errors)jsonResponse(false,implode(' ',$errors));

$role=$pdo->prepare('SELECT name FROM roles WHERE id=:id');
$role->execute([':id'=>$roleId]);$roleName=$role->fetchColumn();
if(!$roleName)jsonResponse(false,'Selected role does not exist.');

if($officeId){
    $q=$pdo->prepare("SELECT COUNT(*) FROM offices WHERE id=:id AND status='Active'");
    $q->execute([':id'=>$officeId]);
    if((int)$q->fetchColumn()===0)jsonResponse(false,'Selected office is invalid.');
}
if($departmentId){
    $q=$pdo->prepare("SELECT office_id FROM departments WHERE id=:id AND status='Active'");
    $q->execute([':id'=>$departmentId]);$departmentOffice=$q->fetchColumn();
    if($departmentOffice===false)jsonResponse(false,'Selected department is invalid.');
    if($officeId && $departmentOffice && (int)$departmentOffice!==$officeId){
        jsonResponse(false,'Selected department does not belong to the selected office.');
    }
}

$dup=$pdo->prepare(
 'SELECT id FROM users
  WHERE deleted_at IS NULL AND id<>:id
    AND (LOWER(email)=LOWER(:email)
      OR (:username_present<>"" AND LOWER(username)=LOWER(:username_match)))
  LIMIT 1'
);
$dup->execute([':id'=>$id,':email'=>$email,':username_present'=>$username,':username_match'=>$username]);
if($dup->fetch())jsonResponse(false,'Another active account already uses that email or username.');

$existing=null;
if($id>0){
    $q=$pdo->prepare(
      'SELECT u.*,r.name role_name
       FROM users u JOIN roles r ON r.id=u.role_id
       WHERE u.id=:id AND u.deleted_at IS NULL'
    );
    $q->execute([':id'=>$id]);$existing=$q->fetch();
    if(!$existing)jsonResponse(false,'User not found.');

    if($id===(int)currentUserId()){
        if($status!=='Active'||$access!=='Active'){
            jsonResponse(false,'You cannot deactivate your own shared account or ORLMS access.');
        }
        if($existing['role_name']==='Administrator'&&$roleName!=='Administrator'){
            jsonResponse(false,'You cannot remove your own Administrator role.');
        }
    }

    if($existing['role_name']==='Administrator'
        && ($roleName!=='Administrator'||$status!=='Active')){
        $count=(int)$pdo->query(
          "SELECT COUNT(*)
           FROM users u JOIN roles r ON r.id=u.role_id
           WHERE r.name='Administrator'
             AND u.status='Active'
             AND u.deleted_at IS NULL"
        )->fetchColumn();
        if($count<=1)jsonResponse(false,'At least one active Administrator must remain.');
    }

    if($existing['role_name']==='Administrator'
        && ($roleName!=='Administrator'||$status!=='Active'||$access!=='Active')){
        $adminAccess=$pdo->prepare(
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
        $adminAccess->execute([':system'=>orlmsSystemId(),':id'=>$id]);
        if((int)$adminAccess->fetchColumn()<=0){
            jsonResponse(false,'At least one other active Administrator with ORLMS access must remain.');
        }
    }
}

try{
    $pdo->beginTransaction();

    if($existing){
        $sql='UPDATE users
              SET username=:username,full_name=:name,email=:email,phone=:phone,
                  role_id=:role,office_id=:office,department_id=:department,
                  status=:status,deleted_at=NULL,updated_at=NOW()';
        $params=[
            ':username'=>$username?:null,':name'=>$name,':email'=>$email,
            ':phone'=>$phone?:null,':role'=>$roleId,':office'=>$officeId,
            ':department'=>$departmentId,':status'=>$status,':id'=>$id
        ];
        if($password!==''){
            $sql.=',password=:password';
            $params[':password']=password_hash($password,PASSWORD_DEFAULT);
        }
        $sql.=' WHERE id=:id';
        $pdo->prepare($sql)->execute($params);
        $message='User updated successfully.';
    }else{
        $pdo->prepare(
          'INSERT INTO users
           (username,full_name,email,phone,password,role_id,office_id,department_id,
            status,created_at,last_login_at,updated_at,deleted_at)
           VALUES(:username,:name,:email,:phone,:password,:role,:office,:department,
            :status,NOW(),NULL,NOW(),NULL)'
        )->execute([
            ':username'=>$username?:null,':name'=>$name,':email'=>$email,
            ':phone'=>$phone?:null,':password'=>password_hash($password,PASSWORD_DEFAULT),
            ':role'=>$roleId,':office'=>$officeId,':department'=>$departmentId,
            ':status'=>$status
        ]);
        $id=(int)$pdo->lastInsertId();
        $message='User created successfully.';
    }

    $pdo->prepare('UPDATE user_roles SET is_primary=0 WHERE user_id=:user')
        ->execute([':user'=>$id]);
    $pdo->prepare(
      'INSERT INTO user_roles
       (user_id,role_id,is_primary,assigned_by,assigned_at)
       VALUES(:user,:role,1,:by,NOW())
       ON DUPLICATE KEY UPDATE
          is_primary=1,assigned_by=VALUES(assigned_by),assigned_at=NOW()'
    )->execute([':user'=>$id,':role'=>$roleId,':by'=>currentUserId()]);

    $pdo->prepare(
      'INSERT INTO user_system_access
       (user_id,system_id,access_level,status,granted_by,granted_at,updated_at)
       VALUES(:user,:system,:level,:status,:by,NOW(),NOW())
       ON DUPLICATE KEY UPDATE
          access_level=VALUES(access_level),status=VALUES(status),
          granted_by=VALUES(granted_by),updated_at=NOW()'
    )->execute([
        ':user'=>$id,':system'=>orlmsSystemId(),':level'=>$level,
        ':status'=>$access,':by'=>currentUserId()
    ]);

    if($id===(int)currentUserId()){
        $_SESSION['full_name']=$name;$_SESSION['email']=$email;
        $_SESSION['role_id']=$roleId;$_SESSION['role_name']=$roleName;
    }

    logActivity(currentUserId(),$existing?'Update ORLMS User':'Create ORLMS User',
        "User #{$id} {$name}; role={$roleName}; ORLMS access={$access}.");

    $pdo->commit();
    jsonResponse(true,$message,['id'=>$id]);
}catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();
    jsonResponse(false,APP_DEBUG?$e->getMessage():'Unable to save user.');
}
