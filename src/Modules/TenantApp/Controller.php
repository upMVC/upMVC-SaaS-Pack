<?php

namespace App\Modules\TenantApp;

class Controller
{
    private const ALLOWED_ROLES = ['tenant_owner', 'tenant_user'];

    public function display(string $reqRoute, string $_reqMet): void
    {
        $slug    = $_GET['slug']    ?? '';
        $page    = $_GET['page']    ?? '';

        // /app/{slug} with no /admin — public frontend, no auth required
        if ($reqRoute !== '/app' && $page !== 'admin' && ($_GET['subpage'] ?? '') === '') {
            (new View())->renderPublic($slug);
            return;
        }

        // Everything else (/app, /app/{slug}/admin, /app/{slug}/admin/{subpage}) requires auth
        if (!isset($_SESSION['logged']) || $_SESSION['logged'] !== true) {
            $path = str_replace(SITEPATH, '', strtok($_SERVER['REQUEST_URI'], '?'));
            $_SESSION['intended_url'] = BASE_URL . $path;
            header('Location: ' . BASE_URL . '/auth');
            exit;
        }

        if (!in_array($_SESSION['role'] ?? '', self::ALLOWED_ROLES, true)) {
            http_response_code(403);
            echo '<h1>403 — Tenant access only.</h1>';
            exit;
        }

        $sessionSlug = $_SESSION['tenant_slug'] ?? '';

        // /app — redirect to own tenant admin
        if ($reqRoute === '/app') {
            header('Location: ' . BASE_URL . '/app/' . $sessionSlug . '/admin');
            exit;
        }

        // Slug in URL must match session tenant
        if (strtolower($slug) !== strtolower($sessionSlug)) {
            http_response_code(403);
            echo '<h1>403 — You do not have access to this tenant.</h1>';
            exit;
        }

        (new View())->renderAdmin([
            'slug'      => $sessionSlug,
            'name'      => $_SESSION['tenant_name'] ?? $sessionSlug,
            'username'  => $_SESSION['username'] ?? '',
            'tenant_id' => (int) ($_SESSION['tenant_id'] ?? 0),
            'token'     => $_SESSION['jwt_token'] ?? '',
        ]);
    }
}
