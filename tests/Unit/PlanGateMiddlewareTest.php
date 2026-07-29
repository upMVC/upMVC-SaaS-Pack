<?php

declare(strict_types=1);

namespace BitsHost\UpmvcSaas\Tests\Unit;

use BitsHost\UpmvcSaas\Middleware\PlanGateMiddleware;
use PHPUnit\Framework\TestCase;

/**
 * Only the pass paths are covered here.
 *
 * Every failure path in PlanGateMiddleware ends in exit(), which would take
 * PHPUnit down with it. That is worth knowing rather than working around:
 * middleware that exits cannot be composed or tested for its refusals. If the
 * abort path is ever changed to throw, the denial cases belong here too.
 */
final class PlanGateMiddlewareTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($GLOBALS['current_tenant']);
    }

    public function testPassesWhenFeatureIsEnabledInAnArray(): void
    {
        $GLOBALS['current_tenant'] = [
            'id'       => 1,
            'features' => ['invoices' => true, 'efactura' => true],
        ];

        $this->assertTrue((new PlanGateMiddleware('efactura'))('/api/x', 'GET'));
    }

    public function testPassesWhenFeaturesArriveAsAJsonString(): void
    {
        // features is a JSON column, so it reaches the middleware as a string
        // unless something decoded it first. Both shapes must work.
        $GLOBALS['current_tenant'] = [
            'id'       => 1,
            'features' => '{"invoices":true,"efactura":true,"advanced_reports":false}',
        ];

        $this->assertTrue((new PlanGateMiddleware('invoices'))('/api/x', 'GET'));
    }

    public function testTruthyNonBooleanFeatureValueAlsoPasses(): void
    {
        $GLOBALS['current_tenant'] = [
            'id'       => 1,
            'features' => ['seats' => 5],
        ];

        $this->assertTrue((new PlanGateMiddleware('seats'))('/api/x', 'GET'));
    }
}
