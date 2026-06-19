<?php

namespace BitsHost\UpmvcSaas\Middleware;

class PlanGateMiddleware
{
    public function __construct(private string $feature) {}

    public function __invoke(string $route, string $method): bool
    {
        $tenant = $GLOBALS['current_tenant'] ?? null;

        if (!$tenant) {
            $this->abort(401, 'Tenant context not available - run tenant middleware first');
        }

        $features = $tenant['features'] ?? [];
        if (is_string($features)) {
            $features = json_decode($features, true) ?? [];
        }

        if (empty($features[$this->feature])) {
            $this->abort(
                403,
                "Feature '{$this->feature}' is not available on your current plan. Please upgrade."
            );
        }

        return true;
    }

    private function abort(int $code, string $msg): never
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => $msg]);
        exit;
    }
}
