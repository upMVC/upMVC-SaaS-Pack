<?php

namespace App\Modules\Api\Modules\Admin;

use App\Common\Bmvc\BaseModel;

class Model extends BaseModel
{
    public function listTenants(int $limit, int $offset): array
    {
        $stmt = $this->conn->prepare(
            "SELECT t.id, t.slug, t.name, t.status, t.plan_id,
                    p.name AS plan_name, t.created_at
             FROM   tenants t
             LEFT JOIN plans p ON p.id = t.plan_id
             WHERE  t.deleted_at IS NULL
             ORDER  BY t.created_at DESC
             LIMIT  :limit OFFSET :offset"
        );
        $stmt->bindValue(':limit',  $limit,  \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function countTenants(): int
    {
        return (int) $this->conn->query(
            "SELECT COUNT(*) FROM tenants WHERE deleted_at IS NULL"
        )->fetchColumn();
    }

    public function updateStatus(int $tenantId, string $status): bool
    {
        $allowed = ['active', 'suspended', 'trial'];
        if (!in_array($status, $allowed, true)) {
            return false;
        }
        $stmt = $this->conn->prepare(
            "UPDATE tenants SET status = :status WHERE id = :id"
        );
        return $stmt->execute([':status' => $status, ':id' => $tenantId]);
    }

    public function updatePlan(int $tenantId, int $planId): bool
    {
        $stmt = $this->conn->prepare(
            "UPDATE tenants SET plan_id = :plan_id WHERE id = :id"
        );
        return $stmt->execute([':plan_id' => $planId, ':id' => $tenantId]);
    }

    public function getDashboardStats(): array
    {
        return [
            'tenants' => [
                'total'     => (int) $this->conn->query("SELECT COUNT(*) FROM tenants WHERE deleted_at IS NULL")->fetchColumn(),
                'active'    => (int) $this->conn->query("SELECT COUNT(*) FROM tenants WHERE status='active' AND deleted_at IS NULL")->fetchColumn(),
                'trial'     => (int) $this->conn->query("SELECT COUNT(*) FROM tenants WHERE status='trial' AND deleted_at IS NULL")->fetchColumn(),
                'suspended' => (int) $this->conn->query("SELECT COUNT(*) FROM tenants WHERE status='suspended' AND deleted_at IS NULL")->fetchColumn(),
            ],
            'users' => [
                'total' => (int) $this->conn->query("SELECT COUNT(*) FROM users")->fetchColumn(),
            ],
            'plans' => (new \App\Modules\Api\Modules\Plans\Model())->listAll(),
        ];
    }

    public function getMetrics(): array
    {
        $stmt = $this->conn->query(
            "SELECT DATE(created_at) AS day, COUNT(*) AS new_tenants
             FROM tenants
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY DATE(created_at)
             ORDER BY day ASC"
        );
        return [
            'tenants_last_30_days' => $stmt->fetchAll(\PDO::FETCH_ASSOC),
        ];
    }

    public function updatePlanDetails(int $id, array $data): bool
    {
        $allowed = ['name', 'price', 'features', 'limits'];
        $sets    = [];
        $params  = [':id' => $id];

        foreach ($allowed as $col) {
            if (array_key_exists($col, $data)) {
                $sets[]          = "$col = :$col";
                $params[":$col"] = in_array($col, ['features', 'limits'])
                    ? json_encode($data[$col])
                    : $data[$col];
            }
        }

        if (empty($sets)) {
            return false;
        }

        $stmt = $this->conn->prepare(
            "UPDATE plans SET " . implode(', ', $sets) . " WHERE id = :id"
        );
        return $stmt->execute($params);
    }

    public function findTenantById(int $id): array|false
    {
        $stmt = $this->conn->prepare(
            "SELECT * FROM tenants WHERE id = :id AND deleted_at IS NULL LIMIT 1"
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    public function updateTenantFields(int $id, array $data): bool
    {
        $allowed = ['name', 'status', 'plan_id'];
        $sets    = [];
        $params  = [':id' => $id];

        foreach ($allowed as $col) {
            if (array_key_exists($col, $data)) {
                $sets[]          = "$col = :$col";
                $params[":$col"] = $data[$col];
            }
        }

        if (empty($sets)) {
            return false;
        }

        $stmt = $this->conn->prepare(
            "UPDATE tenants SET " . implode(', ', $sets) . " WHERE id = :id AND deleted_at IS NULL"
        );
        return $stmt->execute($params);
    }

    public function findTenantOwner(int $tenantId): array|false
    {
        $stmt = $this->conn->prepare(
            "SELECT id, username, role FROM users
             WHERE tenant_id = :tid AND role = 'tenant_owner' LIMIT 1"
        );
        $stmt->execute([':tid' => $tenantId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }
}
