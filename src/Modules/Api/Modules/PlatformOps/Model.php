<?php

namespace App\Modules\Api\Modules\PlatformOps;

use App\Common\Bmvc\BaseModel;
use BitsHost\UpmvcSaas\Audit\AuditLog;
use BitsHost\UpmvcSaas\Security\Totp;

class Model extends BaseModel
{
    public function mfaStatus(int $userId): array
    {
        $row = $this->findAdminMfa($userId);
        return [
            'enabled'       => !empty($row['totp_enabled']),
            'pending_setup' => empty($row['totp_enabled']) && !empty($row['totp_secret_enc']),
        ];
    }

    public function mfaSetup(int $userId): array
    {
        $row = $this->findAdminMfa($userId);
        if (!$row) {
            throw new \InvalidArgumentException('User not found');
        }
        if (!empty($row['totp_enabled'])) {
            throw new \InvalidArgumentException('Sign-in codes are already on. Turn them off first to move to a new device.');
        }
        $secret = Totp::newSecret();
        $this->conn->prepare(
            "UPDATE users SET totp_secret_enc = :s, totp_last_step = NULL
             WHERE id = :id AND role = 'platform_admin'"
        )->execute([':s' => Totp::seal($secret), ':id' => $userId]);
        $account = (string) ($row['email'] ?: $row['username']);
        return [
            'secret' => $secret,
            'uri'    => Totp::otpauthUri($secret, $account, 'upMVC SaaS'),
        ];
    }

    public function mfaEnable(int $userId, string $code): void
    {
        $row = $this->findAdminMfa($userId);
        if (!$row) {
            throw new \InvalidArgumentException('User not found');
        }
        if (!empty($row['totp_enabled'])) {
            return;
        }
        if (empty($row['totp_secret_enc'])) {
            throw new \InvalidArgumentException('Start the setup again');
        }
        $secret = Totp::unseal((string) $row['totp_secret_enc']);
        if ($secret === null) {
            throw new \InvalidArgumentException('Start the setup again');
        }
        $last = $row['totp_last_step'] !== null ? (int) $row['totp_last_step'] : null;
        $step = Totp::verify($secret, $code, $last);
        if ($step === null) {
            throw new \InvalidArgumentException('That code is not valid');
        }
        $this->conn->prepare(
            'UPDATE users SET totp_enabled = 1, totp_last_step = :step WHERE id = :id'
        )->execute([':step' => $step, ':id' => $userId]);
        AuditLog::write(null, $userId, 'user', $userId, 'mfa_enabled', []);
    }

    public function mfaDisable(int $userId, string $password, string $code = ''): void
    {
        $row = $this->findAdminMfa($userId);
        if (!$row) {
            throw new \InvalidArgumentException('User not found');
        }
        if (empty($row['totp_enabled'])) {
            return;
        }
        if (!password_verify($password, (string) $row['password'])) {
            throw new \InvalidArgumentException('Current password is incorrect');
        }
        $secret = Totp::unseal((string) ($row['totp_secret_enc'] ?? ''));
        $last = $row['totp_last_step'] !== null ? (int) $row['totp_last_step'] : null;
        if ($secret === null || Totp::verify($secret, $code, $last) === null) {
            throw new \InvalidArgumentException('That code is not valid');
        }
        $this->conn->prepare(
            'UPDATE users SET totp_enabled = 0, totp_secret_enc = NULL, totp_last_step = NULL WHERE id = :id'
        )->execute([':id' => $userId]);
        AuditLog::write(null, $userId, 'user', $userId, 'mfa_disabled', []);
    }

    /** @return array<string,mixed>|false */
    public function findAdminMfa(int $userId): array|false
    {
        try {
            $stmt = $this->conn->prepare(
                "SELECT id, username, email, password, totp_enabled, totp_secret_enc, totp_last_step
                 FROM users WHERE id = :id AND role = 'platform_admin' LIMIT 1"
            );
            $stmt->execute([':id' => $userId]);
            return $stmt->fetch(\PDO::FETCH_ASSOC) ?: false;
        } catch (\Throwable) {
            return false;
        }
    }
}
