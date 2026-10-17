<?php

declare(strict_types=1);

namespace BitsHost\UpmvcSaas\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class SaasNextTest extends TestCase
{
    public function testSaasKitManifestIsValid(): void
    {
        $path = dirname(__DIR__, 2) . '/saas-kit.json';
        $this->assertFileExists($path);
        $data = json_decode((string) file_get_contents($path), true);
        $this->assertIsArray($data);
        $this->assertSame('saas', $data['id']);
        $this->assertSame('bitshost/upmvc-saas-pack', $data['composer']);
        $this->assertSame('BitsHost\\UpmvcSaas\\SaasServiceProvider', $data['provider']);
        $this->assertContains('JWT_SECRET', $data['env_keys']);
        $this->assertNotEmpty($data['agent_hints']);
    }

    public function testBinExistsAndIsParseable(): void
    {
        $bin = dirname(__DIR__, 2) . '/bin/saas-next.php';
        $this->assertFileExists($bin);
        $out = [];
        $code = 0;
        exec('php ' . escapeshellarg($bin) . ' help 2>&1', $out, $code);
        $this->assertSame(0, $code);
        $this->assertStringContainsString('doctor', implode("\n", $out));
        $this->assertStringContainsString('scaffold-domain', implode("\n", $out));
    }

    public function testDoctorRunsAgainstTempHost(): void
    {
        $bin = dirname(__DIR__, 2) . '/bin/saas-next.php';
        $tmp = sys_get_temp_dir() . '/saas-next-host-' . bin2hex(random_bytes(4));
        mkdir($tmp . '/src/Etc', 0777, true);
        mkdir($tmp . '/public', 0777, true);
        file_put_contents($tmp . '/composer.json', json_encode([
            'require' => [
                'bitshost/upmvc' => '^2.5',
                'bitshost/upmvc-saas-pack' => '^1.1',
            ],
        ], JSON_PRETTY_PRINT));
        file_put_contents($tmp . '/src/Etc/packages.php', "<?php\nreturn [\\BitsHost\\UpmvcSaas\\SaasServiceProvider::class];\n");
        file_put_contents($tmp . '/src/Etc/.env', "JWT_SECRET=test\nDB_HOST=localhost\nDB_NAME=x\nDOMAIN_NAME=http://localhost\nSITE_PATH=\nAPP_KEY=k\nAPP_ENV=testing\nDB_USER=root\nDB_PASS=\n");

        $out = [];
        $code = 0;
        exec(
            'php ' . escapeshellarg($bin) . ' doctor --root=' . escapeshellarg($tmp) . ' 2>&1',
            $out,
            $code
        );
        $text = implode("\n", $out);
        $this->assertStringContainsString('saas:', $text);
        // cleanup
        $this->deleteTree($tmp);
    }

    private function deleteTree(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->deleteTree($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }
}
