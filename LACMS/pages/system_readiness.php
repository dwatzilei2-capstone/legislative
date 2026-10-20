<?php
declare(strict_types=1);

require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../includes/lacms_security.php';
requireLacmsPermission('lacms.system_health.view');

redirect(appUrl('pages/system_health.php'));
