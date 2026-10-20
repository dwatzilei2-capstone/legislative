<?php
declare(strict_types=1);

if(ob_get_level()===0){
    ob_start();
}

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/lacms_helpers.php';
require_once __DIR__ . '/lacms_security.php';

if(!headers_sent()){
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header("Permissions-Policy: camera=(), microphone=(), geolocation=()");
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com; font-src 'self' https://cdn.jsdelivr.net https://fonts.gstatic.com data:; img-src 'self' data: blob:; connect-src 'self' http://127.0.0.1:11434 http://localhost:11434; frame-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'");
}

function startSharedSession(): void
{
    if(session_status()===PHP_SESSION_ACTIVE)return;

    ini_set('session.use_strict_mode','1');
    ini_set('session.use_only_cookies','1');

    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime'=>0,
        'path'=>'/',
        'domain'=>'',
        'secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off',
        'httponly'=>true,
        'samesite'=>'Lax',
    ]);
    session_start();
}

startSharedSession();

$lastActivity=(int)($_SESSION['last_activity']??0);

if($lastActivity>0 && (time()-$lastActivity)>SESSION_IDLE_TIMEOUT){
    $_SESSION=[];

    if(ini_get('session.use_cookies')){
        $cookie=session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time()-42000,
            $cookie['path'],
            $cookie['domain'],
            $cookie['secure'],
            $cookie['httponly']
        );
    }

    session_destroy();
    session_start();
    $_SESSION['auth_expired']=true;
}

if(currentUserId()>0){
    $_SESSION['last_activity']=time();
    $lastRotation=(int)($_SESSION['session_rotated_at']??0);
    if($lastRotation===0 || (time()-$lastRotation)>=1800){
        session_regenerate_id(true);
        $_SESSION['session_rotated_at']=time();
    }
}

function isAuthenticated(): bool
{
    return currentUserId()>0;
}

function requireLogin(): void
{
    if(isAuthenticated()){
        if(!lacmsHasSystemAccess(currentUserId())){
            lacmsLogActivity(
                currentUserId(),
                'LACMS Access Denied',
                'Authenticated shared account does not have active LACMS system access.'
            );

            if(isAjaxRequest()){
                jsonResponse(false,'Your account does not have active LACMS access.',[],403);
            }

            http_response_code(403);
            exit(
                '<h1>403 - LACMS Access Denied</h1>' .
                '<p>Your shared account does not currently have access to the Legislative Agenda and Calendar Management System.</p>'
            );
        }

        return;
    }

    if(isAjaxRequest()){
        jsonResponse(false,'Your session has expired. Sign in again.',[
            'session_expired'=>true,
            'login_url'=>appUrl('login.php')
        ],401);
    }

    $query=!empty($_SESSION['auth_expired'])?'?expired=1':'';
    redirect(appUrl('login.php'.$query));
}

function requireRole(array $allowedRoles): void
{
    requireLogin();

    $current=normalizeRole(currentRole());
    $allowed=array_map(
        static fn(mixed $role): string => normalizeRole((string)$role),
        $allowedRoles
    );

    if(in_array($current,$allowed,true))return;

    lacmsLogActivity(
        currentUserId(),
        'LACMS Role Denied',
        'Role '.$current.' attempted a restricted LACMS action.'
    );

    if(isAjaxRequest()){
        jsonResponse(false,'You do not have permission to perform this LACMS action.',[],403);
    }

    http_response_code(403);
    exit(
        '<h1>403 - Access Denied</h1>' .
        '<p>Your account does not have permission to access this page.</p>' .
        '<p><a href="'.e(appUrl('dashboard.php')).'">Return to dashboard</a></p>'
    );
}

function canManage(): bool
{
    return in_array(
        normalizeRole(currentRole()),
        [normalizeRole(ROLE_ADMIN),normalizeRole(ROLE_STAFF)],
        true
    );
}
