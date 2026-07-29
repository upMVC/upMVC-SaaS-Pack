<?php

declare(strict_types=1);

namespace BitsHost\UpmvcSaas\Tests\Unit;

use BitsHost\UpmvcSaas\Middleware\TenantMiddleware;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * resolveSlug() decides which tenant a request belongs to. It is pure — no
 * database — so it is worth pinning down precisely. __invoke() is not covered
 * here because it queries the tenants table and aborts with exit().
 */
final class TenantMiddlewareTest extends TestCase
{
    private ReflectionMethod $resolveSlug;

    protected function setUp(): void
    {
        $this->resolveSlug = new ReflectionMethod(TenantMiddleware::class, 'resolveSlug');
        $this->resolveSlug->setAccessible(true);
    }

    protected function tearDown(): void
    {
        unset($_SERVER['REQUEST_URI'], $_SERVER['HTTP_HOST'], $_ENV['BASE_URL']);
    }

    private function resolve(): string
    {
        return $this->resolveSlug->invoke(new TenantMiddleware());
    }

    public function testResolvesSlugFromAppPath(): void
    {
        $_SERVER['REQUEST_URI'] = '/app/bitsworld/admin';
        $this->assertSame('bitsworld', $this->resolve());
    }

    public function testResolvesSlugWhenPathHasNoTrailingSegment(): void
    {
        $_SERVER['REQUEST_URI'] = '/app/techforge';
        $this->assertSame('techforge', $this->resolve());
    }

    public function testSlugIsLowercased(): void
    {
        $_SERVER['REQUEST_URI'] = '/app/BitsWorld/admin';
        $this->assertSame('bitsworld', $this->resolve());
    }

    public function testQueryStringIsIgnored(): void
    {
        $_SERVER['REQUEST_URI'] = '/app/freeuser/admin?tab=billing';
        $this->assertSame('freeuser', $this->resolve());
    }

    public function testHyphensAndUnderscoresAreValidSlugCharacters(): void
    {
        $_SERVER['REQUEST_URI'] = '/app/dark-star_2/admin';
        $this->assertSame('dark-star_2', $this->resolve());
    }

    public function testFallsBackToSubdomainWhenPathIsNotAnAppRoute(): void
    {
        $_SERVER['REQUEST_URI'] = '/dashboard';
        $_SERVER['HTTP_HOST']   = 'bitsworld.example.com';
        $_ENV['BASE_URL']       = 'https://example.com';

        $this->assertSame('bitsworld', $this->resolve());
    }

    public function testAppPathWinsOverSubdomain(): void
    {
        $_SERVER['REQUEST_URI'] = '/app/techforge/admin';
        $_SERVER['HTTP_HOST']   = 'bitsworld.example.com';
        $_ENV['BASE_URL']       = 'https://example.com';

        $this->assertSame('techforge', $this->resolve());
    }
}
