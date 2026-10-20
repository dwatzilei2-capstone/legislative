<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

if($_SERVER['REQUEST_METHOD']!=='POST'){
    redirect(appUrl('login.php'));
}

requireCsrf();

$email=strtolower(trim((string)($_POST['email']??'')));
$password=(string)($_POST['password']??'');

if(!filter_var($email,FILTER_VALIDATE_EMAIL)||$password===''){
    setFlash('warning','Enter a valid email address and password.');
    redirect(appUrl('login.php'));
}

$rate=vqdLoginRateLimitStatus($email);

if($rate['blocked']){
    $minutes=max(1,(int)ceil($rate['retry_after_seconds']/60));
    vqdLogActivity(null,'VQDSS Login Rate Limited','Authentication temporarily blocked after repeated unsuccessful attempts.');
    setFlash('danger',"Too many unsuccessful sign-in attempts. Please wait about {$minutes} minute(s) before trying again.");
    redirect(appUrl('login.php'));
}

try{
    $stmt=db()->prepare(
      'SELECT u.id,u.full_name,u.email,u.password,u.status,u.role_id,
              u.deleted_at,r.name role_name
       FROM users u
       JOIN roles r ON r.id=u.role_id
       WHERE LOWER(u.email)=LOWER(:email)
         AND u.deleted_at IS NULL
       LIMIT 1'
    );
    $stmt->execute([':email'=>$email]);
    $user=$stmt->fetch();

    if(!$user || !password_verify($password,(string)$user['password'])){
        vqdRecordLoginAttempt($email,false);
        vqdLogActivity(null,'VQDSS Login Failed','Invalid VQDSS sign-in credentials were supplied.');
        setFlash('danger','The email or password is incorrect.');
        redirect(appUrl('login.php'));
    }

    if(strcasecmp((string)$user['status'],'Active')!==0){
        vqdRecordLoginAttempt($email,false);
        setFlash('danger','Your account is currently inactive. Please contact the administrator.');
        redirect(appUrl('login.php'));
    }

    if(!vqdHasSystemAccess((int)$user['id'])){
        vqdRecordLoginAttempt($email,false);
        vqdLogActivity((int)$user['id'],'VQDSS Access Denied','Authenticated user has inactive or missing explicit VQDSS access.');
        setFlash('danger','Your account does not currently have access to VQDSS.');
        redirect(appUrl('login.php'));
    }

    session_regenerate_id(true);

    $_SESSION['user_id']=(int)$user['id'];
    $_SESSION['full_name']=(string)$user['full_name'];
    $_SESSION['email']=(string)$user['email'];
    $_SESSION['role_id']=(int)$user['role_id'];
    $_SESSION['role_name']=(string)$user['role_name'];
    $_SESSION['role_code']=canonicalRole((string)$user['role_name']);
    $_SESSION['user']=[
        'id'=>(int)$user['id'],
        'full_name'=>(string)$user['full_name'],
        'email'=>(string)$user['email'],
        'role_id'=>(int)$user['role_id'],
        'role_name'=>(string)$user['role_name'],
    ];
    $_SESSION['last_activity']=time();
    $_SESSION['csrf_token']=bin2hex(random_bytes(32));

    db()->prepare(
      'UPDATE users SET last_login_at=NOW(),updated_at=NOW() WHERE id=:id'
    )->execute([':id'=>$user['id']]);

    vqdRecordLoginAttempt($email,true);
    vqdLogActivity((int)$user['id'],'VQDSS Login','User signed in successfully to VQDSS.');

    setFlash('success','Welcome back, '.$user['full_name'].'!');
    redirect(appUrl('dashboard.php'));
}catch(Throwable $e){
    error_log('[VQDSS Login] '.$e->getMessage());
    setFlash('danger',defined('APP_DEBUG')&&APP_DEBUG
        ? 'Login error: '.$e->getMessage()
        : 'VQDSS could not complete sign-in. Please try again.');
    redirect(appUrl('login.php'));
}
