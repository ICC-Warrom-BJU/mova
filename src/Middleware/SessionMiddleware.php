<?php

class SessionMiddleware
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ($_SERVER['SERVER_PORT'] ?? 80) == 443;

        session_set_cookie_params([
            'lifetime' => 3600 * 8,
            'path' => '/',
            'domain' => '',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);

        session_start();

        if (empty($_SESSION['_started'])) {
            $_SESSION['_started'] = time();
        }

        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 3600, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        session_destroy();
    }

    public static function isLoggedIn(): bool
    {
        return !empty($_SESSION['user_id']);
    }

    public static function getTenantContext(): TenantContext
    {
        return new TenantContext($_SESSION);
    }
}
