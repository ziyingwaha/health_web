<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verify_csrf((string)($_POST['csrf_token'] ?? ''))) {
        session_unset();
        session_destroy();
    }
}

redirect('/health-platform/public/login.php');

