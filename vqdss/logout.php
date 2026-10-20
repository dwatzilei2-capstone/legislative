<?php
declare(strict_types=1);

require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/includes/functions.php';

session_unset();
session_destroy();

redirect(appUrl('login.php'));
