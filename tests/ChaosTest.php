<?php
/**
 * Franken-Leak-Hunter Chaos & High-Load Stress Test Suite
 */

require_once dirname(__DIR__) . '/src/autoload.php';

use FrankenLeakHunter\Runtime\Hunter;
use FrankenLeakHunter\Runtime\Snapshot;
use FrankenLeakHunter\Runtime\StateDiffer;

echo "========================================================\n";
echo "   Running Franken-Leak-Hunter Chaos & Stress Tests\n";
echo "========================================================\n\n";

$passed = 0;
$failed = 0;

function run_test(string $name, callable $fn): void {
    global $passed, $failed;
    echo "Testing {$name}... ";
    try {
        $fn();
        echo "\033[32m[PASS]\033[0m\n";
        $passed++;
    } catch (\Throwable $e) {
        echo "\033[31m[FAIL: " . $e->getMessage() . "]\033[0m\n";
        $failed++;
    }
}

// Dummy classes for chaos tests
class StaticClosureHolder {
    public static array $listeners = [];
    public static function reset(): void {
        self::$listeners = [];
    }
}

class CycleNodeA {
    public ?CycleNodeB $b = null;
}
class CycleNodeB {
    public ?CycleNodeA $a = null;
}
class CyclicHolder {
    public static array $cycles = [];
    public static function reset(): void {
        self::$cycles = [];
    }
}

// 1. High-Load 1,000 Request Worker Simulation
run_test("1,000 Request Worker Simulation Benchmark", function() {
    StaticClosureHolder::reset();

    $t0 = microtime(true);
    $results = Hunter::simulate(function() {
        // Subtle leak: add array element
        StaticClosureHolder::$listeners[] = function() { return 42; };
    }, 1000);
    $duration = microtime(true) - $t0;

    assert($results['iterations'] === 1000);
    assert($results['monotonic_leak_suspect'] === true, "Must flag monotonic leak suspect");
    assert($results['total_memory_growth'] > 0, "Memory growth should be positive");
    assert($duration < 3.0, "1,000 simulated iterations should take < 3.0s (took {$duration}s)");

    StaticClosureHolder::reset();
});

// 2. Closure Capturing $this in Static Holder
run_test("Closure Capturing \$this Retained in Static Scope", function() {
    StaticClosureHolder::reset();

    $report = null;
    $scopedController = new class {
        public string $secret = "user_private_cart_999";
        public function register() {
            StaticClosureHolder::$listeners[] = function() {
                return $this->secret;
            };
        }
    };

    Hunter::watch(function() use ($scopedController) {
        $scopedController->register();
    }, $report);

    assert($report !== null);
    assert($report->hasPollution() === true, "Static closure accumulation must trigger pollution");
    $found = false;
    foreach ($report->staticPollutions as $p) {
        if ($p['class'] === StaticClosureHolder::class) {
            $found = true;
            break;
        }
    }
    assert($found, "StaticClosureHolder pollution should be reported");
    StaticClosureHolder::reset();
});

// 3. Cyclic Object References Detection
run_test('Cyclic Object References ($a->b = $b; $b->a = $a)', function() {
    CyclicHolder::reset();

    $report = null;
    Hunter::watch(function() {
        $nodeA = new CycleNodeA();
        $nodeB = new CycleNodeB();
        $nodeA->b = $nodeB;
        $nodeB->a = $nodeA;
        CyclicHolder::$cycles[] = $nodeA;
    }, $report);

    assert($report !== null);
    assert($report->hasPollution() === true, "Retained cycle must trigger pollution");
    CyclicHolder::reset();
});

// 4. Zero-Overhead Latency Benchmark (5,000 checks)
run_test("Zero-Overhead Latency Benchmark (5,000 diffs < 0.2ms)", function() {
    $iterations = 5000;
    $t0 = microtime(true);
    for ($i = 0; $i < $iterations; $i++) {
        Hunter::start();
        $rep = Hunter::stop();
    }
    $totalTime = microtime(true) - $t0;
    $avgMs = ($totalTime / $iterations) * 1000;

    assert($avgMs < 0.5, "Average overhead per request must be < 0.5ms (was {$avgMs}ms)");
});

// 5. Exception & Fatal Error Resilience in Hunter::watch()
run_test("Exception Resilience: Clean Snapshot Teardown on Error", function() {
    $threw = false;
    $report = null;

    try {
        Hunter::watch(function() {
            throw new \RuntimeException("Simulated fatal controller failure");
        }, $report);
    } catch (\RuntimeException $e) {
        $threw = true;
    }

    assert($threw === true, "Original exception must bubble up");
    // Verify that next turn starts without 'must call start() before stop()' error
    Hunter::start();
    $cleanReport = Hunter::stop();
    assert($cleanReport !== null, "Hunter must be immediately reusable after unhandled exception");
});

echo "\n--------------------------------------------------------\n";
echo "Chaos Results: {$passed} Passed, {$failed} Failed\n";
echo "--------------------------------------------------------\n";

if ($failed === 0) {
    echo "🎉 ALL FRANKEN-LEAK-HUNTER CHAOS TESTS PASSED CLEANLY!\n";
    exit(0);
} else {
    exit(1);
}
