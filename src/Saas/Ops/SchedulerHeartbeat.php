<?php

namespace BitsHost\UpmvcSaas\Ops;

/** Cron / scheduler liveness for Attention. */
final class SchedulerHeartbeat
{
    public static function beat(): string
    {
        $now = gmdate('c');
        PlatformSettings::set('scheduler_heartbeat', $now);
        return $now;
    }

    /**
     * @return array{last_run:?string,stale:bool,age_seconds:?int}
     */
    public static function health(int $staleAfterSeconds = 3600): array
    {
        $raw = trim((string) (PlatformSettings::get('scheduler_heartbeat') ?? ''));
        if ($raw === '') {
            return ['last_run' => null, 'stale' => true, 'age_seconds' => null];
        }
        $ts = strtotime($raw);
        if ($ts === false) {
            return ['last_run' => $raw, 'stale' => true, 'age_seconds' => null];
        }
        $age = time() - $ts;
        return [
            'last_run'     => $raw,
            'stale'        => $age > $staleAfterSeconds,
            'age_seconds'  => $age,
        ];
    }
}
