<?php

namespace BitsHost\UpmvcSaas\Notify;

use App\Etc\Database;
use BitsHost\UpmvcSaas\Mail\MailGateway;

/**
 * Fire product notifications (never throws into callers).
 * Honour: platform kill switch → personal mute → MailGateway.
 *
 * Digests / role resolution stay in the app — pass an explicit $users list.
 */
final class Notify
{
    /**
     * @param list<array{id?:int,email:string,name?:string}> $users
     * @param callable $build fn(array $user): array{subject:string,html:string,text?:string}
     * @return array{sent:int,skipped:int,reason?:string,error?:string}
     */
    public static function fire(
        ?int $tenantId,
        string $type,
        array $users,
        callable $build,
        ?int $excludeUserId = null
    ): array {
        try {
            if (!NotifyRegistry::isValid($type)) {
                return ['sent' => 0, 'skipped' => 0, 'reason' => 'unknown_type'];
            }
            if (NotifyGate::isPaused($type)) {
                return ['sent' => 0, 'skipped' => count($users), 'reason' => 'paused'];
            }

            $mail = new MailGateway();
            $sent = 0;
            $skipped = 0;
            foreach ($users as $user) {
                $uid = isset($user['id']) ? (int) $user['id'] : 0;
                if ($excludeUserId && $uid === $excludeUserId) {
                    $skipped++;
                    continue;
                }
                if ($uid > 0 && self::isMuted($uid, $type)) {
                    $skipped++;
                    continue;
                }
                $email = trim((string) ($user['email'] ?? ''));
                if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $skipped++;
                    continue;
                }
                $payload = $build($user);
                $subject = (string) ($payload['subject'] ?? '');
                $html = (string) ($payload['html'] ?? '');
                if ($subject === '' || $html === '') {
                    $skipped++;
                    continue;
                }
                $result = $mail->send(
                    $email,
                    $subject,
                    $html,
                    'notify',
                    $tenantId,
                    $type,
                    isset($payload['text']) ? (string) $payload['text'] : null
                );
                if (($result['status'] ?? '') === 'sent') {
                    $sent++;
                } else {
                    $skipped++;
                }
            }
            return ['sent' => $sent, 'skipped' => $skipped];
        } catch (\Throwable $e) {
            error_log('[saas-notify] ' . $type . ' ' . $e->getMessage());
            return ['sent' => 0, 'skipped' => 0, 'error' => $e->getMessage()];
        }
    }

    public static function isMuted(int $userId, string $type): bool
    {
        try {
            $pdo = (new Database())->getConnection();
            $stmt = $pdo->prepare(
                'SELECT 1 FROM notification_mutes WHERE user_id = :u AND mute_key = :k LIMIT 1'
            );
            $stmt->execute([':u' => $userId, ':k' => $type]);
            return (bool) $stmt->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
    }
}
