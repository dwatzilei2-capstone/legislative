<?php
/**
 * Shared utility functions for LACMS.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';

/**
 * Escape output for HTML.
 */
function e(mixed $value): string
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

/**
 * Build a URL within LACMS.
 */
function appUrl(string $path = ''): string
{
    $path = trim($path);

    if ($path === '') {
        return APP_URL;
    }

    return rtrim(APP_URL, '/') . '/' . ltrim($path, '/');
}

/**
 * Redirect and stop execution.
 */
function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

/**
 * Read the current authenticated user.
 */
function currentUser(): array
{
    $nestedUser = $_SESSION['user'] ?? [];

    if (!is_array($nestedUser)) {
        $nestedUser = [];
    }

    return [
        'id' => (int)(
            $_SESSION['user_id']
            ?? $nestedUser['id']
            ?? 0
        ),
        'full_name' => (string)(
            $_SESSION['full_name']
            ?? $nestedUser['full_name']
            ?? ''
        ),
        'email' => (string)(
            $_SESSION['email']
            ?? $nestedUser['email']
            ?? ''
        ),
        'role_id' => (int)(
            $_SESSION['role_id']
            ?? $nestedUser['role_id']
            ?? 0
        ),
        'role' => (string)(
            $_SESSION['role']
            ?? $nestedUser['role']
            ?? ''
        ),
    ];
}

function currentUserId(): int
{
    return currentUser()['id'];
}

function currentRole(): string
{
    return currentUser()['role'];
}

function isAdmin(): bool
{
    return normalizeRole(currentRole()) === normalizeRole(ROLE_ADMIN);
}

/**
 * Normalize roles such as ROLE_ADMIN, admin, and Administrator.
 */
function normalizeRole(string $role): string
{
    $role = strtoupper(trim($role));
    $role = preg_replace('/^ROLE_/', '', $role) ?? $role;
    $role = str_replace([' ', '-'], '_', $role);

    if (in_array($role, ['ADMINISTRATOR', 'SYSTEM_ADMIN'], true)) {
        return 'ADMIN';
    }

    if (in_array($role, ['LEGISLATIVE_STAFF', 'SECRETARIAT'], true)) {
        return 'STAFF';
    }

    if (in_array($role, ['COMMITTEE_MEMBER', 'COMMITTEE_CHAIR'], true)) {
        return 'COMMITTEE';
    }

    return $role;
}

/**
 * Return a user-friendly role label for the layout.
 *
 * Examples:
 * ADMIN                -> System Administrator
 * ROLE_ADMIN           -> System Administrator
 * STAFF                -> Legislative Staff
 * COMMITTEE            -> Committee Member
 * EXECUTIVE_LIAISON    -> Executive Liaison
 */
function orlmsFriendlyRole(string $role): string
{
    $normalized = normalizeRole($role);

    $labels = [
        'ADMIN'     => 'System Administrator',
        'STAFF'     => 'Legislative Staff',
        'COMMITTEE' => 'Committee Member',
    ];

    if (isset($labels[$normalized])) {
        return $labels[$normalized];
    }

    if ($normalized === '') {
        return 'Legislative User';
    }

    return ucwords(
        strtolower(
            str_replace('_', ' ', $normalized)
        )
    );
}

/**
 * Create or return the current CSRF token.
 */
function csrfToken(): string
{
    if (
        empty($_SESSION['csrf_token']) ||
        !is_string($_SESSION['csrf_token'])
    ) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/**
 * Validate a submitted CSRF token.
 */
function verifyCsrfToken(?string $token): bool
{
    $sessionToken = $_SESSION['csrf_token'] ?? '';

    return is_string($token)
        && is_string($sessionToken)
        && $token !== ''
        && hash_equals($sessionToken, $token);
}

/**
 * Store a one-time message.
 */
function setFlash(string $type, string $message): void
{
    $_SESSION['flash'] = [
        'type'    => $type,
        'message' => $message,
    ];
}

/**
 * Retrieve and remove the current one-time message.
 */
function getFlash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);

    return is_array($flash) ? $flash : null;
}

function formatDate(?string $value): string
{
    if (!$value) {
        return '—';
    }

    $timestamp = strtotime($value);

    return $timestamp === false
        ? (string)$value
        : date('M d, Y', $timestamp);
}

function formatDateTime(?string $value): string
{
    if (!$value) {
        return '—';
    }

    $timestamp = strtotime($value);

    return $timestamp === false
        ? (string)$value
        : date('M d, Y h:i A', $timestamp);
}

/**
 * Prefer a local vendor asset, otherwise use its CDN fallback.
 */
function vendorAsset(
    string $relativePath,
    string $fallbackUrl
): string {
    $relativePath = ltrim($relativePath, '/');
    $localFile = APP_ROOT . '/assets/vendor/' . $relativePath;

    return is_file($localFile)
        ? appUrl('assets/vendor/' . $relativePath)
        : $fallbackUrl;
}


function clean(mixed $value): string
{
    return trim((string)$value);
}

function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' .
        e(csrfToken()) . '">';
}

function isAjaxRequest(): bool
{
    return strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''))
        === 'xmlhttprequest';
}

function jsonResponse(
    bool $success,
    string $message='',
    array $data=[],
    int $status=200
): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');

    echo json_encode(
        array_merge(
            ['success'=>$success,'message'=>$message],
            $data
        ),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

function requireCsrf(): void
{
    if(verifyCsrfToken($_POST['csrf_token'] ?? null))return;

    if(isAjaxRequest()){
        jsonResponse(false,'Your form session expired. Refresh the page and try again.',[],419);
    }

    http_response_code(419);
    exit('Invalid or expired CSRF token.');
}

function formatTime(?string $value): string
{
    if(!$value)return '—';
    $timestamp=strtotime($value);
    return $timestamp===false?(string)$value:date('h:i A',$timestamp);
}

function paginate(int $total,int $page,int $perPage=DEFAULT_PAGE_SIZE): array
{
    $perPage=max(1,$perPage);
    $pages=max(1,(int)ceil($total/$perPage));
    $page=max(1,min($page,$pages));

    return [
        'page'=>$page,
        'perPage'=>$perPage,
        'total'=>$total,
        'pages'=>$pages,
        'offset'=>($page-1)*$perPage,
    ];
}

function renderPagination(array $info,string $baseUrl,array $query=[]): string
{
    $pages=(int)($info['pages']??1);
    $page=(int)($info['page']??1);
    if($pages<=1)return '';

    $html='<nav><ul class="pagination pagination-sm mb-0">';

    for($i=1;$i<=$pages;$i++){
        $q=$query;
        $q['page']=$i;
        $url=$baseUrl.(str_contains($baseUrl,'?')?'&':'?').http_build_query($q);
        $html.='<li class="page-item '.($i===$page?'active':'').'">' .
            '<a class="page-link" href="'.e($url).'">'.$i.'</a></li>';
    }

    return $html.'</ul></nav>';
}

function handleUpload(array $file,string $subDirectory='lacms'): array
{
    $error=(int)($file['error']??UPLOAD_ERR_NO_FILE);

    if($error!==UPLOAD_ERR_OK){
        return ['success'=>false,'message'=>'The selected file could not be uploaded.'];
    }

    $size=(int)($file['size']??0);
    if($size<=0 || $size>MAX_UPLOAD_SIZE){
        return ['success'=>false,'message'=>'File exceeds the allowed upload size.'];
    }

    $original=(string)($file['name']??'document');
    $extension=strtolower(pathinfo($original,PATHINFO_EXTENSION));

    if(!in_array($extension,ALLOWED_UPLOAD_EXT,true)){
        return ['success'=>false,'message'=>'This file type is not allowed.'];
    }

    $safeBase=preg_replace('/[^A-Za-z0-9._-]/','_',pathinfo($original,PATHINFO_FILENAME));
    $safeBase=trim((string)$safeBase,'._-') ?: 'document';
    $stored=$safeBase.'_'.bin2hex(random_bytes(8)).'.'.$extension;

    $folder=trim($subDirectory,'/').'/'.date('Y/m');
    $targetDir=rtrim(UPLOAD_DIR,'/\\').DIRECTORY_SEPARATOR.str_replace('/',DIRECTORY_SEPARATOR,$folder);

    if(!is_dir($targetDir) && !mkdir($targetDir,0775,true) && !is_dir($targetDir)){
        return ['success'=>false,'message'=>'Unable to create the upload folder.'];
    }

    $target=$targetDir.DIRECTORY_SEPARATOR.$stored;

    if(!move_uploaded_file((string)$file['tmp_name'],$target)){
        return ['success'=>false,'message'=>'Unable to save the uploaded file.'];
    }

    return [
        'success'=>true,
        'file_name'=>$original,
        'stored_name'=>$stored,
        'file_path'=>$folder.'/'.$stored,
        'size'=>$size,
    ];
}
