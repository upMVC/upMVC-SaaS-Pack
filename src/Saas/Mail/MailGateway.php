<?php

namespace BitsHost\UpmvcSaas\Mail;

use App\Common\Bmvc\BaseModel;
use App\Etc\Config\Environment;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * Outbound mail via Composer PHPMailer (phpmailer/phpmailer).
 *
 * Routing for tenant notifications (tenantId set):
 * 1) Tenant custom SMTP — only if plan feature custom_smtp AND smtp_settings.enabled
 * 2) Network / platform SMTP (smtp_settings WHERE tenant_id IS NULL, enabled)
 * 3) .env MAIL_* fallback (still logged as transport=platform)
 * 4) else skip + log
 *
 * Platform admin sends use steps 2–4 only (no tenant SMTP).
 */
class MailGateway extends BaseModel
{
    private const RESERVED = '/\.(test|invalid|example|localhost|local)$/i';
    private const PORTS = [25, 465, 587, 2525];

    public static function isTestAddress(string $email): bool
    {
        return (bool) preg_match(self::RESERVED, trim($email));
    }

    public function isConfigured(?int $tenantId = null): bool
    {
        return $this->resolveConfig($tenantId) !== null;
    }

    public function isNetworkConfigured(): bool
    {
        return $this->resolveConfig(null, false) !== null;
    }

    /**
     * Active delivery route for UI / diagnostics.
     *
     * @return array{engine:string,mode:string,allowed_custom:bool,network:bool,from_email:?string,from_name:?string}
     */
    public function describeRoute(?int $tenantId = null): array
    {
        $allowed = $tenantId ? $this->tenantHasCustomSmtp($tenantId) : false;
        $cfg = $this->resolveConfig($tenantId);
        $network = $this->isNetworkConfigured();
        $mode = 'none';
        if ($cfg) {
            $mode = ($cfg['transport'] ?? '') === 'tenant' ? 'tenant' : 'platform';
            if (($cfg['transport'] ?? '') === 'platform' && !empty($cfg['from_env'])) {
                $mode = 'env';
            }
        }
        return [
            'engine'         => 'phpmailer',
            'mode'           => $mode,
            'allowed_custom' => $allowed,
            'network'        => $network,
            'from_email'     => $cfg['from_email'] ?? null,
            'from_name'      => $cfg['from_name'] ?? null,
        ];
    }

    /**
     * @return array{status:string,transport?:string,error?:string,engine?:string}
     */
    public function send(
        string $to,
        string $subject,
        string $html,
        string $template = 'generic',
        ?int $tenantId = null,
        ?string $type = null,
        ?string $text = null,
        bool $forcePlatform = false
    ): array {
        $to = trim($to);
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return $this->finish($tenantId, $to, $subject, $template, $type, 'none', 'failed', 'Invalid recipient');
        }
        if (self::isTestAddress($to)) {
            return $this->finish($tenantId, $to, $subject, $template, $type, 'none', 'skipped', 'Reserved test address');
        }

        $cfg = $this->resolveConfig($forcePlatform ? null : $tenantId, !$forcePlatform);
        if ($cfg === null) {
            return $this->finish($tenantId, $to, $subject, $template, $type, 'none', 'skipped', 'No SMTP server configured');
        }

        try {
            $mail = $this->buildMailer($cfg);
            $mail->setFrom($cfg['from_email'], $cfg['from_name'] ?: 'App');
            $mail->addAddress($to);
            if (!empty($cfg['reply_to'])) {
                $mail->addReplyTo($cfg['reply_to']);
            }
            $mail->Subject = $subject;
            $mail->Body    = $html;
            $mail->AltBody = $text ?? strip_tags($html);
            $mail->send();
            $out = $this->finish($tenantId, $to, $subject, $template, $type, $cfg['transport'], 'sent', null);
            $out['engine'] = 'phpmailer';
            return $out;
        } catch (\Throwable $e) {
            $out = $this->finish(
                $tenantId,
                $to,
                $subject,
                $template,
                $type,
                $cfg['transport'] ?? 'none',
                'failed',
                mb_substr($e->getMessage(), 0, 500)
            );
            $out['engine'] = 'phpmailer';
            return $out;
        }
    }

    /**
     * Test SMTP: uses form override when host present, else saved row (platform or tenant).
     * @param array<string,mixed>|null $override
     * @return array{ok:bool,to:string,error?:string}
     */
    public function test(?array $override, string $to, bool $updateSavedMeta = true, ?int $tenantId = null): array
    {
        $to = trim($to);
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'to' => $to, 'error' => 'Invalid test recipient'];
        }
        if ($tenantId && !$this->tenantHasCustomSmtp($tenantId)) {
            return ['ok' => false, 'to' => $to, 'error' => 'Custom SMTP is not included in your plan'];
        }

        $saved = $tenantId ? $this->tenantRow($tenantId) : $this->platformRow();
        $transport = $tenantId ? 'tenant' : 'platform';
        try {
            $cfg = $override && !empty($override['host'])
                ? $this->normalizeInput($override, $saved)
                : $this->rowToConfig($saved);
            if ($cfg === null) {
                return ['ok' => false, 'to' => $to, 'error' => 'Enter SMTP settings first'];
            }
            $cfg['transport'] = $transport;

            $mail = $this->buildMailer($cfg);
            $mail->smtpConnect();
            $mail->smtpClose();

            if (!self::isTestAddress($to)) {
                $mail = $this->buildMailer($cfg);
                $mail->setFrom($cfg['from_email'], $cfg['from_name'] ?: 'App');
                $mail->addAddress($to);
                $mail->Subject = 'SMTP test';
                $scope = $tenantId ? 'workspace' : 'platform';
                $mail->Body    = '<p>SMTP test from App ' . $scope . ' gateway.</p><p>Server: '
                    . htmlspecialchars($cfg['host'] . ':' . $cfg['port']) . '</p>';
                $mail->AltBody = 'SMTP test from App ' . $scope . ' gateway.';
                $mail->send();
            }

            if ($updateSavedMeta && $saved && empty($override['host'])) {
                $this->markTest((int) $saved['id'], true, null);
            }
            $this->writeLog($tenantId, $to, 'SMTP test', 'smtp_test', null, $transport, 'sent', null);
            return ['ok' => true, 'to' => $to];
        } catch (\Throwable $e) {
            $err = mb_substr($e->getMessage(), 0, 500);
            if ($tenantId) {
                // Don't leak raw SMTP banners to tenant admins
                $err = 'Connection or authentication failed. Check host, port, and credentials.';
            }
            if ($updateSavedMeta && $saved && empty($override['host'] ?? null)) {
                $this->markTest((int) $saved['id'], false, $err);
            }
            $this->writeLog($tenantId, $to, 'SMTP test', 'smtp_test', null, $transport, 'failed', $err);
            return ['ok' => false, 'to' => $to, 'error' => $err];
        }
    }

    /** @return array<string,mixed>|null public view */
    public function getPublicPlatform(): ?array
    {
        $row = $this->platformRow();
        return $row ? $this->toPublic($row) : null;
    }

    /** @param array<string,mixed> $body */
    public function savePlatform(array $body): array
    {
        $cur = $this->platformRow();
        $v   = $this->normalizeInput($body, $cur);
        if ($cur) {
            $stmt = $this->conn->prepare(
                "UPDATE smtp_settings SET
                    host=:host, port=:port, secure=:secure, username=:username, password_enc=:password_enc,
                    from_email=:from_email, from_name=:from_name, reply_to=:reply_to, enabled=:enabled,
                    last_test_at=NULL, last_test_ok=NULL, last_error=NULL
                 WHERE id=:id AND tenant_id IS NULL"
            );
            $stmt->execute([
                ':id' => $cur['id'],
                ':host' => $v['host'],
                ':port' => $v['port'],
                ':secure' => $v['secure'],
                ':username' => $v['username'],
                ':password_enc' => $v['password_enc'],
                ':from_email' => $v['from_email'],
                ':from_name' => $v['from_name'],
                ':reply_to' => $v['reply_to'],
                ':enabled' => $v['enabled'],
            ]);
        } else {
            $stmt = $this->conn->prepare(
                "INSERT INTO smtp_settings
                    (tenant_id, host, port, secure, username, password_enc, from_email, from_name, reply_to, enabled)
                 VALUES
                    (NULL, :host, :port, :secure, :username, :password_enc, :from_email, :from_name, :reply_to, :enabled)"
            );
            $stmt->execute([
                ':host' => $v['host'],
                ':port' => $v['port'],
                ':secure' => $v['secure'],
                ':username' => $v['username'],
                ':password_enc' => $v['password_enc'],
                ':from_email' => $v['from_email'],
                ':from_name' => $v['from_name'],
                ':reply_to' => $v['reply_to'],
                ':enabled' => $v['enabled'],
            ]);
        }
        return $this->getPublicPlatform() ?? [];
    }

    public function deletePlatform(): bool
    {
        return $this->conn->exec('DELETE FROM smtp_settings WHERE tenant_id IS NULL') !== false;
    }

    public function getPublicTenant(int $tenantId): ?array
    {
        $row = $this->tenantRow($tenantId);
        return $row ? $this->toPublic($row) : null;
    }

    public function saveTenant(int $tenantId, array $body): array
    {
        if (!$this->tenantHasCustomSmtp($tenantId)) {
            throw new \InvalidArgumentException('Custom SMTP is not included in your plan');
        }
        $cur = $this->tenantRow($tenantId);
        $v   = $this->normalizeInput($body, $cur);
        if ($cur) {
            $stmt = $this->conn->prepare(
                "UPDATE smtp_settings SET
                    host=:host, port=:port, secure=:secure, username=:username, password_enc=:password_enc,
                    from_email=:from_email, from_name=:from_name, reply_to=:reply_to, enabled=:enabled,
                    last_test_at=NULL, last_test_ok=NULL, last_error=NULL
                 WHERE id=:id AND tenant_id=:tid"
            );
            $stmt->execute([
                ':id' => $cur['id'],
                ':tid' => $tenantId,
                ':host' => $v['host'],
                ':port' => $v['port'],
                ':secure' => $v['secure'],
                ':username' => $v['username'],
                ':password_enc' => $v['password_enc'],
                ':from_email' => $v['from_email'],
                ':from_name' => $v['from_name'],
                ':reply_to' => $v['reply_to'],
                ':enabled' => $v['enabled'],
            ]);
        } else {
            $stmt = $this->conn->prepare(
                "INSERT INTO smtp_settings
                    (tenant_id, host, port, secure, username, password_enc, from_email, from_name, reply_to, enabled)
                 VALUES
                    (:tid, :host, :port, :secure, :username, :password_enc, :from_email, :from_name, :reply_to, :enabled)"
            );
            $stmt->execute([
                ':tid' => $tenantId,
                ':host' => $v['host'],
                ':port' => $v['port'],
                ':secure' => $v['secure'],
                ':username' => $v['username'],
                ':password_enc' => $v['password_enc'],
                ':from_email' => $v['from_email'],
                ':from_name' => $v['from_name'],
                ':reply_to' => $v['reply_to'],
                ':enabled' => $v['enabled'],
            ]);
        }
        return $this->getPublicTenant($tenantId) ?? [];
    }

    public function deleteTenant(int $tenantId): bool
    {
        $stmt = $this->conn->prepare('DELETE FROM smtp_settings WHERE tenant_id = :tid');
        return $stmt->execute([':tid' => $tenantId]);
    }

    /** Tenant email page payload — network + optional custom SMTP. */
    public function tenantEmailBundle(int $tenantId): array
    {
        $platform = $this->platformRow();
        $emailsStmt = $this->conn->prepare(
            "SELECT id, to_email, subject, template, transport, status, error, created_at
             FROM email_log WHERE tenant_id = :tid
             ORDER BY created_at DESC LIMIT 25"
        );
        $emailsStmt->execute([':tid' => $tenantId]);
        $route = $this->describeRoute($tenantId);
        $tenantSmtp = $this->getPublicTenant($tenantId);
        // Hide stored custom SMTP from UI when plan does not allow it
        if (!$route['allowed_custom']) {
            $tenantSmtp = null;
        }
        return [
            'engine'   => 'phpmailer',
            'route'    => $route,
            'allowed'  => $route['allowed_custom'],
            'smtp'     => $tenantSmtp,
            'platform' => ($platform && (int) $platform['enabled'] === 1)
                ? [
                    'from_email' => $platform['from_email'],
                    'from_name'  => $platform['from_name'],
                    'enabled'    => true,
                ]
                : ($this->isNetworkConfigured()
                    ? ['from_email' => (string) Environment::get('MAIL_FROM_ADDRESS', ''), 'from_name' => (string) Environment::get('MAIL_FROM_NAME', 'App'), 'enabled' => true, 'via_env' => true]
                    : null),
            'emails'   => $emailsStmt->fetchAll(\PDO::FETCH_ASSOC),
        ];
    }

    public function tenantHasCustomSmtp(int $tenantId): bool
    {
        $stmt = $this->conn->prepare(
            "SELECT t.features AS tenant_features, p.features AS plan_features
             FROM tenants t
             LEFT JOIN plans p ON p.id = t.plan_id
             WHERE t.id = :tid AND t.deleted_at IS NULL LIMIT 1"
        );
        $stmt->execute([':tid' => $tenantId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            return false;
        }
        $tf = json_decode($row['tenant_features'] ?? '{}', true) ?: [];
        $pf = json_decode($row['plan_features'] ?? '{}', true) ?: [];
        if (array_key_exists('custom_smtp', $tf)) {
            return !empty($tf['custom_smtp']);
        }
        return !empty($pf['custom_smtp']);
    }

    /** @return array{emails:list,summary:array} */
    public function listLog(?string $status = 'all', int $limit = 100, ?int $tenantId = null): array
    {
        $limit = max(1, min(500, $limit));
        $sql = "SELECT e.*, t.name AS tenant_name
                FROM email_log e
                LEFT JOIN tenants t ON t.id = e.tenant_id
                WHERE 1=1";
        $params = [];
        if ($tenantId !== null) {
            $sql .= ' AND e.tenant_id = :tid';
            $params[':tid'] = $tenantId;
        }
        if ($status && $status !== 'all') {
            $sql .= ' AND e.status = :st';
            $params[':st'] = $status;
        }
        $sql .= " ORDER BY e.created_at DESC LIMIT {$limit}";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        $emails = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if ($tenantId !== null) {
            $sumStmt = $this->conn->prepare(
                "SELECT
                    SUM(status='sent') AS sent,
                    SUM(status='failed') AS failed,
                    SUM(status='skipped') AS skipped,
                    SUM(transport='platform') AS via_platform,
                    SUM(transport='tenant') AS via_tenant
                 FROM email_log
                 WHERE tenant_id = :tid AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)"
            );
            $sumStmt->execute([':tid' => $tenantId]);
            $sum = $sumStmt->fetch(\PDO::FETCH_ASSOC) ?: [];
        } else {
            $sum = $this->conn->query(
                "SELECT
                    SUM(status='sent') AS sent,
                    SUM(status='failed') AS failed,
                    SUM(status='skipped') AS skipped,
                    SUM(transport='platform') AS via_platform,
                    SUM(transport='tenant') AS via_tenant
                 FROM email_log
                 WHERE created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)"
            )->fetch(\PDO::FETCH_ASSOC) ?: [];
        }

        return [
            'emails'  => $emails,
            'summary' => [
                'sent'         => (int) ($sum['sent'] ?? 0),
                'failed'       => (int) ($sum['failed'] ?? 0),
                'skipped'      => (int) ($sum['skipped'] ?? 0),
                'via_platform' => (int) ($sum['via_platform'] ?? 0),
                'via_tenant'   => (int) ($sum['via_tenant'] ?? 0),
                'smtp_configured' => $this->isConfigured($tenantId),
            ],
        ];
    }

    /** @param array<string,mixed> $body @param array<string,mixed>|false|null $cur */
    private function normalizeInput(array $body, $cur): array
    {
        $host = strtolower(trim((string) ($body['host'] ?? '')));
        if ($host === '' || !preg_match('/^[a-z0-9.-]+$/', $host) || strlen($host) > 253) {
            throw new \InvalidArgumentException('SMTP host is invalid');
        }
        $port = (int) ($body['port'] ?? 587);
        if (!in_array($port, self::PORTS, true)) {
            throw new \InvalidArgumentException('Port must be one of ' . implode(', ', self::PORTS));
        }
        $from = trim((string) ($body['from_email'] ?? $body['fromEmail'] ?? ''));
        if ($from === '' || !filter_var($from, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('From email is invalid');
        }
        $username = trim((string) ($body['username'] ?? ''));
        $password = array_key_exists('password', $body) ? (string) $body['password'] : '';
        $enc = $cur['password_enc'] ?? null;
        if ($password !== '') {
            if (strlen($password) > 500) {
                throw new \InvalidArgumentException('Password is too long');
            }
            $enc = \BitsHost\UpmvcSaas\Security\SecretBox::seal($password);
        } elseif ($username === '') {
            $enc = null;
        }
        $reply = trim((string) ($body['reply_to'] ?? $body['replyTo'] ?? ''));
        if ($reply !== '' && !filter_var($reply, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Reply-To is invalid');
        }
        $secure = array_key_exists('secure', $body)
            ? (!empty($body['secure']) ? 1 : 0)
            : ($port === 465 ? 1 : 0);

        return [
            'host'         => $host,
            'port'         => $port,
            'secure'       => $secure,
            'username'     => $username !== '' ? $username : null,
            'password_enc' => $enc,
            'from_email'   => $from,
            'from_name'    => mb_substr(trim((string) ($body['from_name'] ?? $body['fromName'] ?? '')), 0, 120) ?: null,
            'reply_to'     => $reply !== '' ? $reply : null,
            'enabled'      => array_key_exists('enabled', $body) ? (!empty($body['enabled']) ? 1 : 0) : 1,
        ];
    }

    private function platformRow(): array|false
    {
        return $this->conn->query(
            'SELECT * FROM smtp_settings WHERE tenant_id IS NULL LIMIT 1'
        )->fetch(\PDO::FETCH_ASSOC);
    }

    private function tenantRow(int $tenantId): array|false
    {
        $stmt = $this->conn->prepare(
            'SELECT * FROM smtp_settings WHERE tenant_id = :tid LIMIT 1'
        );
        $stmt->execute([':tid' => $tenantId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * @param bool $allowTenantSmtp when false, skip tenant custom SMTP (network-only)
     * @return array<string,mixed>|null
     */
    private function resolveConfig(?int $tenantId = null, bool $allowTenantSmtp = true): ?array
    {
        // Custom SMTP only when plan includes custom_smtp and settings are enabled
        if ($allowTenantSmtp && $tenantId && $this->tenantHasCustomSmtp($tenantId)) {
            $t = $this->tenantRow($tenantId);
            if ($t && (int) $t['enabled'] === 1) {
                $cfg = $this->rowToConfig($t);
                if ($cfg) {
                    $cfg['transport'] = 'tenant';
                    return $cfg;
                }
            }
        }

        // Network / platform gateway (shared for all tenants)
        $row = $this->platformRow();
        if ($row && (int) $row['enabled'] === 1) {
            $cfg = $this->rowToConfig($row);
            if ($cfg) {
                $cfg['transport'] = 'platform';
                return $cfg;
            }
        }

        // .env fallback (still "network" for tenants)
        $host = (string) Environment::get('MAIL_HOST', '');
        if ($host === '') {
            return null;
        }
        return [
            'host'       => $host,
            'port'       => (int) Environment::get('MAIL_PORT', 587),
            'secure'     => strtolower((string) Environment::get('MAIL_ENCRYPTION', 'tls')) === 'ssl',
            'username'   => (string) Environment::get('MAIL_USERNAME', ''),
            'password'   => (string) Environment::get('MAIL_PASSWORD', ''),
            'from_email' => (string) Environment::get('MAIL_FROM_ADDRESS', 'noreply@example.com'),
            'from_name'  => (string) Environment::get('MAIL_FROM_NAME', 'App'),
            'reply_to'   => null,
            'transport'  => 'platform',
            'from_env'   => true,
            'encryption' => (string) Environment::get('MAIL_ENCRYPTION', 'tls'),
        ];
    }

    /** @param array<string,mixed>|false|null $row */
    private function rowToConfig($row): ?array
    {
        if (!$row || empty($row['host'])) {
            return null;
        }
        return [
            'host'       => $row['host'],
            'port'       => (int) $row['port'],
            'secure'     => (int) $row['secure'] === 1,
            'username'   => (string) ($row['username'] ?? ''),
            'password'   => (string) (\BitsHost\UpmvcSaas\Security\SecretBox::unseal($row['password_enc'] ?? null) ?? ''),
            'from_email' => $row['from_email'],
            'from_name'  => $row['from_name'] ?? '',
            'reply_to'   => $row['reply_to'] ?? null,
            'encryption' => ((int) $row['secure'] === 1) ? 'ssl' : 'tls',
        ];
    }

    /** @param array<string,mixed> $row */
    private function toPublic(array $row): array
    {
        return [
            'host'         => $row['host'],
            'port'         => (int) $row['port'],
            'secure'       => (int) $row['secure'] === 1,
            'username'     => $row['username'],
            'has_password' => !empty($row['password_enc']),
            'from_email'   => $row['from_email'],
            'from_name'    => $row['from_name'],
            'reply_to'     => $row['reply_to'],
            'enabled'      => (int) $row['enabled'] === 1,
            'last_test_at' => $row['last_test_at'],
            'last_test_ok' => $row['last_test_ok'] === null ? null : ((int) $row['last_test_ok'] === 1),
            'last_error'   => $row['last_error'],
            'updated_at'   => $row['updated_at'] ?? null,
        ];
    }

    /** @param array<string,mixed> $cfg */
    private function buildMailer(array $cfg): PHPMailer
    {
        $mail = new PHPMailer(true);
        $mail->CharSet = 'UTF-8';
        $mail->isSMTP();
        $mail->Host = $cfg['host'];
        $mail->Port = (int) $cfg['port'];
        $mail->SMTPAuth = ($cfg['username'] ?? '') !== '';
        if ($mail->SMTPAuth) {
            $mail->Username = $cfg['username'];
            $mail->Password = $cfg['password'] ?? '';
        }
        $enc = $cfg['encryption'] ?? (!empty($cfg['secure']) ? 'ssl' : 'tls');
        if ($enc === 'ssl' || !empty($cfg['secure'])) {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } else {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->SMTPAutoTLS = true;
        }
        $mail->Timeout = 12;
        $mail->isHTML(true);
        return $mail;
    }

    private function markTest(int $id, bool $ok, ?string $error): void
    {
        $stmt = $this->conn->prepare(
            'UPDATE smtp_settings SET last_test_at=NOW(), last_test_ok=:ok, last_error=:err WHERE id=:id'
        );
        $stmt->execute([':ok' => $ok ? 1 : 0, ':err' => $error, ':id' => $id]);
    }

    /** @return array{status:string,transport?:string,error?:string} */
    private function finish(
        ?int $tenantId,
        string $to,
        string $subject,
        string $template,
        ?string $type,
        string $transport,
        string $status,
        ?string $error
    ): array {
        $this->writeLog($tenantId, $to, $subject, $template, $type, $transport, $status, $error);
        $out = ['status' => $status, 'transport' => $transport];
        if ($error) {
            $out['error'] = $error;
        }
        return $out;
    }

    private function writeLog(
        ?int $tenantId,
        string $to,
        string $subject,
        string $template,
        ?string $type,
        string $transport,
        string $status,
        ?string $error
    ): void {
        $stmt = $this->conn->prepare(
            "INSERT INTO email_log (tenant_id, to_email, subject, template, transport, status, error, type)
             VALUES (:tid, :to, :subj, :tpl, :tr, :st, :err, :type)"
        );
        $stmt->execute([
            ':tid'  => $tenantId,
            ':to'   => mb_substr($to, 0, 254),
            ':subj' => mb_substr($subject, 0, 300),
            ':tpl'  => mb_substr($template, 0, 80),
            ':tr'   => $transport,
            ':st'   => $status,
            ':err'  => $error,
            ':type' => $type,
        ]);
    }
}
