<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

if($_SERVER['REQUEST_METHOD']!=='POST'){
    redirect(appUrl('login.php'));
}

if(!verifyCsrfToken($_POST['csrf_token']??null)){
    setFlash('danger','Your login form expired. Please try again.');
    redirect(appUrl('login.php'));
}

$email=strtolower(trim((string)($_POST['email']??'')));
$password=(string)($_POST['password']??'');

if($email===''||$password===''){
    setFlash('warning','Enter both your email address and password.');
    redirect(appUrl('login.php'));
}

$pdo=db();
$ip=(string)($_SERVER['REMOTE_ADDR']??'');

if(lacmsLoginRateLimited($email,$ip)){
    lacmsLogActivity(null,'LACMS Login Rate Limited','Temporary sign-in block triggered by repeated failed attempts.');
    setFlash('danger','Too many failed sign-in attempts. Try again in about 15 minutes.');
    redirect(appUrl('login.php'));
}

try{
    $stmt=$pdo->prepare(
        'SELECT
            u.id,u.full_name,u.email,u.password,u.role_id,u.status,
            u.deleted_at,r.name role_name
         FROM users u
         LEFT JOIN roles r ON r.id=u.role_id
         WHERE LOWER(u.email)=:email
           AND u.deleted_at IS NULL
         LIMIT 1'
    );
    $stmt->execute([':email'=>$email]);
    $user=$stmt->fetch();

    if(!$user || !password_verify($password,(string)$user['password'])){
        lacmsRecordLoginAttempt($email,$ip,false);
        lacmsLogActivity(null,'LACMS Login Failed','Invalid sign-in attempt.');
        setFlash('danger','Invalid email address or password.');
        redirect(appUrl('login.php'));
    }

    if(strcasecmp((string)$user['status'],'Active')!==0){
        lacmsRecordLoginAttempt($email,$ip,false);
        lacmsLogActivity((int)$user['id'],'LACMS Login Blocked','Inactive shared account attempted sign-in.');
        setFlash('danger','This account is inactive or unavailable.');
        redirect(appUrl('login.php'));
    }

    if(!lacmsHasSystemAccess((int)$user['id'])){
        lacmsRecordLoginAttempt($email,$ip,false);
        lacmsLogActivity((int)$user['id'],'LACMS Access Denied','Shared user does not have active system access.');
        setFlash('danger','Your account does not currently have access to LACMS.');
        redirect(appUrl('login.php'));
    }

    lacmsRecordLoginAttempt($email,$ip,true);
    session_regenerate_id(true);
    $roleName=(string)($user['role_name']??'');

    $_SESSION['user_id']=(int)$user['id'];
    $_SESSION['full_name']=(string)$user['full_name'];
    $_SESSION['email']=(string)$user['email'];
    $_SESSION['role_id']=(int)$user['role_id'];
    $_SESSION['role']=$roleName;
    $_SESSION['logged_in']=true;
    $_SESSION['last_activity']=time();
    $_SESSION['session_rotated_at']=time();
    $_SESSION['csrf_token']=bin2hex(random_bytes(32));
    $_SESSION['user']=[
        'id'=>(int)$user['id'],
        'full_name'=>(string)$user['full_name'],
        'email'=>(string)$user['email'],
        'role_id'=>(int)$user['role_id'],
        'role'=>$roleName,
    ];

    $pdo->prepare('UPDATE users SET last_login_at=NOW() WHERE id=:id')
        ->execute([':id'=>(int)$user['id']]);

    unset($_SESSION['auth_expired']);

    lacmsLogActivity(
        (int)$user['id'],
        'LACMS Login',
        'Successful LACMS sign-in.'
    );

    setFlash('success','Welcome to LACMS.');
    redirect(appUrl('dashboard.php'));
}catch(Throwable $e){
    error_log('[LACMS Login] '.$e->getMessage());
    setFlash('danger',APP_DEBUG
        ? 'Login error: '.$e->getMessage()
        : 'Unable to sign in right now.');
    redirect(appUrl('login.php'));
}
