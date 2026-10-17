<?php
session_start();
require_once __DIR__ . '/database.php';

function appBasePath(): string
{
    $scriptPath = $_SERVER['SCRIPT_NAME'] ?? '/';
    $routeDirs = ['/admin', '/customer', '/pages', '/config'];

    foreach ($routeDirs as $routeDir) {
        $routePos = strrpos($scriptPath, $routeDir . '/');
        if ($routePos !== false) {
            return substr($scriptPath, 0, $routePos);
        }
    }

    $basePath = rtrim(dirname($scriptPath), '/\\');

    return $basePath === '.' || $basePath === '/' ? '' : $basePath;
}

function encodeUrlPath(string $path): string
{
    $parts = parse_url($path);
    $segments = explode('/', $parts['path'] ?? $path);

    foreach ($segments as $index => $segment) {
        if ($segment === '' || $segment === '.') {
            continue;
        }

        $segments[$index] = rawurlencode(rawurldecode($segment));
    }

    $encodedPath = implode('/', $segments);
    if (isset($parts['query'])) {
        $encodedPath .= '?' . $parts['query'];
    }
    if (isset($parts['fragment'])) {
        $encodedPath .= '#' . $parts['fragment'];
    }

    return $encodedPath;
}

function appUrlPath(string $path): string
{
    $basePath = appBasePath();
    $relativePath = '/' . ltrim($path, '/');

    return encodeUrlPath($basePath . $relativePath);
}

function redirectTo(string $path): void
{
    $target = appUrlPath($path);
    header('Location: ' . $target, true, 302);
    exit;
}

function isLoggedIn(): bool
{
    return !empty($_SESSION['user_id']);
}

function currentUser(): ?array
{
    if (!isLoggedIn()) {
        return null;
    }

    return [
        'id' => $_SESSION['user_id'] ?? null,
        'name' => $_SESSION['user_name'] ?? 'User',
        'email' => $_SESSION['user_email'] ?? '',
        'role' => $_SESSION['user_role'] ?? 'customer',
    ];
}

function requireAuth(): void
{
    if (!isLoggedIn()) {
        redirectTo('/pages/login.php');
    }
}

function requireRole(array $allowedRoles): void
{
    requireAuth();

    $currentRole = $_SESSION['user_role'] ?? '';

    if (!in_array($currentRole, ['admin', 'customer'], true)) {
        session_unset();
        session_destroy();
        redirectTo('/pages/login.php');
    }

    if (!in_array($currentRole, $allowedRoles, true)) {
        redirectTo($currentRole === 'admin' ? '/admin/dashboard.php' : '/customer/dashboard.php');
    }
}

function logoutUser(): void
{
    session_unset();
    session_destroy();
    redirectTo('/pages/login.php');
}
