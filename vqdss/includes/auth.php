<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/vqdss_helpers.php';

if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(self), microphone=(), geolocation=()');
}

function canonicalRole(?string $role): string
{
    $normalized = strtolower(
        trim((string)$role)
    );

    $normalized = preg_replace(
        '/[^a-z0-9]+/',
        ' ',
        $normalized
    ) ?? '';

    if (str_contains($normalized, 'admin')) {
        return ROLE_ADMIN;
    }

    if (str_contains($normalized, 'committee')) {
        return ROLE_COMMITTEE;
    }

    if (
        str_contains($normalized, 'staff') ||
        str_contains($normalized, 'secretariat') ||
        str_contains($normalized, 'clerk')
    ) {
        return ROLE_STAFF;
    }

    return trim($normalized);
}

function currentUserId(): ?int
{
    $value =
        $_SESSION['user_id']
        ?? $_SESSION['user']['id']
        ?? $_SESSION['auth_user']['id']
        ?? null;

    return is_numeric($value)
        ? (int)$value
        : null;
}

function currentUserName(): string
{
    return (string)(
        $_SESSION['full_name']
        ?? $_SESSION['user_name']
        ?? $_SESSION['user']['full_name']
        ?? $_SESSION['user']['name']
        ?? $_SESSION['auth_user']['full_name']
        ?? 'System User'
    );
}

function currentUserEmail(): string
{
    return (string)(
        $_SESSION['email']
        ?? $_SESSION['user']['email']
        ?? $_SESSION['auth_user']['email']
        ?? ''
    );
}

function currentRoleLabel(): string
{
    return (string)(
        $_SESSION['role_name']
        ?? $_SESSION['role_label']
        ?? $_SESSION['user']['role_name']
        ?? $_SESSION['user']['role']
        ?? $_SESSION['auth_user']['role_name']
        ?? 'User'
    );
}

function currentRole(): string
{
    $storedCode = (string)(
        $_SESSION['role_code']
        ?? ''
    );

    if ($storedCode !== '') {
        return canonicalRole($storedCode);
    }

    return canonicalRole(currentRoleLabel());
}

function isLoggedIn(): bool
{
    return currentUserId() !== null;
}

function isAdmin(): bool
{
    return currentRole() === ROLE_ADMIN;
}

function requireLogin(): void
{
    if (!isLoggedIn()) {
        if (isAjaxRequest()) {
            jsonResponse(false,'Please sign in to access VQDSS.',['session_expired'=>true]);
        }

        setFlash(
            'warning',
            'Please sign in to access VQDSS.'
        );

        redirect(appUrl('login.php'));
    }

    if (!vqdHasSystemAccess(currentUserId())) {
        if (isAjaxRequest()) {
            jsonResponse(false,'Your account does not currently have access to VQDSS.');
        }

        http_response_code(403);
        $pageTitle='Access Denied';
        include __DIR__ . '/../layouts/header.php';
        echo '<div class="app-wrapper">';
        include __DIR__ . '/../layouts/sidebar.php';
        echo '<main class="main-content"><section class="system-message-card">';
        echo '<i class="bi bi-shield-lock"></i><h1>VQDSS Access Denied</h1>';
        echo '<p>Your account is authenticated but does not currently have active access to the Voting, Quorum, and Decision Support System.</p>';
        echo '</section>';
        include __DIR__ . '/../layouts/footer.php';
        exit;
    }
}

function requireRole(array $allowedRoles): void
{
    requireLogin();

    $allowed = array_map(
        static fn (string $role): string =>
            canonicalRole($role),
        $allowedRoles
    );

    if (!in_array(currentRole(), $allowed, true)) {
        http_response_code(403);

        $pageTitle = 'Access Denied';

        include __DIR__ .
            '/../layouts/header.php';

        echo '<div class="app-wrapper">';
        include __DIR__ .
            '/../layouts/sidebar.php';

        echo '<main class="main-content">';
        echo '<section class="system-message-card">';
        echo '<i class="bi bi-shield-lock"></i>';
        echo '<h1>Access Denied</h1>';
        echo '<p>Your account does not have permission to open this page.</p>';
        echo '<a class="btn btn-primary" href="' .
            e(appUrl('dashboard.php')) .
            '">Return to Dashboard</a>';
        echo '</section>';

        include __DIR__ .
            '/../layouts/footer.php';

        exit;
    }
}

require_once __DIR__ . '/vqdss_security.php';
