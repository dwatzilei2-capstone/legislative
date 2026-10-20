<?php
/**
 * auth/process_login.php
 * ------------------------------------------------------------------
 * Authenticates a user using the shared legislative database and
 * creates the same session structure used by LPH.
 * ------------------------------------------------------------------
 */

require_once __DIR__ . '/../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(APP_URL . '/login.php');
}

// A repeated submission can carry the token from before successful login.
// Do not authenticate again or change session state for an existing login.
if (isLoggedIn()) {
    header('Location: ' . APP_URL . '/dashboard.php', true, 303);
    exit;
}

if (!verifyCsrf()) {
    setFlash('warning', 'Your login form expired. Please try again.');
    header('Location: ' . APP_URL . '/login.php', true, 303);
    exit;
}

$email      = strtolower(trim($_POST['email'] ?? ''));
$password   = (string)($_POST['password'] ?? '');
$rememberMe = isset($_POST['remember_me']);

if ($email === '' || $password === '') {
    setFlash(
        'warning',
        'Please enter your email address and password.'
    );

    redirect(APP_URL . '/login.php');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    setFlash(
        'warning',
        'Please enter a valid email address.'
    );

    redirect(APP_URL . '/login.php');
}

$pdo = db();
$loginIp = orlmsLoginIp();

if (orlmsLoginRateLimited($email,$loginIp,5,15)) {
    logActivity(null,'ORLMS Login Rate Limited','Rate limit triggered for a login attempt from '.$loginIp.'.');
    setFlash('danger','Too many unsuccessful sign-in attempts. Please wait about 15 minutes before trying again.');
    redirect(APP_URL . '/login.php');
}

try {
    $stmt = $pdo->prepare(
        'SELECT
            u.id,
            u.full_name,
            u.email,
            u.password,
            u.role_id,
            u.status,
            r.name AS role_name
         FROM users u
         INNER JOIN roles r
            ON r.id = u.role_id
         WHERE u.email = :email
           AND u.deleted_at IS NULL
         LIMIT 1'
    );

    $stmt->execute([
        ':email' => $email,
    ]);

    $user = $stmt->fetch();

    if (
        !$user ||
        !password_verify($password, $user['password'])
    ) {
        orlmsRecordLoginAttempt($email,$loginIp,false);
        setFlash(
            'danger',
            'The email address or password is incorrect.'
        );

        redirect(APP_URL . '/login.php');
    }

    if (strcasecmp($user['status'], 'Active') !== 0) {
        orlmsRecordLoginAttempt($email,$loginIp,false);
        setFlash(
            'danger',
            'Your account is not active. Please contact the administrator.'
        );

        redirect(APP_URL . '/login.php');
    }

    if (!orlmsHasSystemAccess((int)$user['id'])) {
        orlmsRecordLoginAttempt($email,$loginIp,false);
        logActivity(
            (int)$user['id'],
            'ORLMS Access Denied',
            'Authenticated account does not have active ORLMS system access.'
        );

        setFlash(
            'danger',
            'Your account does not currently have access to ORLMS.'
        );

        redirect(APP_URL . '/login.php');
    }

    /*
     * Regenerate the session identifier after authentication.
     */
    session_regenerate_id(true);

    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['role_id'] = (int)$user['role_id'];
    $_SESSION['role_name'] = $user['role_name'];
    $_SESSION['last_activity'] = time();
    $_SESSION['session_rotated_at'] = time();
    $_SESSION['remember_me'] = $rememberMe;
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $_SESSION['user'] = [
        'id' => (int)$user['id'],
        'full_name' => $user['full_name'],
        'email' => $user['email'],
        'role_id' => (int)$user['role_id'],
        'role_name' => $user['role_name'],
    ];

    /*
     * Record the latest login when the column exists.
     */
    try {
        $updateStmt = $pdo->prepare(
            'UPDATE users
             SET last_login_at = NOW(),
                 updated_at = NOW()
             WHERE id = :id'
        );

        $updateStmt->execute([
            ':id' => (int)$user['id'],
        ]);
    } catch (PDOException $e) {
        error_log(
            'Last login update skipped: ' . $e->getMessage()
        );
    }

    orlmsRecordLoginAttempt($email,$loginIp,true);

    logActivity(
        (int)$user['id'],
        'Login',
        'Signed in to the Ordinance and Resolution Life Cycle Management System.'
    );

    /*
     * Always redirect after a successful login.
     */
    redirect(APP_URL . '/dashboard.php');

} catch (Throwable $e) {
    error_log(
        'ORLMS login error: ' .
        $e->getMessage() .
        ' in ' .
        $e->getFile() .
        ':' .
        $e->getLine()
    );

    setFlash(
        'danger',
        APP_DEBUG
            ? 'Login error: ' . $e->getMessage()
            : 'A server error occurred while signing in.'
    );

    redirect(APP_URL . '/login.php');
}
