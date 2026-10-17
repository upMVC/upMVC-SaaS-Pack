<?php

namespace BitsHost\UpmvcSaas\Ops;

use BitsHost\UpmvcSaas\Mail\MailGateway;

/**
 * Generic Attention items for PlatformAdmin overview.
 * Apps can merge extra domain items on top.
 */
final class Attention
{
    /**
     * @return list<array{tone:string,key:string,tab?:string,text:string,meta?:array}>
     */
    public static function collect(): array
    {
        $items = [];

        $mail = new MailGateway();
        if (!$mail->isNetworkConfigured()) {
            $items[] = [
                'tone' => 'bad',
                'key'  => 'smtp',
                'tab'  => 'email',
                'text' => 'Platform network SMTP is not configured — outbound mail will be skipped.',
            ];
        }

        try {
            $pdo = (new \App\Etc\Database())->getConnection();
            $failed = (int) $pdo->query(
                "SELECT COUNT(*) FROM email_log
                 WHERE status = 'failed' AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)"
            )->fetchColumn();
            if ($failed > 0) {
                $items[] = [
                    'tone' => 'bad',
                    'key'  => 'email_failed',
                    'tab'  => 'email',
                    'meta' => ['count' => $failed],
                    'text' => "{$failed} email(s) failed in the last 24 hours.",
                ];
            }
        } catch (\Throwable) {
        }

        $sched = SchedulerHeartbeat::health();
        if ($sched['stale']) {
            $items[] = [
                'tone' => 'bad',
                'key'  => 'scheduler',
                'tab'  => 'ops',
                'meta' => ['last_run' => $sched['last_run']],
                'text' => $sched['last_run']
                    ? 'Scheduler heartbeat is stale — check cron.'
                    : 'No scheduler heartbeat yet — digests will not run until cron is configured.',
            ];
        }

        $flags = PlatformSettings::flags();
        if ($flags['maintenance_mode']) {
            $items[] = [
                'tone' => 'warn',
                'key'  => 'maintenance',
                'tab'  => 'ops',
                'text' => 'Maintenance mode is ON.',
            ];
        }
        if (!$flags['signup_enabled']) {
            $items[] = [
                'tone' => 'signal',
                'key'  => 'signup_off',
                'tab'  => 'ops',
                'text' => 'Public signup is disabled.',
            ];
        }

        return $items;
    }
}
