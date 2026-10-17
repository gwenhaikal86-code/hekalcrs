<?php
require_once __DIR__ . '/config/auth.php';

if (isLoggedIn()) {
    $role = $_SESSION['user_role'] ?? 'customer';
    redirectTo(($role === 'admin') ? '/admin/dashboard.php' : '/customer/dashboard.php');
}

redirectTo('/index.html');
