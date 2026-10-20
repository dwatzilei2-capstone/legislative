<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';

function e(mixed $value): string
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

function appUrl(string $path = ''): string
{
    $base = rtrim(APP_URL, '/');
    $path = ltrim($path, '/');

    return $path === ''
        ? $base . '/'
        : $base . '/' . $path;
}

function vendorAsset(
    string $localPath,
    string $cdnFallback
): string {
    $fullPath = dirname(__DIR__) .
        '/assets/vendor/' .
        ltrim($localPath, '/');

    return is_file($fullPath)
        ? appUrl('assets/vendor/' . ltrim($localPath, '/'))
        : $cdnFallback;
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function setFlash(string $type, string $message): void
{
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message,
    ];
}

function getFlash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);

    return is_array($flash) ? $flash : null;
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return (string)$_SESSION['csrf_token'];
}

function verifyCsrfToken(?string $token): bool
{
    return is_string($token)
        && isset($_SESSION['csrf_token'])
        && hash_equals(
            (string)$_SESSION['csrf_token'],
            $token
        );
}

function tableExists(string $table): bool
{
    static $cache = [];

    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }

    try {
        $stmt = db()->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name LIMIT 1'
        );

        $stmt->execute([
            ':table_name' => $table,
        ]);

        $cache[$table] = (bool)$stmt->fetchColumn();
    } catch (Throwable $exception) {
        error_log(
            '[VQDSS tableExists] ' .
            $exception->getMessage()
        );

        $cache[$table] = false;
    }

    return $cache[$table];
}

function formatDate(?string $date): string
{
    if (!$date) {
        return 'Not recorded';
    }

    $timestamp = strtotime($date);

    return $timestamp
        ? date('M j, Y', $timestamp)
        : $date;
}

function formatDateTime(?string $date): string
{
    if (!$date) {
        return 'Not recorded';
    }

    $timestamp = strtotime($date);

    return $timestamp
        ? date('M j, Y g:i A', $timestamp)
        : $date;
}

function countTableRows(string $table): int
{
    if (!tableExists($table)) {
        return 0;
    }

    try {
        return (int)db()
            ->query("SELECT COUNT(*) FROM `$table`")
            ->fetchColumn();
    } catch (Throwable $exception) {
        error_log(
            '[VQDSS countTableRows] ' .
            $exception->getMessage()
        );

        return 0;
    }
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
    array $data=[]
): never {
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=UTF-8');
    }

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
    $token = $_POST['csrf_token']
        ?? $_SERVER['HTTP_X_CSRF_TOKEN']
        ?? null;

    if (!verifyCsrfToken(is_string($token) ? $token : null)) {
        if (isAjaxRequest()) {
            jsonResponse(false,'Your session token is invalid. Refresh the page and try again.');
        }

        setFlash('danger','Your session token is invalid. Please try again.');
        redirect(appUrl('dashboard.php'));
    }
}

function formatTime(?string $time): string
{
    if (!$time) {
        return 'Not recorded';
    }

    $timestamp=strtotime($time);
    return $timestamp ? date('g:i A',$timestamp) : $time;
}

function paginate(int $totalRows, int $perPage=15): array
{
    $perPage=max(1,min(100,$perPage));
    $totalPages=max(1,(int)ceil($totalRows/$perPage));
    $page=max(1,min($totalPages,(int)($_GET['page'] ?? 1)));

    return [
        'page'=>$page,
        'perPage'=>$perPage,
        'totalRows'=>$totalRows,
        'totalPages'=>$totalPages,
        'offset'=>($page-1)*$perPage,
    ];
}

function paginationLinks(array $pageInfo, array $query=[]): string
{
    if (($pageInfo['totalPages'] ?? 1) <= 1) {
        return '';
    }

    $page=(int)$pageInfo['page'];
    $pages=(int)$pageInfo['totalPages'];
    $html='<nav aria-label="Pagination"><ul class="pagination pagination-sm mb-0">';

    for ($i=max(1,$page-2);$i<=min($pages,$page+2);$i++) {
        $params=$query;
        $params['page']=$i;
        $active=$i===$page ? ' active' : '';
        $html.='<li class="page-item'.$active.'"><a class="page-link" href="?'.
            e(http_build_query($params)).'">'.$i.'</a></li>';
    }

    return $html.'</ul></nav>';
}

function handleUpload(array $file, string $subfolder='vqdss'): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['success'=>false,'message'=>'The uploaded file could not be processed.'];
    }

    $maxBytes=10*1024*1024;
    if ((int)($file['size'] ?? 0) <= 0 || (int)$file['size'] > $maxBytes) {
        return ['success'=>false,'message'=>'Files must be larger than 0 bytes and no more than 10 MB.'];
    }

    $allowed=[
        'pdf'=>'application/pdf',
        'doc'=>'application/msword',
        'docx'=>'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls'=>'application/vnd.ms-excel',
        'xlsx'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'png'=>'image/png',
        'jpg'=>'image/jpeg',
        'jpeg'=>'image/jpeg',
    ];

    $original=basename((string)($file['name'] ?? 'file'));
    $ext=strtolower(pathinfo($original,PATHINFO_EXTENSION));

    if (!isset($allowed[$ext])) {
        return ['success'=>false,'message'=>'Unsupported file type.'];
    }

    $finfo=new finfo(FILEINFO_MIME_TYPE);
    $mime=(string)$finfo->file((string)$file['tmp_name']);

    $acceptedMimes=[
        $allowed[$ext],
        'application/octet-stream',
        'application/zip',
    ];

    if (!in_array($mime,$acceptedMimes,true)) {
        return ['success'=>false,'message'=>'The uploaded file content does not match an allowed document type.'];
    }

    $safeFolder=preg_replace('/[^a-zA-Z0-9_-]+/','_',$subfolder) ?: 'vqdss';
    $relative=$safeFolder.'/'.date('Y/m');
    $baseDir=dirname(__DIR__).'/assets/uploads/'.$relative;

    if (!is_dir($baseDir) && !mkdir($baseDir,0775,true) && !is_dir($baseDir)) {
        return ['success'=>false,'message'=>'The upload directory could not be created.'];
    }

    $stored=date('YmdHis').'_'.bin2hex(random_bytes(6)).'.'.$ext;
    $target=$baseDir.'/'.$stored;

    if (!move_uploaded_file((string)$file['tmp_name'],$target)) {
        return ['success'=>false,'message'=>'The uploaded file could not be saved.'];
    }

    return [
        'success'=>true,
        'message'=>'File uploaded successfully.',
        'file_name'=>$original,
        'file_path'=>$relative.'/'.$stored,
        'mime_type'=>$mime,
        'size_bytes'=>(int)$file['size'],
    ];
}

