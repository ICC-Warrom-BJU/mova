<?php

class AuthMiddleware
{
    public static function requireAuth(): void
    {
        if (empty($_SESSION['user_id'])) {
            http_response_code(401);
            header('Location: /login');
            exit;
        }
    }

    public static function requireRole(array $allowedRoles): void
    {
        $tenant = SessionMiddleware::getTenantContext();
        $userRole = $tenant->getRole();
        if (!in_array($userRole, $allowedRoles, true)) {
            jsonError('Forbidden: role tidak diizinkan', 403);
        }
    }

    public static function requireLayer(string $layer): void
    {
        $tenant = SessionMiddleware::getTenantContext();
        if ($tenant->getLayer() !== $layer && !$tenant->isSuperAdmin()) {
            jsonError('Forbidden: akses terbatas', 403);
        }
    }

    public static function requireOwnershipOrScope(int $resourceCustomerId): void
    {
        $tenant = SessionMiddleware::getTenantContext();

        if ($tenant->isSuperAdmin()) {
            return;
        }

        $accessible = $tenant->getAccessibleCustomerIds();

        if ($tenant->getLayer() === 'customer') {
            if ($tenant->getCustomerId() !== $resourceCustomerId) {
                jsonError('Forbidden: di luar scope akses Anda', 403);
            }
            return;
        }

        if (!in_array($resourceCustomerId, $accessible, true)) {
            jsonError('Forbidden: di luar scope akses Anda', 403);
        }
    }

    public static function validateCsrf(): void
    {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            http_response_code(419);
            die(json_encode(['error' => 'Invalid CSRF token']));
        }
    }

    public static function generateCsrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

}
