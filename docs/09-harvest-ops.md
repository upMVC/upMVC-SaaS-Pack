# 09 — Harvest: mail, notify kernel, ops, i18n, MFA

This layer was proven in `myCMMS-PHP` and harvested into the pack so every SaaS product gets the same factory pieces without CMMS domain coupling.

## What ships

| Piece | Namespace / path | Role |
|---|---|---|
| Mail gateway | `BitsHost\UpmvcSaas\Mail\MailGateway` | Platform SMTP + plan-gated tenant SMTP + `.env` fallback + `email_log` |
| Secret seal | `BitsHost\UpmvcSaas\Security\SecretBox` | AES-GCM for SMTP passwords |
| Notify registry | `BitsHost\UpmvcSaas\Notify\NotifyRegistry` | Apps **register** types; pack stays empty |
| Notify gate | `BitsHost\UpmvcSaas\Notify\NotifyGate` | Global pause / kill switch |
| Notify fire | `BitsHost\UpmvcSaas\Notify\Notify` | Instant send with mute + MailGateway |
| Audit | `BitsHost\UpmvcSaas\Audit\AuditLog` | Append-only trail + impersonator |
| TOTP | `BitsHost\UpmvcSaas\Security\Totp` | Platform MFA helper |
| Attention | `BitsHost\UpmvcSaas\Ops\Attention` | SMTP / email fails / scheduler / flags |
| Settings | `BitsHost\UpmvcSaas\Ops\PlatformSettings` | Key/value platform flags |
| Heartbeat | `BitsHost\UpmvcSaas\Ops\SchedulerHeartbeat` | Cron liveness |
| i18n catalog | `BitsHost\UpmvcSaas\I18n\Catalog` | File + DB overlays |
| Migration | `002_saas_ops_layer.sql` | Tables for the above |
| API | `Api/Modules/PlatformOps`, `Api/Modules/I18n` | Thin routes apps can override |
| Shell i18n seed | `public/lang/{en,ro}.json` | Minimal chrome keys |

## App wiring

```php
use BitsHost\UpmvcSaas\Notify\NotifyRegistry;
use BitsHost\UpmvcSaas\Notify\Notify;

// boot / service provider
NotifyRegistry::registerMany([
    ['key' => 'invoice_paid', 'zone' => 'tenant', 'kind' => 'instant', 'enabled' => true],
]);

// domain code
Notify::fire($tenantId, 'invoice_paid', [['id' => 1, 'email' => $to]], function (array $u) {
    return ['subject' => 'Paid', 'html' => '<p>Thanks</p>'];
});
```

Override `App\Modules\Api\Modules\PlatformOps` in the consuming app to add domain Attention items (plan limits, trials, CMMS metrics, …).

## Deliberately not in this harvest

- Domain notify types (WO/PM/stock, …)
- Payments / billing (optional future pack)
- Full PlatformAdmin UI tabs (apps override shells)
- AI translate (after overlays are used in production)

## Rule of thumb

If car-rental and ContaPlus both need it before domain logic → saas pack.  
If it pulls a heavy vendor SDK or is vertical-only → new pack or app module.
