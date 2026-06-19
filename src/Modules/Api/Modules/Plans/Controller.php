<?php

namespace App\Modules\Api\Modules\Plans;

use BitsHost\UpmvcSaas\Http\SaasApiController;

class Controller extends SaasApiController
{
    public function index(): never
    {
        $plans = (new Model())->listAll();
        foreach ($plans as &$p) {
            $p['features'] = json_decode($p['features'] ?? '{}', true) ?? [];
            $p['limits']   = json_decode($p['limits']   ?? '{}', true) ?? [];
        }
        $this->success($plans);
    }

    public function show(): never
    {
        $id   = (int) ($_GET['id'] ?? 0);
        $plan = (new Model())->findById($id);

        if (!$plan) {
            $this->error('Plan not found', 404);
        }

        $plan['features'] = json_decode($plan['features'] ?? '{}', true) ?? [];
        $plan['limits']   = json_decode($plan['limits']   ?? '{}', true) ?? [];

        $this->success($plan);
    }
}
