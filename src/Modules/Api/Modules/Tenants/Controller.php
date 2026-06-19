<?php

namespace App\Modules\Api\Modules\Tenants;

use BitsHost\UpmvcSaas\Http\SaasApiController;

class Controller extends SaasApiController
{
    /** POST /api/tenants/register  (public) */
    public function register(): never
    {
        $body  = $this->requireFields(['slug', 'name', 'username', 'email', 'password']);
        $model = new Model();

        $slug = strtolower(preg_replace('/[^a-z0-9\-]/i', '-', trim($body['slug'])));

        if ($model->findBySlug($slug)) {
            $this->error("Slug '{$slug}' is already taken", 409);
        }

        $tenantId = $model->create([
            'slug'    => $slug,
            'name'    => $body['name'],
            'plan_id' => (int) ($body['plan_id'] ?? 1),
        ]);

        $userId = $model->createOwnerUser($tenantId, [
            'name'     => $body['name'],
            'username' => $body['username'],
            'email'    => $body['email'],
            'password' => $body['password'],
        ]);

        $this->success(
            ['tenant_id' => $tenantId, 'user_id' => $userId],
            'Registration successful. You can now log in via /api/auth/login.',
            201
        );
    }

    /** GET /api/tenants/{id}  (JWT) — returns tenant with plan details */
    public function show(): never
    {
        $id = (int) ($_GET['id'] ?? 0);
        $this->assertTenantAccess($id);

        $tenant = (new Model())->findByIdWithPlan($id);
        if (!$tenant) {
            $this->error('Tenant not found', 404);
        }

        $tenant['features']      = json_decode($tenant['features']      ?? '{}', true) ?? [];
        $tenant['plan_features'] = json_decode($tenant['plan_features'] ?? '{}', true) ?? [];
        $tenant['plan_limits']   = json_decode($tenant['plan_limits']   ?? '{}', true) ?? [];

        $this->success($tenant);
    }

    /** GET /api/tenants/{id}/users  (JWT) — list users for tenant */
    public function users(): never
    {
        $id = (int) ($_GET['id'] ?? 0);
        $this->assertTenantAccess($id);

        $this->success((new Model())->listUsers($id));
    }

    /** PATCH /api/tenants/{id}/update  (JWT) */
    public function update(): never
    {
        $id = (int) ($_GET['id'] ?? 0);
        $this->assertTenantAccess($id);

        $ok = (new Model())->update($id, $this->body());
        if ($ok) {
            $this->success(null, 'Tenant updated');
        } else {
            $this->error('Nothing to update or update failed');
        }
    }

    /** GET /api/public/tenants/{slug}  (public — no JWT) */
    public function bySlug(): never
    {
        $slug   = trim($_GET['slug'] ?? '');
        $tenant = (new Model())->findBySlugPublic($slug);

        if (!$tenant) {
            $this->error('Tenant not found', 404);
        }

        $this->success($tenant);
    }

    private function assertTenantAccess(int $id): void
    {
        $role         = $this->user['role']      ?? '';
        $userTenantId = (int) ($this->user['tenant_id'] ?? 0);

        if ($role !== 'platform_admin' && $userTenantId !== $id) {
            $this->error('Forbidden', 403);
        }
    }
}
