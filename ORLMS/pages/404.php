<?php
declare(strict_types=1);
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../includes/functions.php';
http_response_code(404);
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>404 | ORLMS</title><link rel="stylesheet" href="<?= e(vendorAsset('bootstrap/bootstrap.min.css','https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css')) ?>"></head><body class="bg-light"><main class="container py-5"><div class="card border-0 shadow-sm mx-auto" style="max-width:720px"><div class="card-body p-5 text-center"><div class="display-3 fw-bold text-primary">404</div><h1 class="h3">Page Not Found</h1><p class="text-muted">The requested ORLMS page could not be found.</p><a class="btn btn-primary" href="<?= e(APP_URL.'/dashboard.php') ?>">Return to ORLMS</a></div></div></main></body></html>
