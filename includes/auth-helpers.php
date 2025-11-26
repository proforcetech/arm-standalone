<?php

declare(strict_types=1);

use ARM\Auth\AuthService;

if (!function_exists('current_user_can')) {
    function current_user_can(string $capability): bool
    {
        $auth = AuthService::fromEnvironment();
        return $auth->can($capability);
    }
}

if (!function_exists('wp_create_nonce')) {
    function wp_create_nonce(string $action = 'default'): string
    {
        $auth = AuthService::fromEnvironment();
        return $auth->csrf()->issue($action);
    }
}

if (!function_exists('wp_verify_nonce')) {
    function wp_verify_nonce($nonce, string $action = 'default'): bool
    {
        $auth = AuthService::fromEnvironment();
        return $auth->csrf()->verify($nonce, $action);
    }
}

if (!function_exists('wp_die')) {
    function wp_die(string $message = 'Forbidden', int $status = 403): void
    {
        http_response_code($status);
        echo $message;
        exit;
    }
}

// wp_send_json_error is now defined in ajax-functions.php
