<?php
declare(strict_types=1);

if (!defined('APP_NAME')) {
    define('APP_NAME', 'Legislative Agenda and Calendar Management System');
}
if (!defined('APP_SHORT_NAME')) {
    define('APP_SHORT_NAME', 'LACMS');
}
if (!defined('APP_URL')) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
    $scheme = $isHttps ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    if (!empty($_SERVER['DOCUMENT_ROOT'])) {
        $docRoot = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT']));
        $targetDir = str_replace('\\', '/', realpath(__DIR__ . '/..'));
        if (str_starts_with($targetDir, $docRoot)) {
            $webPath = substr($targetDir, strlen($docRoot));
            define('APP_URL', $scheme . $host . rtrim($webPath, '/'));
        }
    }
    if (!defined('APP_URL')) {
        define('APP_URL', 'http://localhost/legislative/LACMS');
    }
}
if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}
if (!defined('SESSION_NAME')) {
    define('SESSION_NAME', 'lph_session');
}
if (!defined('SESSION_IDLE_TIMEOUT')) {
    define('SESSION_IDLE_TIMEOUT', 8 * 60 * 60);
}
if (!defined('ROLE_ADMIN')) {
    define('ROLE_ADMIN', 'ADMIN');
}
if (!defined('ROLE_STAFF')) {
    define('ROLE_STAFF', 'STAFF');
}
if (!defined('ROLE_COMMITTEE')) {
    define('ROLE_COMMITTEE', 'COMMITTEE');
}

date_default_timezone_set('Asia/Manila');


if (!defined('UPLOAD_DIR')) {
    define('UPLOAD_DIR', APP_ROOT . '/assets/uploads/');
}
if (!defined('UPLOAD_URL')) {
    define('UPLOAD_URL', rtrim(APP_URL,'/') . '/assets/uploads/');
}
if (!defined('MAX_UPLOAD_SIZE')) {
    define('MAX_UPLOAD_SIZE', 10 * 1024 * 1024);
}
if (!defined('ALLOWED_UPLOAD_EXT')) {
    define('ALLOWED_UPLOAD_EXT', ['pdf','doc','docx','png','jpg','jpeg','xlsx','xls','csv']);
}
if (!defined('DEFAULT_PAGE_SIZE')) {
    define('DEFAULT_PAGE_SIZE', 10);
}
if (!defined('APP_DEBUG')) {
    define('APP_DEBUG', true);
}

ini_set('session.gc_maxlifetime', (string)SESSION_IDLE_TIMEOUT);
ini_set('session.cookie_lifetime', (string)SESSION_IDLE_TIMEOUT);

if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

ini_set('log_errors', '1');

$logDir=APP_ROOT . '/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir,0775,true);
}
ini_set('error_log', $logDir . '/php_errors.log');
