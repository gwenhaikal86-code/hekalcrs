<?php
require_once __DIR__ . '/../config/auth.php';
requireRole(['admin']);

if (empty($_SESSION['admin_csrf_token'])) {
    $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
}

function adminCsrfValid(): bool
{
    $submittedToken = $_POST['csrf_token'] ?? '';
    return is_string($submittedToken)
        && isset($_SESSION['admin_csrf_token'])
        && hash_equals($_SESSION['admin_csrf_token'], $submittedToken);
}

function adminRedirect(string $path): never
{
    redirectTo($path);
}