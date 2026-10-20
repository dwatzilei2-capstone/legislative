<?php
declare(strict_types=1);

/**
 * Compatibility include for code that expects a dedicated permissions file.
 * Fine-grained ORLMS permission functions are implemented in
 * includes/orlms_security.php and loaded by includes/auth.php.
 */
require_once __DIR__.'/auth.php';
require_once __DIR__.'/orlms_security.php';
