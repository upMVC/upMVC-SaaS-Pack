<?php

namespace BitsHost\UpmvcSaas\Middleware;

use App\Etc\Database;

class TenantMiddleware
{
    public function __invoke(string $route, string $method): bool
    {
        $slug = $this->resolveSlug();

        $db = (new Database())->getConnection();
        $stmt = $db->prepare(
            "SELECT id, slug, name, plan_id, status, features
             FROM tenants
             WHERE slug = :slug AND deleted_at IS NULL
             LIMIT 1"
        );
        $stmt->execute([':slug' => $slug]);
        $tenant = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$tenant) {
            $this->abort(404, 'Tenant not found');
        }

        if ($tenant['status'] !== 'active') {
            $this->abort(403, 'Account suspended or not yet active');
        }

        if (is_string($tenant['features'])) {
            $tenant['features'] = json_decode($tenant['features'], true) ?? [];
        }

        $GLOBALS['current_tenant'] = $tenant;
        return true;
    }

    private function resolveSlug(): string
    {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
        $host = $_SERVER['HTTP_HOST'] ?? '';

        if (preg_match('#^/app/([a-z0-9\-_]+)(/|$)#i', $uri, $m)) {
            return strtolower($m[1]);
        }

        $baseHost = parse_url($_ENV['BASE_URL'] ?? '', PHP_URL_HOST) ?? '';
        if ($baseHost !== '' && str_ends_with($host, '.' . $baseHost)) {
            return strtolower(substr($host, 0, -strlen('.' . $baseHost)));
        }

        $this->abort(400, 'Tenant could not be resolved from the request');
    }

    private function abort(int $code, string $msg): never
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => $msg]);
        exit;
    }
}
