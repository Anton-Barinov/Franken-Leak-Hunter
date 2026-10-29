<?php
/**
 * Master Test Runner for Franken-Leak-Hunter
 */

require_once __DIR__ . '/AnalyzerTest.php';
require_once __DIR__ . '/RuntimeDifferTest.php';
require_once __DIR__ . '/SimulationTest.php';
require_once __DIR__ . '/ConsoleTest.php';
require_once __DIR__ . '/IntegrationTest.php';

echo "🚀 Running Franken-Leak-Hunter Test Suite...\n\n";

$suites = [
    'Static Analyzer Tests' => [
        [new AnalyzerTest(), 'testDetectsLeakyCartServiceStaticProperty'],
        [new AnalyzerTest(), 'testCleanServiceWithResetMethodHasNoHazards'],
        [new AnalyzerTest(), 'testDetectsStaticVariableMemoization'],
        [new AnalyzerTest(), 'testDetectsGlobalMutation'],
        [new AnalyzerTest(), 'testDetectsLeakySingleton'],
    ],
    'Runtime State Differ Tests' => [
        [new RuntimeDifferTest(), 'testDetectsLeakyCartServiceInRuntime'],
        [new RuntimeDifferTest(), 'testCleanServiceProducesCleanReport'],
        [new RuntimeDifferTest(), 'testDetectsGlobalVariablePollution'],
        [new RuntimeDifferTest(), 'testDetectsResourceLeak'],
    ],
    'Simulation & Profiler Tests' => [
        [new SimulationTest(), 'testSimulateDetectsMonotonicCreep'],
    ],
    'Framework Integration Tests' => [
        [new IntegrationTest(), 'testFrankenPhpWorkerRunExecutesHandler'],
        [new IntegrationTest(), 'testPsr15MiddlewareAttachesDiagnosticsHeader'],
        [new IntegrationTest(), 'testThrowOnPollutionOptionThrowsException'],
    ],
    'Console CLI Tests' => [
        [new ConsoleTest(), 'testAnalyzeCommandExitsWithHazardsOnFixtures'],
        [new ConsoleTest(), 'testAnalyzeCommandJsonFormat'],
        [new ConsoleTest(), 'testHelpCommandExitsZero'],
    ],
];

$passed = 0;
$total = 0;

foreach ($suites as $name => $tests) {
    echo "=== {$name} ===\n";
    foreach ($tests as $case) {
        $total++;
        try {
            call_user_func($case);
            $passed++;
        } catch (\Throwable $e) {
            echo "  ✗ FAILED: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . "\n";
        }
    }
    echo "\n";
}

echo "=== Chaos & Fault-Injection Tests ===\n";
passthru(PHP_BINARY . ' ' . escapeshellarg(__DIR__ . '/ChaosTest.php'), $chaosExit);
echo "\n";

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
if ($passed === $total && $chaosExit === 0) {
    echo "🎉 ALL TESTS PASSED ({$passed}/{$total} Unit Tests + 5 Chaos Tests)!\n";
    exit(0);
} else {
    echo "❌ SOME TESTS FAILED ({$passed}/{$total})\n";
    exit(1);
}
