<?php
/**
 * Static Analyzer Unit Tests
 */

require_once dirname(__DIR__) . '/src/autoload.php';

use FrankenLeakHunter\Analyzer\StaticAnalyzer;
use FrankenLeakHunter\Analyzer\Hazard;

class AnalyzerTest
{
    public function testDetectsLeakyCartServiceStaticProperty()
    {
        $analyzer = new StaticAnalyzer();
        $file = __DIR__ . '/Fixtures/LeakyCartService.php';
        $result = $analyzer->analyze($file);

        assert($result->hasHazards(), "Expected hazards in LeakyCartService");
        assert($result->getCriticalCount() >= 1, "Expected at least 1 critical hazard");

        $found = false;
        foreach ($result->hazards as $h) {
            if (strpos($h->symbol, 'LeakyCartService::$items') !== false) {
                $found = true;
                assert($h->rule === 'static-property');
                break;
            }
        }
        assert($found, "Did not find LeakyCartService::\$items hazard");
        echo "  ✓ testDetectsLeakyCartServiceStaticProperty passed\n";
    }

    public function testCleanServiceWithResetMethodHasNoHazards()
    {
        $analyzer = new StaticAnalyzer();
        $file = __DIR__ . '/Fixtures/CleanService.php';
        $result = $analyzer->analyze($file);

        assert(!$result->hasHazards(), "CleanService with reset() method must have no hazards");
        echo "  ✓ testCleanServiceWithResetMethodHasNoHazards passed\n";
    }

    public function testDetectsStaticVariableMemoization()
    {
        $analyzer = new StaticAnalyzer();
        $file = __DIR__ . '/Fixtures/StaticFunctionMemo.php';
        $result = $analyzer->analyze($file);

        assert($result->hasHazards(), "Expected hazard in StaticFunctionMemo");
        $found = false;
        foreach ($result->hazards as $h) {
            if ($h->rule === 'static-variable' && strpos($h->symbol, '$taxRates') !== false) {
                $found = true;
                break;
            }
        }
        assert($found, "Expected static-variable hazard for \$taxRates");
        echo "  ✓ testDetectsStaticVariableMemoization passed\n";
    }

    public function testDetectsGlobalMutation()
    {
        $analyzer = new StaticAnalyzer();
        $file = __DIR__ . '/Fixtures/GlobalPolluter.php';
        $result = $analyzer->analyze($file);

        assert($result->hasHazards(), "Expected hazard in GlobalPolluter");
        $found = false;
        foreach ($result->hazards as $h) {
            if ($h->rule === 'global-mutation') {
                $found = true;
                break;
            }
        }
        assert($found, "Expected global-mutation hazard");
        echo "  ✓ testDetectsGlobalMutation passed\n";
    }

    public function testDetectsLeakySingleton()
    {
        $analyzer = new StaticAnalyzer();
        $file = __DIR__ . '/Fixtures/LeakySingleton.php';
        $result = $analyzer->analyze($file);

        assert($result->hasHazards(), "Expected hazard in LeakySingleton");
        $found = false;
        foreach ($result->hazards as $h) {
            if ($h->rule === 'singleton-state' && strpos($h->symbol, 'LeakySingleton::getInstance') !== false) {
                $found = true;
                break;
            }
        }
        assert($found, "Expected singleton-state hazard for LeakySingleton");
        echo "  ✓ testDetectsLeakySingleton passed\n";
    }
}
