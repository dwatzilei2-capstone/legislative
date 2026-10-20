<?php
declare(strict_types=1);

require_once __DIR__.'/../includes/auth.php';
requireLogin();

if($_SERVER['REQUEST_METHOD']!=='POST')redirect(APP_URL.'/pages/profile.php');
requireCsrf();

$current=(string)($_POST['current_password']??'');
$new=(string)($_POST['new_password']??'');
$confirm=(string)($_POST['confirm_password']??'');

$errors=[];
if($current==='')$errors[]='Current password is required.';
if(strlen($new)<10)$errors[]='New password must be at least 10 characters.';
if(!preg_match('/[A-Z]/',$new))$errors[]='New password must include uppercase.';
if(!preg_match('/[a-z]/',$new))$errors[]='New password must include lowercase.';
if(!preg_match('/[0-9]/',$new))$errors[]='New password must include a number.';
if($new!==$confirm)$errors[]='New password confirmation does not match.';
if($current===$new)$errors[]='New password must be different from the current password.';

if($errors){
    setFlash('danger',implode(' ',$errors));
    redirect(APP_URL.'/pages/profile.php');
}

$pdo=db();
$q=$pdo->prepare('SELECT password FROM users WHERE id=:id AND deleted_at IS NULL');
$q->execute([':id'=>currentUserId()]);$hash=$q->fetchColumn();

if(!$hash||!password_verify($current,(string)$hash)){
    logActivity(currentUserId(),'ORLMS Password Change Failed','Current password verification failed.');
    setFlash('danger','Current password is incorrect.');
    redirect(APP_URL.'/pages/profile.php');
}

$pdo->prepare(
 'UPDATE users SET password=:password,updated_at=NOW() WHERE id=:id'
)->execute([
    ':password'=>password_hash($new,PASSWORD_DEFAULT),
    ':id'=>currentUserId()
]);

$_SESSION['csrf_token']=bin2hex(random_bytes(32));
session_regenerate_id(true);
$_SESSION['session_rotated_at']=time();

logActivity(currentUserId(),'ORLMS Password Changed','Shared account password changed from the ORLMS profile page.');
setFlash('success','Password changed successfully.');
redirect(APP_URL.'/pages/profile.php');
