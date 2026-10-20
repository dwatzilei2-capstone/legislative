<?php
require_once __DIR__ . '/includes/auth.php';

requireLogin();

$user = currentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>ORLMS Session Test</title>
</head>

<body>
    <h1>ORLMS Session Test</h1>

    <p>
        You are logged in as:
        <strong><?= e($user['full_name']) ?></strong>
    </p>

    <p>
        Email:
        <strong><?= e($user['email']) ?></strong>
    </p>

    <p>
        Role:
        <strong><?= e($user['role_name']) ?></strong>
    </p>

    <p>
        User ID:
        <strong><?= (int)$user['id'] ?></strong>
    </p>

    <p>
        <a href="http://localhost/lph/dashboard.php">
            Return to Public Hearing System
        </a>
    </p>
</body>
</html>