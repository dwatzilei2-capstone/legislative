<?php
declare(strict_types=1);

$sharedSessionName = defined('SHARED_SESSION_NAME')
    ? SHARED_SESSION_NAME
    : 'lph_session';

$sessionLifetime = 8 * 60 * 60;

if (session_status() === PHP_SESSION_NONE) {
    $isHttps =
        (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
        || (int)($_SERVER['SERVER_PORT'] ?? 0) === 443;

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.gc_maxlifetime', (string)$sessionLifetime);

    session_name($sharedSessionName);
    session_set_cookie_params([
        'lifetime' => $sessionLifetime,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

if (!empty($_SESSION['last_activity'])) {
    $inactive = time() - (int)$_SESSION['last_activity'];

    if ($inactive > $sessionLifetime) {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();

            setcookie(
                session_name(),
                '',
                [
                    'expires' => time() - 42000,
                    'path' => $params['path'],
                    'domain' => $params['domain'],
                    'secure' => (bool)$params['secure'],
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]
            );
        }

        session_destroy();
    } else {
        $_SESSION['last_activity'] = time();
    }
}
