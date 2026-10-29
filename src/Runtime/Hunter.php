<?php
/**
 * Franken-Leak-Hunter
 * Runtime Leak & State Pollution Coordinator
 *
 * @author Anton Barinov <https://github.com/Anton-Barinov>
 * @license MIT
 */

namespace FrankenLeakHunter\Runtime;

class Hunter
{
    private static ?Snapshot $activeStartSnapshot = null;
    private static ?StateDiffer $differ = null;

    private static array $options = [
        'throwOnPollution'    => false,
        'autoDumpToStderr'    => false,
        'monitoredNamespaces' => [],
        'logger'              => null,
    ];

    /**
     * Configure Hunter options
     */
    public static function configure(array $options): void
    {
        self::$options = array_merge(self::$options, $options);
    }

    /**
     * Reset hunter state between runs
     */
    public static function reset(): void
    {
        self::$activeStartSnapshot = null;
        self::$differ = null;
    }

    /**
     * Begin recording request state
     */
    public static function start(): void
    {
        self::$activeStartSnapshot = Snapshot::capture(self::$options['monitoredNamespaces']);
    }

    /**
     * Stop recording, compute state delta, and return DiffReport
     *
     * @return DiffReport
     * @throws StatePollutionException
     */
    public static function stop(): DiffReport
    {
        if (self::$activeStartSnapshot === null) {
            throw new \LogicException("Hunter::start() must be called before Hunter::stop().");
        }

        $after = Snapshot::capture(self::$options['monitoredNamespaces']);
        $before = self::$activeStartSnapshot;
        self::$activeStartSnapshot = null;

        if (self::$differ === null) {
            self::$differ = new StateDiffer();
        }

        $report = self::$differ->diff($before, $after);

        if (!$report->isClean()) {
            if (self::$options['autoDumpToStderr']) {
                fwrite(STDERR, $report->renderTerminal() . "\n");
            }
            if (is_callable(self::$options['logger'])) {
                call_user_func(self::$options['logger'], $report);
            }
            if (self::$options['throwOnPollution']) {
                throw new StatePollutionException($report);
            }
        }

        return $report;
    }

    /**
     * Wrap any request callable or worker iteration
     *
     * @param callable $fn
     * @param DiffReport|null $reportRef
     * @return mixed Result of $fn()
     */
    public static function watch(callable $fn, &$reportRef = null)
    {
        self::start();
        try {
            $result = $fn();
            $reportRef = self::stop();
            return $result;
        } catch (\Throwable $e) {
            // Even if exception thrown, finish diffing to avoid dangling state
            if (self::$activeStartSnapshot !== null) {
                try {
                    $reportRef = self::stop();
                } catch (\Throwable $diffEx) {
                    // ignore secondary diff error
                }
            }
            throw $e;
        }
    }

    /**
     * Simulate multi-request worker execution loop across N iterations
     *
     * @param callable $workerJob Callback executed once per simulated request
     * @param int $iterations Number of consecutive requests to simulate
     * @return array Multi-iteration analysis report
     */
    public static function simulate(callable $workerJob, int $iterations = 5): array
    {
        $iterationReports = [];
        $memoryTrend = [];

        for ($i = 1; $i <= $iterations; $i++) {
            $report = null;
            self::watch($workerJob, $report);
            $iterationReports[] = $report;
            $memoryTrend[] = $report->memoryDeltaBytes;
        }

        $totalGrowth = array_sum($memoryTrend);
        $averageGrowthPerReq = $totalGrowth / $iterations;
        $monotonicallyGrowing = true;

        for ($i = 1; $i < count($memoryTrend); $i++) {
            if ($memoryTrend[$i] < 0) {
                $monotonicallyGrowing = false;
                break;
            }
        }

        $allStaticPollutions = [];
        foreach ($iterationReports as $idx => $r) {
            if (!empty($r->staticPollutions)) {
                $allStaticPollutions[$idx + 1] = $r->staticPollutions;
            }
        }

        return [
            'iterations'             => $iterations,
            'total_memory_growth'    => $totalGrowth,
            'avg_growth_per_request' => round($averageGrowthPerReq, 2),
            'monotonic_leak_suspect' => $monotonicallyGrowing && ($totalGrowth > 0),
            'reports'                => $iterationReports,
            'static_pollutions'      => $allStaticPollutions,
        ];
    }
}
