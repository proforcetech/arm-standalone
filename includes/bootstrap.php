<?php
declare(strict_types=1);

$rootPath = dirname(__DIR__);
$publicPath = $rootPath . '/public';

if (!defined('ARM_RE_ROOT')) {
    define('ARM_RE_ROOT', $rootPath);
}

if (!defined('ARM_RE_PATH')) {
    define('ARM_RE_PATH', rtrim($rootPath, '/\\') . '/');
}

if (!defined('ARM_RE_PUBLIC_PATH')) {
    define('ARM_RE_PUBLIC_PATH', rtrim($publicPath, '/\\') . '/');
}

if (!defined('ARM_RE_VERSION')) {
    $version = $_ENV['ARM_RE_VERSION'] ?? '1.2.0';
    define('ARM_RE_VERSION', $version);
}

if (!defined('ABSPATH')) {
    define('ABSPATH', ARM_RE_PATH);
}

$baseUrl = $_ENV['APP_URL'] ?? '';

if ($baseUrl === '' && isset($_SERVER['HTTP_HOST'])) {
    $scheme = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ($_SERVER['REQUEST_SCHEME'] ?? 'http');
    $baseUrl = $scheme . '://' . $_SERVER['HTTP_HOST'];
}

$baseUrl = rtrim($baseUrl, '/');

if (!defined('ARM_RE_URL')) {
    define('ARM_RE_URL', $baseUrl !== '' ? $baseUrl . '/' : '/');
}

// Load WordPress compatibility layers (must be loaded before anything else)
require_once __DIR__ . '/compat/hooks.php';
require_once __DIR__ . '/compat/db.php';
require_once __DIR__ . '/compat/sanitization-functions.php';
require_once __DIR__ . '/compat/i18n-functions.php';
require_once __DIR__ . '/compat/options-api.php';
require_once __DIR__ . '/compat/ajax-functions.php';
require_once __DIR__ . '/compat/admin-functions.php';
require_once __DIR__ . '/compat/db-init.php';
require_once __DIR__ . '/compat/upgrade.php';

// Start session if not already started (for authentication)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load authentication helpers (sessions, CSRF, capability shims)
$authHelpers = __DIR__ . '/auth-helpers.php';
if (file_exists($authHelpers)) {
    require_once $authHelpers;
}

// Load compatibility shims for legacy class names
$compatShim = __DIR__ . '/compat-shim.php';
if (file_exists($compatShim)) {
    require_once $compatShim;
}
