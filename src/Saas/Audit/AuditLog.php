<?php

namespace BitsHost\UpmvcSaas\Audit;

use App\Etc\Database;

/**
 * Append-only audit trail (tenant or platform).
 * When the JWT has impersonated=true, pass impersonator_id so Activity can filter.
 */
class AuditLog
{
    /**
     * @param array<string,mixed> $user JWT / session user claims
     * @return array{userId:?int,impersonatorId:?int}
     */
    public static function actorsFromUser(array $user): array
    {
        $uid = (int) ($user['sub'] ?? $user['id'] ?? 0);
        $imp = 0;
        if (!empty($user['impersonated']) || !empty($user['impersonator_id'])) {
            $imp = (int) ($user['impersonator_id'] ?? 0);
        }
        return [
            'userId'         => $uid > 0 ? $uid : null,
            'impersonatorId' => $imp > 0 ? $imp : null,
        ];
    }

    /**
     * @param array<string,mixed> $details
     */
    public static function write(
        ?int $tenantId,
        ?int $userId,
        string $entity,
        ?int $entityId,
        string $action,
        array $details = [],
        ?int $impersonatorId = null
    ): void {
        if ($entity === '' || $action === '') {
            return;
        }
        $tid = ($tenantId !== null && $tenantId > 0) ? $tenantId : null;
        try {
            $pdo = (new Database())->getConnection();
            $stmt = $pdo->prepare(
                'INSERT INTO audit_log (tenant_id, user_id, impersonator_id, entity, entity_id, action, details)
                 VALUES (:tid, :uid, :imp, :entity, :entity_id, :action, :details)'
            );
            $stmt->execute([
                ':tid'       => $tid,
                ':uid'       => $userId ?: null,
                ':imp'       => $impersonatorId ?: null,
                ':entity'    => mb_substr($entity, 0, 64),
                ':entity_id' => $entityId,
                ':action'    => mb_substr($action, 0, 64),
                ':details'   => json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
        } catch (\Throwable) {
            // Never break the primary action for audit failures
        }
    }

    /** Convenience: stamp impersonator from JWT claims when present. */
    public static function writeAs(
        array $user,
        ?int $tenantId,
        string $entity,
        ?int $entityId,
        string $action,
        array $details = []
    ): void {
        $a = self::actorsFromUser($user);
        self::write($tenantId, $a['userId'], $entity, $entityId, $action, $details, $a['impersonatorId']);
    }
}
