<?php

namespace App\Modules\PlatformAdmin;

use App\Etc\JwtService;

class Controller
{
    public function display(): void
    {
        if (!isset($_SESSION['logged']) || $_SESSION['logged'] !== true) {
            header('Location: ' . BASE_URL . '/auth');
            exit;
        }

        if (($_SESSION['role'] ?? '') !== 'platform_admin') {
            http_response_code(403);
            echo '<h1>403 — Platform admin access required.</h1>';
            exit;
        }

        (new View())->render();
    }

    /**
     * Receives an impersonation JWT (POST), verifies it, swaps the PHP session
     * to the tenant user, and redirects to /app.
     */
    public function assume(): void
    {
        if (!isset($_SESSION['logged']) || ($_SESSION['role'] ?? '') !== 'platform_admin') {
            header('Location: ' . BASE_URL . '/auth');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . '/platform-admin');
            exit;
        }

        $token   = trim($_POST['token'] ?? '');
        $payload = $token !== '' ? (new JwtService())->verify($token) : null;

        if ($payload === null) {
            header('Location: ' . BASE_URL . '/platform-admin');
            exit;
        }

        // Stash admin identity so TenantApp can offer a "Return to Admin" link
        $_SESSION['impersonating'] = [
            'admin_id'       => $_SESSION['iduser'],
            'admin_username' => $_SESSION['username'],
            'admin_jwt'      => $_SESSION['jwt_token'] ?? '',
        ];

        session_regenerate_id(true);
        $_SESSION['logged']        = true;
        $_SESSION['authenticated'] = true;
        $_SESSION['iduser']        = (int) $payload['sub'];
        $_SESSION['username']      = $payload['username'];
        $_SESSION['role']          = $payload['role'];
        $_SESSION['tenant_id']     = $payload['tenant_id'];
        $_SESSION['tenant_slug']   = $payload['tenant_slug'] ?? '';
        $_SESSION['tenant_name']   = $payload['tenant_name'] ?? '';
        $_SESSION['jwt_token']     = $token;

        header('Location: ' . BASE_URL . '/app');
        exit;
    }

    /**
     * Restores the original platform admin session after impersonation.
     */
    public function resume(): void
    {
        if (!isset($_SESSION['impersonating'])) {
            header('Location: ' . BASE_URL . '/platform-admin');
            exit;
        }

        $admin = $_SESSION['impersonating'];

        session_regenerate_id(true);
        $_SESSION['logged']        = true;
        $_SESSION['authenticated'] = true;
        $_SESSION['iduser']        = $admin['admin_id'];
        $_SESSION['username']      = $admin['admin_username'];
        $_SESSION['role']          = 'platform_admin';
        $_SESSION['tenant_id']     = null;
        $_SESSION['tenant_slug']   = '';
        $_SESSION['tenant_name']   = '';
        $_SESSION['jwt_token']     = $admin['admin_jwt'];

        unset($_SESSION['impersonating']);

        header('Location: ' . BASE_URL . '/platform-admin');
        exit;
    }
}
