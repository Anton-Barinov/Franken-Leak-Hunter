<?php
/**
 * Simulation Runner Unit Tests
 */

require_once dirname(__DIR__) . '/src/autoload.php';
require_once __DIR__ . '/Fixtures/LeakyCartService.php';

use FrankenLeakHunter\Runtime\Hunter;
use Tests\Fixtures\LeakyCartService;

class SimulationTest
{
    public function testSimulateDetectsMonotonicCreep()
    {
        LeakyCartService::$items = [];

        $results = Hunter::simulate(function () {
            $cart = new LeakyCartService();
            $cart->addItem(rand(1, 1000), 'Widget', 19.99);
        }, 5);

        assert($results['iterations'] === 5, "Expected 5 iterations");
        assert(!empty($results['static_pollutions']), "Expected static pollutions in simulation");
        assert(count($results['reports']) === 5, "Expected 5 reports");
        echo "  ✓ testSimulateDetectsMonotonicCreep passed (5 iterations analyzed)\n";
    }
}
