<?php

namespace App\Modules\Api\Modules\Admin;

use App\Etc\JwtService;
use BitsHost\UpmvcSaas\Http\SaasApiController;

class Controller extends SaasApiController
{
    public function __construct()
    {
        parent::__construct();

        if (($this->user['role'] ?? '') !== 'platform_admin') {
            $this->error('Platform admin access required', 403);
        }
    }

    /** GET /api/admin/tenants?limit=50&offset=0 */
    public function listTenants(): never
    {
        $limit  = max(1, min(100, (int) ($_GET['limit']  ?? 50)));
        $offset = max(0, (int) ($_GET['offset'] ?? 0));

        $model = new Model();
        $this->success([
            'tenants' => $model->listTenants($limit, $offset),
            'total'   => $model->countTenants(),
        ]);
    }

    /** PATCH /api/admin/tenants/{id}/status   body: {"status":"active"|"suspended"|"trial"} */
    public function updateStatus(): never
    {
        $id   = (int) ($_GET['id'] ?? 0);
        $body = $this->requireFields(['status']);

        $ok = (new Model())->updateStatus($id, $body['status']);
        if ($ok) {
            $this->success(null, 'Status updated');
        } else {
            $this->error('Invalid status value or tenant not found', 400);
        }
    }

    /** PATCH /api/admin/tenants/{id}/plan   body: {"plan_id":2} */
    public function updatePlan(): never
    {
        $id   = (int) ($_GET['id'] ?? 0);
        $body = $this->requireFields(['plan_id']);

        $ok = (new Model())->updatePlan($id, (int) $body['plan_id']);
        if ($ok) {
            $this->success(null, 'Plan updated');
        } else {
            $this->error('Update failed', 400);
        }
    }

    /** GET /api/admin/dashboard */
    public function dashboard(): never
    {
        $this->success((new Model())->getDashboardStats());
    }

    /** GET /api/admin/metrics */
    public function metrics(): never
    {
        $this->success((new Model())->getMetrics());
    }

    /** PUT /api/admin/plans/{id}   body: {name, price, features, limits} */
    public function updatePlanDetails(): never
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
            $this->error('Method not allowed', 405);
        }

        $id   = (int) ($_GET['id'] ?? 0);
        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        $ok = (new Model())->updatePlanDetails($id, $body);
        if ($ok) {
            $this->success(null, 'Plan updated');
        } else {
            $this->error('Update failed', 400);
        }
    }

    /** PATCH /api/admin/tenants/{id}   body: {name?, status?, plan_id?} */
    public function updateTenant(): never
    {
        $id   = (int) ($_GET['id'] ?? 0);
        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        if (empty($body)) {
            $this->error('No fields to update', 400);
        }

        $ok = (new Model())->updateTenantFields($id, $body);
        if ($ok) {
            $this->success(null, 'Tenant updated');
        } else {
            $this->error('Update failed or nothing changed', 400);
        }
    }

    /** POST /api/admin/impersonate   body: {"tenant_id": 5} */
    public function impersonate(): never
    {
        $body     = $this->requireFields(['tenant_id']);
        $tenantId = (int) $body['tenant_id'];
        $model    = new Model();

        $tenant = $model->findTenantById($tenantId);
        if (!$tenant) {
            $this->error('Tenant not found', 404);
        }
        if ($tenant['status'] !== 'active') {
            $this->error('Cannot impersonate an inactive tenant', 403);
        }

        $owner = $model->findTenantOwner($tenantId);
        if (!$owner) {
            $this->error('No owner user found for this tenant', 404);
        }

        $jwt   = new JwtService();
        $token = $jwt->issueAccessToken([
            'sub'          => (int) $owner['id'],
            'username'     => $owner['username'],
            'tenant_id'    => $tenantId,
            'tenant_slug'  => $tenant['slug'],
            'tenant_name'  => $tenant['name'],
            'role'         => $owner['role'],
            'impersonated' => true,
        ]);

        $this->success([
            'access_token' => $token,
            'token_type'   => 'Bearer',
            'expires_in'   => $jwt->getAccessTtl(),
            'tenant'       => [
                'id'   => $tenantId,
                'name' => $tenant['name'],
                'slug' => $tenant['slug'],
            ],
        ]);
    }
}
