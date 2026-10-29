<?php
/**
 * Franken-Leak-Hunter
 * Native FrankenPHP Worker Loop Integration
 *
 * @author Anton Barinov <https://github.com/Anton-Barinov>
 * @license MIT
 */

namespace FrankenLeakHunter\Integration;

use FrankenLeakHunter\Runtime\Hunter;
use FrankenLeakHunter\Runtime\DiffReport;

class FrankenPhpWorker
{
    /**
     * Wrap the standard FrankenPHP worker loop with automatic state diffing
     *
     * Example:
     * FrankenPhpWorker::run(function () use ($kernel) {
     *     $response = $kernel->handle($request);
     *     $response->send();
     *     $kernel->terminate($request, $response);
     * });
     *
     * @param callable $requestHandler Callback invoked on each incoming request
     * @param array $options Configuration options
     */
    public static function run(callable $requestHandler, array $options = []): void
    {
        $logToStderr = isset($options['autoDumpToStderr']) ? (bool)$options['autoDumpToStderr'] : true;
        $throwOnPollution = isset($options['throwOnPollution']) ? (bool)$options['throwOnPollution'] : false;
        $maxRequests = isset($options['maxRequests']) ? (int)$options['maxRequests'] : 0;
        $gcEvery = isset($options['gcEvery']) ? (int)$options['gcEvery'] : 50;

        Hunter::configure([
            'throwOnPollution' => $throwOnPollution,
            'autoDumpToStderr' => $logToStderr,
        ]);

        $requestCount = 0;

        // Loop using frankenphp_handle_request() if running under FrankenPHP, or fallback loop for testing
        $isFranken = function_exists('frankenphp_handle_request');

        while (!$isFranken || frankenphp_handle_request()) {
            $requestCount++;

            $report = null;
            Hunter::watch($requestHandler, $report);

            if ($report && !$report->isClean() && $logToStderr) {
                fwrite(STDERR, sprintf("\n[Franken-Leak-Hunter] Request #%d State Pollution Alert!\n", $requestCount));
                fwrite(STDERR, $report->renderTerminal() . "\n");
            }

            // Periodic garbage collection trigger
            if ($gcEvery > 0 && ($requestCount % $gcEvery === 0) && function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }

            if ($maxRequests > 0 && $requestCount >= $maxRequests) {
                break;
            }

            // If not in FrankenPHP worker runtime, break to prevent infinite loop in CLI tests
            if (!$isFranken) {
                break;
            }
        }
    }
}
