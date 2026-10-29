<?php
/**
 * Franken-Leak-Hunter
 * PSR-15 Compatible Middleware for Slim, Mezzio, Laravel, Symfony
 *
 * @author Anton Barinov <https://github.com/Anton-Barinov>
 * @license MIT
 */

namespace FrankenLeakHunter\Integration;

use FrankenLeakHunter\Runtime\Hunter;
use FrankenLeakHunter\Runtime\DiffReport;

class Psr15Middleware
{
    private array $options;

    public function __construct(array $options = [])
    {
        $this->options = array_merge([
            'throwOnPollution' => false,
            'autoDumpToStderr' => true,
            'headerName'       => 'X-Franken-Leak-Hunter',
        ], $options);

        Hunter::configure([
            'throwOnPollution' => $this->options['throwOnPollution'],
            'autoDumpToStderr' => $this->options['autoDumpToStderr'],
        ]);
    }

    /**
     * Standard PSR-15 process method
     */
    public function process($request, $handler)
    {
        $report = null;
        $response = Hunter::watch(function () use ($request, $handler) {
            return $handler->handle($request);
        }, $report);

        // Optionally attach diagnostics header to response if supported
        if ($report && is_object($response) && method_exists($response, 'withHeader')) {
            $status = $report->isClean() ? 'clean' : 'polluted';
            $response = $response->withHeader(
                $this->options['headerName'],
                "status={$status}; delta={$report->formatBytes($report->memoryDeltaBytes)}"
            );
        }

        return $response;
    }

    /**
     * Classic callable middleware (e.g. Slim 3 / double-pass)
     */
    public function __invoke($request, $response, callable $next)
    {
        $report = null;
        $res = Hunter::watch(function () use ($request, $response, $next) {
            return $next($request, $response);
        }, $report);

        return $res;
    }
}
