<?php
declare(strict_types=1);

const APP_NAME = 'Voting, Quorum, and Decision Support System';
const APP_SHORT_NAME = 'VQDSS';
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
$detectedAppUrl = '/legislative/vqdss';
if (!empty($_SERVER['DOCUMENT_ROOT'])) {
    $docRoot = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT']));
    $targetDir = str_replace('\\', '/', realpath(__DIR__ . '/..'));
    if (str_starts_with($targetDir, $docRoot)) {
        $detectedAppUrl = rtrim(substr($targetDir, strlen($docRoot)), '/');
    }
}
if (!defined('APP_URL')) {
    define('APP_URL', $detectedAppUrl);
}

define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_DATABASE') ?: (getenv('DB_NAME') ?: 'legislative_management_db'));
define('DB_USER', getenv('DB_USERNAME') ?: (getenv('DB_USER') ?: 'root'));
define('DB_PASS', getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : (getenv('DB_PASS') !== false ? getenv('DB_PASS') : ''));

const SHARED_SESSION_NAME = 'lph_session';
const SESSION_IDLE_TIMEOUT = 28800; // 8 hours

const ROLE_ADMIN = 'admin';
const ROLE_STAFF = 'staff';
const ROLE_COMMITTEE = 'committee';

date_default_timezone_set('Asia/Manila');
