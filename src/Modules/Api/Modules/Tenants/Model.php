<?php

namespace App\Modules\Api\Modules\Tenants;

use App\Common\Bmvc\BaseModel;

class Model extends BaseModel
{
    public function create(array $data, string $table = ''): int
    {
        $stmt = $this->conn->prepare(
            "INSERT INTO tenants (slug, name, plan_id, status, features)
             VALUES (:slug, :name, :plan_id, 'trial', :features)"
        );
        $stmt->execute([
            ':slug'     => strtolower(trim(strip_tags($data['slug']))),
            ':name'     => trim(strip_tags($data['name'])),
            ':plan_id'  => (int) ($data['plan_id'] ?? 1),
            ':features' => json_encode($data['features'] ?? []),
        ]);
        return (int) $this->conn->lastInsertId();
    }

    public function createOwnerUser(int $tenantId, array $u): int
    {
        $stmt = $this->conn->prepare(
            "INSERT INTO users (tenant_id, name, username, email, password, token, state, role)
             VALUES (:tenant_id, :name, :username, :email, :password, :token, 1, 'tenant_owner')"
        );
        $stmt->execute([
            ':tenant_id' => $tenantId,
            ':name'      => trim(strip_tags($u['name'])),
            ':username'  => trim(strip_tags($u['username'])),
            ':email'     => trim(strip_tags($u['email'])),
            ':password'  => password_hash($u['password'], PASSWORD_BCRYPT),
            ':token'     => bin2hex(random_bytes(16)),
        ]);
        return (int) $this->conn->lastInsertId();
    }

    public function findById(int $id): array|false
    {
        $stmt = $this->conn->prepare(
            "SELECT * FROM tenants WHERE id = :id AND deleted_at IS NULL LIMIT 1"
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    public function findBySlug(string $slug): array|false
    {
        $stmt = $this->conn->prepare(
            "SELECT * FROM tenants WHERE slug = :slug AND deleted_at IS NULL LIMIT 1"
        );
        $stmt->execute([':slug' => strtolower($slug)]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    public function update(int $id, array $data, string $table = ''): bool
    {
        $allowed = ['name', 'plan_id', 'status', 'features'];
        $sets    = [];
        $params  = [':id' => $id];

        foreach ($allowed as $col) {
            if (array_key_exists($col, $data)) {
                $sets[]          = "$col = :$col";
                $params[":$col"] = ($col === 'features') ? json_encode($data[$col]) : $data[$col];
            }
        }

        if (empty($sets)) {
            return false;
        }

        $stmt = $this->conn->prepare(
            "UPDATE tenants SET " . implode(', ', $sets) . " WHERE id = :id"
        );
        return $stmt->execute($params);
    }

    public function findByIdWithPlan(int $id): array|false
    {
        $stmt = $this->conn->prepare(
            "SELECT t.id, t.slug, t.name, t.status, t.features, t.created_at,
                    p.id       AS plan_id,
                    p.name     AS plan_name,
                    p.price    AS plan_price,
                    p.features AS plan_features,
                    p.limits   AS plan_limits
             FROM   tenants t
             LEFT JOIN plans p ON p.id = t.plan_id
             WHERE  t.id = :id AND t.deleted_at IS NULL
             LIMIT  1"
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    public function findBySlugPublic(string $slug): array|false
    {
        $stmt = $this->conn->prepare(
            "SELECT t.id, t.slug, t.name, t.status,
                    p.name AS plan_name
             FROM   tenants t
             LEFT JOIN plans p ON p.id = t.plan_id
             WHERE  t.slug = :slug AND t.deleted_at IS NULL
             LIMIT  1"
        );
        $stmt->execute([':slug' => strtolower($slug)]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    public function listUsers(int $tenantId): array
    {
        $stmt = $this->conn->prepare(
            "SELECT id, name, username, email, role, state, stamp
             FROM   users
             WHERE  tenant_id = :tenant_id
             ORDER  BY role DESC, name ASC"
        );
        $stmt->execute([':tenant_id' => $tenantId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function softDelete(int $id): bool
    {
        $stmt = $this->conn->prepare(
            "UPDATE tenants SET deleted_at = NOW() WHERE id = :id"
        );
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Create a tenant and its first owner user in a single transaction.
     * Returns ['success'=>true,'tenant_id'=>int,'user_id'=>int] or ['success'=>false,'error'=>string].
     */
    public function createWithOwner(array $tenantData, array $userData): array
    {
        try {
            $this->conn->beginTransaction();
            $tenantId = $this->create($tenantData);
            $userId   = $this->createOwnerUser($tenantId, $userData);
            $this->conn->commit();
            return ['success' => true, 'tenant_id' => $tenantId, 'user_id' => $userId];
        } catch (\Exception $e) {
            $this->conn->rollBack();
            return ['success' => false, 'error' => 'Registration failed. Username or email may already be in use.'];
        }
    }
}
