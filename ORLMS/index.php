<?php
/**
 * ORLMS root entry point.
 */

require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    redirect(APP_URL . '/dashboard.php');
}

redirect(APP_URL . '/login.php');
