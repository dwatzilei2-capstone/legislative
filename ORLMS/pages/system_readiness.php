<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
requirePermission('orlms.system_health.view');
redirect(APP_URL.'/pages/system_health.php');
