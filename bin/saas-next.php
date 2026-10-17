#!/usr/bin/env php
<?php
/**
 * saas-next — compose-aware SaaS host helper (runs from a host app).
 *
 * Discovers bitshost packs via each package root saas-kit.json so future packs
 * (payments, etc.) appear without rewriting this CLI.
 *
 * Usage (from host project root):
 *   php vendor/bitshost/upmvc-saas-pack/bin/saas-next.php
 *   php vendor/bitshost/upmvc-saas-pack/bin/saas-next.php doctor
 *   php vendor/bitshost/upmvc-saas-pack/bin/saas-next.php init
 *   php vendor/bitshost/upmvc-saas-pack/bin/saas-next.php agent --goal "Add tenant bookings API"
 *   php vendor/bitshost/upmvc-saas-pack/bin/saas-next.php scaffold-domain Bookings
 *
 * Options:
 *   --root=/path/to/host
 *   --stdout          (agent: print prompt only)
 *   --yes             (init: write stubs without prompting)
 */

declare(strict_types=1);

final class SaasNext
{
    private string $hostRoot;
    private string $packRoot;

    public function __construct(string $hostRoot, string $packRoot)
    {
        $this->hostRoot = rtrim($hostRoot, '/\\');
        $this->packRoot = rtrim($packRoot, '/\\');
    }

    public function run(array $argv): int
    {
        $args = $this->parseArgv($argv);
        $cmd = $args['command'];

        echo "\n  upMVC SaaS Next — compose helper\n";
        echo "  Host: {$this->hostRoot}\n\n";

        return match ($cmd) {
            'doctor' => $this->cmdDoctor(),
            'init' => $this->cmdInit($args['yes']),
            'agent' => $this->cmdAgent($args['goal'], $args['stdout']),
            'scaffold-domain' => $this->cmdScaffoldDomain($args['domain']),
            'packs' => $this->cmdPacks(),
            'help', '' => $this->cmdHelp(),
            default => $this->unknown($cmd),
        };
    }

    private function cmdHelp(): int
    {
        echo <<<TXT
  Commands:
    doctor              Check host wiring (composer, packages.php, .env, packs)
    packs               List discovered saas-kit.json packs (installed + known)
    init [--yes]        Checklist + optional stubs (packages.php, .env keys)
    agent [--goal "…"]  Write compose agent prompt for Cursor / Claude
    scaffold-domain X   Empty Api/Modules/X tree (no domain business logic)

  Examples:
    php vendor/bitshost/upmvc-saas-pack/bin/saas-next.php doctor
    php vendor/bitshost/upmvc-saas-pack/bin/saas-next.php init --yes
    php vendor/bitshost/upmvc-saas-pack/bin/saas-next.php agent --goal "Tenant-scoped invoices API"
    php vendor/bitshost/upmvc-saas-pack/bin/saas-next.php scaffold-domain Invoices

  Teach to fish: this tool sets the floor and empty structure — not product code.

TXT;
        return 0;
    }

    private function unknown(string $cmd): int
    {
        echo "  Unknown command: {$cmd}\n";
        $this->cmdHelp();
        return 1;
    }

    private function cmdPacks(): int
    {
        $discovered = $this->discoverKits();
        $required = $this->composerRequires();

        echo "  Discovered saas-kit manifests:\n\n";
        if ($discovered === []) {
            echo "  (none under vendor/*/saas-kit.json)\n";
            echo "  Tip: composer require bitshost/upmvc-saas-pack\n\n";
            return 0;
        }

        foreach ($discovered as $kit) {
            $pkg = (string) ($kit['composer'] ?? '');
            $inComposer = isset($required[$pkg]);
            $opt = !empty($kit['optional']) ? 'optional' : 'required';
            $flag = $inComposer ? '[in composer]' : '[manifest only]';
            echo "  - {$kit['id']}: {$kit['name']} ({$opt}) {$flag}\n";
            echo "      {$pkg}\n";
            if (!empty($kit['provider'])) {
                echo "      provider: {$kit['provider']}\n";
            }
        }
        echo "\n";
        return 0;
    }

    private function cmdDoctor(): int
    {
        $ok = true;
        $kits = $this->discoverKits();
        $required = $this->composerRequires();
        $providers = $this->readPackagesPhp();

        echo "  == Kernel / host ==\n";
        $ok = $this->check(is_file($this->hostRoot . '/composer.json'), 'composer.json present') && $ok;
        $ok = $this->check(is_dir($this->hostRoot . '/public'), 'public/ present') && $ok;
        $ok = $this->check(is_dir($this->hostRoot . '/src/Etc'), 'src/Etc/ present') && $ok;
        $ok = $this->check(
            isset($required['bitshost/upmvc']),
            'composer requires bitshost/upmvc'
        ) && $ok;

        echo "\n  == Packs (discovered via saas-kit.json) ==\n";
        if ($kits === []) {
            echo "  ! No saas-kit.json found under vendor/\n";
            $ok = false;
        }
        foreach ($kits as $kit) {
            $pkg = (string) ($kit['composer'] ?? '');
            $inReq = isset($required[$pkg]);
            $prov = (string) ($kit['provider'] ?? '');
            $provOk = $prov === '' || in_array($prov, $providers, true);
            $label = $kit['id'] ?? $pkg;
            $ok = $this->check($inReq, "{$label}: in composer.json") && $ok;
            if ($prov !== '') {
                $ok = $this->check($provOk, "{$label}: provider in packages.php") && $ok;
            }
        }

        // Known optional packs not installed — inform only
        $knownOptional = [
            'bitshost/upmvc-payments-pack' => 'payments (optional)',
        ];
        foreach ($knownOptional as $pkg => $label) {
            if (!isset($required[$pkg])) {
                $installed = false;
                foreach ($kits as $kit) {
                    if (($kit['composer'] ?? '') === $pkg) {
                        $installed = true;
                        break;
                    }
                }
                if (!$installed) {
                    echo "  · {$label}: not installed (ok)\n";
                }
            }
        }

        echo "\n  == Env keys (from kit manifests) ==\n";
        $env = $this->readEnvKeys();
        $seen = [];
        foreach ($kits as $kit) {
            if (!empty($kit['optional']) && !isset($required[(string) ($kit['composer'] ?? '')])) {
                continue;
            }
            foreach ($kit['env_keys'] ?? [] as $key) {
                $key = (string) $key;
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $has = array_key_exists($key, $env) && trim((string) $env[$key]) !== '';
                // Soft warn — missing JWT is hard for SaaS APIs
                $hard = in_array($key, ['JWT_SECRET', 'DB_HOST', 'DB_NAME'], true);
                if ($hard) {
                    $ok = $this->check($has, ".env {$key}") && $ok;
                } else {
                    $this->check($has, ".env {$key}", false);
                }
            }
        }

        echo "\n  == packages.php providers ==\n";
        if ($providers === []) {
            echo "  ! src/Etc/packages.php missing or empty\n";
            $ok = false;
        } else {
            foreach ($providers as $p) {
                echo "  ✓ {$p}\n";
            }
        }

        echo "\n";
        echo $ok ? "  Doctor: OK (floor looks wired)\n\n" : "  Doctor: issues found — run `init` or fix above\n\n";
        return $ok ? 0 : 2;
    }

    private function cmdInit(bool $yes): int
    {
        echo "  Init teaches the floor — it does not install product domain code.\n\n";

        $this->cmdDoctor();

        $packagesPath = $this->hostRoot . '/src/Etc/packages.php';
        if (!is_file($packagesPath)) {
            $write = $yes || $this->confirm('Create src/Etc/packages.php with SaasServiceProvider?');
            if ($write) {
                $this->ensureDir(dirname($packagesPath));
                $stub = <<<'PHP'
<?php

return [
    \BitsHost\UpmvcSaas\SaasServiceProvider::class,
    // \BitsHost\UpmvcPayments\PaymentsServiceProvider::class,
];

PHP;
                file_put_contents($packagesPath, $stub);
                echo "  ✓ Wrote {$packagesPath}\n";
            }
        } else {
            echo "  · packages.php already exists (left untouched)\n";
        }

        $envPath = $this->hostRoot . '/src/Etc/.env';
        $examplePath = $this->hostRoot . '/src/Etc/.env.example';
        $kits = $this->discoverKits();
        $keys = [];
        foreach ($kits as $kit) {
            foreach ($kit['env_keys'] ?? [] as $k) {
                $keys[(string) $k] = true;
            }
        }
        // Always include core saas keys from this pack's kit even if vendor not linked yet
        $selfKit = $this->packRoot . '/saas-kit.json';
        if (is_file($selfKit)) {
            $decoded = json_decode((string) file_get_contents($selfKit), true);
            if (is_array($decoded)) {
                foreach ($decoded['env_keys'] ?? [] as $k) {
                    $keys[(string) $k] = true;
                }
            }
        }

        $target = is_file($envPath) ? $envPath : $examplePath;
        if ($keys !== []) {
            $writeEnv = $yes || $this->confirm(
                is_file($target)
                    ? "Append missing kit env keys to " . basename($target) . "?"
                    : "Create src/Etc/.env.example with kit env keys?"
            );
            if ($writeEnv) {
                $this->ensureDir(dirname($examplePath));
                if (!is_file($target)) {
                    $target = $examplePath;
                    file_put_contents($target, "# upMVC SaaS host — fill values\n");
                }
                $existing = $this->readEnvKeys($target);
                $append = '';
                foreach (array_keys($keys) as $key) {
                    if (!array_key_exists($key, $existing)) {
                        $append .= "{$key}=\n";
                    }
                }
                if ($append !== '') {
                    file_put_contents($target, rtrim((string) file_get_contents($target)) . "\n\n# --- saas-next kits ---\n" . $append);
                    echo "  ✓ Updated {$target} with missing keys\n";
                } else {
                    echo "  · Env keys already present\n";
                }
            }
        }

        echo "\n  Next:\n";
        echo "    1. composer require bitshost/upmvc bitshost/upmvc-saas-pack\n";
        echo "    2. optional: composer require bitshost/upmvc-payments-pack\n";
        echo "    3. Fill src/Etc/.env (JWT_SECRET, DB_*, DOMAIN_NAME, SITE_PATH)\n";
        echo "    4. php vendor/bitshost/upmvc/src/Tools/migrate.php\n";
        echo "    5. php vendor/bitshost/upmvc-saas-pack/bin/saas-next.php doctor\n";
        echo "    6. php vendor/bitshost/upmvc-saas-pack/bin/saas-next.php scaffold-domain YourDomain\n";
        echo "    7. Read upMVC docs/BUILD_SAAS_APP.md\n\n";
        return 0;
    }

    private function cmdAgent(?string $goal, bool $stdout): int
    {
        $kits = $this->discoverKits();
        $required = $this->composerRequires();
        $providers = $this->readPackagesPhp();

        if ($goal === null || trim($goal) === '') {
            if ($stdout) {
                $goal = '(no goal provided)';
            } else {
                $goal = $this->ask('What do you want to build?');
            }
        }

        $hints = [];
        foreach ($kits as $kit) {
            $pkg = (string) ($kit['composer'] ?? '');
            if (!empty($kit['optional']) && !isset($required[$pkg])) {
                continue;
            }
            foreach ($kit['agent_hints'] ?? [] as $h) {
                $hints[] = '- ' . $h;
            }
        }
        $hints = array_values(array_unique($hints));

        $packLines = [];
        foreach ($kits as $kit) {
            $pkg = (string) ($kit['composer'] ?? '');
            $state = isset($required[$pkg]) ? 'installed' : 'manifest-present';
            $packLines[] = sprintf(
                '- %s (`%s`) — %s%s',
                $kit['id'] ?? '?',
                $pkg,
                $state,
                !empty($kit['optional']) ? ', optional' : ''
            );
        }

        $providerLines = $providers === []
            ? '- (none — register SaasServiceProvider in src/Etc/packages.php)'
            : implode("\n", array_map(static fn(string $p): string => "- `{$p}`", $providers));

        $prompt = <<<MD
# upMVC SaaS compose — agent session

You are helping on a **composed** host app (libraries), not a kernel fork.

## Goal
{$goal}

## Stack
- Kernel: `bitshost/upmvc` (boot, router, modules, JWT middleware)
- SaaS floor: `bitshost/upmvc-saas-pack` (tenancy + Harvest)
- Optional packs: discovered via `saas-kit.json` (e.g. payments)
- Host owns **domain modules only** — teach to fish; do not invent or paste unrelated product domain code

## Discovered packs
MD;
        $prompt .= "\n" . ($packLines === [] ? "- (run composer require / doctor)\n" : implode("\n", $packLines) . "\n");
        $prompt .= "\n## packages.php providers\n{$providerLines}\n";
        $prompt .= "\n## Must / never (from pack kits)\n";
        $prompt .= $hints === [] ? "- Scope tenant queries with tenant_id\n" : implode("\n", $hints) . "\n";
        $prompt .= <<<MD

## Module contract
- New APIs: `src/Modules/Api/Modules/{Domain}/` with `Controller.php`, `Model.php`, `Routes/Routes.php`
- Controllers often extend `BitsHost\\UpmvcSaas\\Http\\SaasApiController`
- Routes: `addRoute(..., ['cors', 'jwt'], ['GET'|'POST'|...])`
- Human guide: upMVC `docs/BUILD_SAAS_APP.md`

## Behavior
1. Output a short **plan** before multi-file edits
2. Prefer pack libraries over copying MailGateway/Totp/SecretBox into the app
3. Keep notify **types** in the host provider — pack registry stays empty of domain keys
4. Do not commit secrets or vault/

MD;

        if ($stdout) {
            echo $prompt;
            return 0;
        }

        $outDir = $this->hostRoot . '/docs/agent/generated';
        $this->ensureDir($outDir);
        $outFile = $outDir . '/saas-last-prompt.md';
        file_put_contents($outFile, $prompt);
        file_put_contents($outDir . '/saas-last-session.json', json_encode([
            'goal' => $goal,
            'packs' => $kits,
            'providers' => $providers,
            'generated_at' => date('c'),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        echo "  ✓ Wrote {$outFile}\n";
        echo "  Paste into Cursor / Claude and continue.\n\n";
        return 0;
    }

    private function cmdScaffoldDomain(?string $domain): int
    {
        if ($domain === null || !preg_match('/^[A-Z][A-Za-z0-9]+$/', $domain)) {
            echo "  Usage: scaffold-domain DomainName  (PascalCase, e.g. Bookings)\n\n";
            return 1;
        }

        $base = $this->hostRoot . '/src/Modules/Api/Modules/' . $domain;
        if (is_dir($base)) {
            echo "  ! Already exists: {$base}\n\n";
            return 1;
        }

        $this->ensureDir($base . '/Routes');
        $ns = 'App\\Modules\\Api\\Modules\\' . $domain;

        $controller = <<<PHP
<?php

namespace {$ns};

use BitsHost\\UpmvcSaas\\Http\\SaasApiController;

/**
 * Thin domain API — fill in; always scope by tenant_id from JWT.
 */
class Controller extends SaasApiController
{
    public function index(): void
    {
        \$tenantId = (int) (\$this->user['tenant_id'] ?? 0);
        if (\$tenantId < 1) {
            \$this->error('Tenant context required', 403);
            return;
        }
        // TODO: query WHERE tenant_id = :tid
        \$this->success(['items' => [], 'tenant_id' => \$tenantId]);
    }
}

PHP;

        $model = <<<PHP
<?php

namespace {$ns};

use App\\Common\\Bmvc\\BaseModel;

class Model extends BaseModel
{
    // TODO: prepared statements scoped with tenant_id
}

PHP;

        $routes = <<<PHP
<?php

use {$ns}\\Controller;

/** @var \\App\\Etc\\Router \$router */
\$router->addRoute('/api/{$this->kebab($domain)}', Controller::class, 'index', ['cors', 'jwt'], ['GET']);

PHP;

        file_put_contents($base . '/Controller.php', $controller);
        file_put_contents($base . '/Model.php', $model);
        file_put_contents($base . '/Routes/Routes.php', $routes);

        echo "  ✓ Scaffolded {$base}\n";
        echo "  Empty fish-school skeleton only — add your domain logic next.\n\n";
        return 0;
    }

    // ----- discovery -----

    /** @return list<array<string,mixed>> */
    private function discoverKits(): array
    {
        $kits = [];
        $vendor = $this->hostRoot . '/vendor';
        if (is_dir($vendor)) {
            $paths = glob($vendor . '/*/*/saas-kit.json') ?: [];
            $paths = array_merge($paths, glob($vendor . '/*/saas-kit.json') ?: []);
            foreach ($paths as $path) {
                $data = json_decode((string) file_get_contents($path), true);
                if (is_array($data) && isset($data['id'], $data['composer'])) {
                    $data['_path'] = $path;
                    $kits[$data['id']] = $data;
                }
            }
        }

        // Always include this pack's kit (when running from source / path repo)
        $self = $this->packRoot . '/saas-kit.json';
        if (is_file($self)) {
            $data = json_decode((string) file_get_contents($self), true);
            if (is_array($data) && isset($data['id'])) {
                $data['_path'] = $self;
                $kits[$data['id']] = $data;
            }
        }

        ksort($kits);
        return array_values($kits);
    }

    /** @return array<string,string> */
    private function composerRequires(): array
    {
        $file = $this->hostRoot . '/composer.json';
        if (!is_file($file)) {
            return [];
        }
        $json = json_decode((string) file_get_contents($file), true);
        if (!is_array($json)) {
            return [];
        }
        $out = [];
        foreach (['require', 'require-dev'] as $section) {
            foreach ($json[$section] ?? [] as $name => $ver) {
                $out[(string) $name] = (string) $ver;
            }
        }
        return $out;
    }

    /** @return list<string> */
    private function readPackagesPhp(): array
    {
        $file = $this->hostRoot . '/src/Etc/packages.php';
        if (!is_file($file)) {
            return [];
        }
        $code = (string) file_get_contents($file);
        $providers = [];
        if (preg_match_all('/([\\\\A-Za-z0-9_]+)::class/', $code, $m)) {
            foreach ($m[1] as $raw) {
                $providers[] = ltrim(str_replace('\\\\', '\\', $raw), '\\');
            }
        }
        return array_values(array_unique($providers));
    }

    /** @return array<string,string> */
    private function readEnvKeys(?string $path = null): array
    {
        $path ??= $this->hostRoot . '/src/Etc/.env';
        if (!is_file($path)) {
            $alt = $this->hostRoot . '/src/Etc/.env.example';
            if (is_file($alt)) {
                $path = $alt;
            } else {
                return [];
            }
        }
        $out = [];
        foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }
            [$k, $v] = explode('=', $line, 2);
            $out[trim($k)] = trim($v);
        }
        return $out;
    }

    // ----- argv / io -----

    /**
     * @return array{command:string,goal:?string,domain:?string,stdout:bool,yes:bool}
     */
    private function parseArgv(array $argv): array
    {
        $command = 'help';
        $goal = null;
        $domain = null;
        $stdout = in_array('--stdout', $argv, true);
        $yes = in_array('--yes', $argv, true);

        $positional = [];
        foreach ($argv as $i => $arg) {
            if ($i === 0) {
                continue;
            }
            if (str_starts_with($arg, '--root=')) {
                continue;
            }
            if ($arg === '--goal' && isset($argv[$i + 1])) {
                $goal = $argv[$i + 1];
                continue;
            }
            if (str_starts_with($arg, '--goal=')) {
                $goal = substr($arg, 7);
                continue;
            }
            if (str_starts_with($arg, '--')) {
                continue;
            }
            // skip value after --goal
            if ($i > 0 && ($argv[$i - 1] ?? '') === '--goal') {
                continue;
            }
            $positional[] = $arg;
        }

        if ($positional !== []) {
            $command = array_shift($positional);
        }
        if ($command === 'scaffold-domain' && $positional !== []) {
            $domain = $positional[0];
        }

        return compact('command', 'goal', 'domain', 'stdout', 'yes');
    }

    private function check(bool $cond, string $label, bool $hard = true): bool
    {
        if ($cond) {
            echo "  ✓ {$label}\n";
            return true;
        }
        echo ($hard ? "  ✗" : "  ·") . " {$label}\n";
        return !$hard;
    }

    private function confirm(string $q): bool
    {
        echo "  {$q} [y/N] ";
        $line = fgets(STDIN);
        return $line !== false && preg_match('/^y(es)?$/i', trim($line));
    }

    private function ask(string $q): string
    {
        echo "  {$q}\n  > ";
        $line = fgets(STDIN);
        return $line === false ? '' : trim($line);
    }

    private function ensureDir(string $dir): void
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
    }

    private function kebab(string $pascal): string
    {
        $s = preg_replace('/([a-z])([A-Z])/', '$1-$2', $pascal) ?? $pascal;
        return strtolower($s);
    }
}

// ----- bootstrap -----
$argv = $_SERVER['argv'] ?? [];
$hostRoot = getcwd() ?: '.';
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--root=')) {
        $hostRoot = substr($arg, 7);
    }
}
$hostRoot = realpath($hostRoot) ?: $hostRoot;
$packRoot = realpath(dirname(__DIR__)) ?: dirname(__DIR__);

exit((new SaasNext($hostRoot, $packRoot))->run($argv));
