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
