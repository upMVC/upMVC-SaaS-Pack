<?php

declare(strict_types=1);

namespace BitsHost\UpmvcSaas\Tests\Unit;

use BitsHost\UpmvcSaas\Audit\AuditLog;
use BitsHost\UpmvcSaas\I18n\Catalog;
use BitsHost\UpmvcSaas\Notify\NotifyRegistry;
use BitsHost\UpmvcSaas\Security\SecretBox;
use BitsHost\UpmvcSaas\Security\Totp;
use PHPUnit\Framework\TestCase;

final class HarvestKernelTest extends TestCase
{
    protected function tearDown(): void
    {
        NotifyRegistry::clear();
    }

    public function testTotpGeneratesAndVerifies(): void
    {
        $secret = Totp::newSecret();
        $this->assertMatchesRegularExpression('/^[A-Z2-7]+$/', $secret);
        $code = Totp::codeAt($secret);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
        $step = Totp::verify($secret, $code);
        $this->assertNotNull($step);
        $this->assertNull(Totp::verify($secret, '000000', $step));
    }

    public function testTotpSealRoundTrip(): void
    {
        // Seal needs JWT_SECRET — set a test value
        $_ENV['JWT_SECRET'] = 'harvest-test-secret-not-for-prod';
        putenv('JWT_SECRET=harvest-test-secret-not-for-prod');
        $secret = Totp::newSecret();
        $sealed = Totp::seal($secret);
        $this->assertStringStartsWith('v1:', $sealed);
        $this->assertSame($secret, Totp::unseal($sealed));
    }

    /** Pack unseal must still read myCMMS-era SMTP seals (`|smtp-password-v1`). */
    public function testSecretBoxUnsealsLegacySmtpPepper(): void
    {
        $src = 'harvest-dual-unseal-app-key';
        $_ENV['APP_KEY'] = $src;
        putenv('APP_KEY=' . $src);
        unset($_ENV['JWT_SECRET']);
        putenv('JWT_SECRET');

        $plain = 'smtp-password-legacy-proof';
        $key = hash('sha256', $src . '|smtp-password-v1', true);
        $iv = random_bytes(12);
        $tag = '';
        $ct = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        $this->assertNotFalse($ct);
        $blob = 'v1:' . base64_encode($iv . $tag . $ct);

        $this->assertSame($plain, SecretBox::unseal($blob));
        // New writes use pack pepper only
        $this->assertSame($plain, SecretBox::unseal(SecretBox::seal($plain)));
    }

    /** Pack unseal must still read myCMMS-era TOTP seals (`mycmms-totp|`). */
    public function testTotpUnsealsLegacyMycmmsPepper(): void
    {
        $jwt = 'harvest-dual-unseal-jwt-secret';
        $_ENV['JWT_SECRET'] = $jwt;
        putenv('JWT_SECRET=' . $jwt);

        $secret = Totp::newSecret();
        $key = hash('sha256', 'mycmms-totp|' . $jwt, true);
        $iv = random_bytes(16);
        $cipher = openssl_encrypt($secret, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        $this->assertNotFalse($cipher);
        $sealed = 'v1:' . base64_encode($iv . $cipher);

        $this->assertSame($secret, Totp::unseal($sealed));
    }

    public function testNotifyRegistryRegisterAndZone(): void
    {
        NotifyRegistry::register([
            'key'  => 'invoice_paid',
            'zone' => 'tenant',
            'kind' => 'instant',
        ]);
        NotifyRegistry::register([
            'key'  => 'mail_failures',
            'zone' => 'platform',
            'kind' => 'digest',
        ]);
        $this->assertTrue(NotifyRegistry::isValid('invoice_paid'));
        $this->assertCount(1, NotifyRegistry::forZone('tenant'));
        $this->assertCount(1, NotifyRegistry::forZone('platform'));
        $this->assertSame(['invoice_paid', 'mail_failures'], NotifyRegistry::keys());
    }

    public function testNotifyRegistryRejectsBadKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        NotifyRegistry::register(['key' => 'Bad-Key!']);
    }

    public function testAuditActorsFromUser(): void
    {
        $a = AuditLog::actorsFromUser([
            'sub' => 10,
            'impersonated' => true,
            'impersonator_id' => 2,
        ]);
        $this->assertSame(10, $a['userId']);
        $this->assertSame(2, $a['impersonatorId']);
    }

    public function testCatalogApplyOverlays(): void
    {
        $base = [
            'common' => ['save' => 'Save', 'name' => 'Name'],
            'platform' => ['ops_tab' => 'Ops'],
        ];
        $out = Catalog::applyOverlays($base, [
            'common.save' => 'Salvează',
            'platform.ops_tab' => 'Operațiuni',
            'platform.new_key' => 'New',
        ]);
        $this->assertSame('Salvează', $out['common']['save']);
        $this->assertSame('Name', $out['common']['name']);
        $this->assertSame('Operațiuni', $out['platform']['ops_tab']);
        $this->assertSame('New', $out['platform']['new_key']);
    }

    public function testCatalogLoadFromPackLang(): void
    {
        $dir = dirname(__DIR__, 2) . '/public/lang';
        $loaded = Catalog::load('en', $dir, ['en', 'ro'], false);
        $this->assertSame('en', $loaded['locale']);
        $this->assertSame('file', $loaded['source']);
        $this->assertIsArray($loaded['catalog']['common'] ?? null);
    }

    public function testMigration002ExistsAndMentionsOpsTables(): void
    {
        $sql = (string) file_get_contents(dirname(__DIR__, 2) . '/database/migrations/002_saas_ops_layer.sql');
        foreach (['smtp_settings', 'email_log', 'audit_log', 'notification_global', 'i18n_overlays', 'totp_enabled'] as $needle) {
            $this->assertStringContainsString($needle, $sql);
        }
    }
}
