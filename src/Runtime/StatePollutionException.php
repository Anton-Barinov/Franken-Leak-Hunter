<?php
/**
 * Franken-Leak-Hunter
 * State Pollution Exception
 *
 * @author Anton Barinov <https://github.com/Anton-Barinov>
 * @license MIT
 */

namespace FrankenLeakHunter\Runtime;

class StatePollutionException extends \RuntimeException
{
    private DiffReport $report;

    public function __construct(DiffReport $report, string $message = "")
    {
        $this->report = $report;
        if (empty($message)) {
            $message = "Worker state pollution detected: " . count($report->staticPollutions) . " static pollutions, " .
                       count($report->globalPollutions) . " global pollutions, " .
                       count($report->resourceLeaks) . " resource leaks.";
        }
        parent::__construct($message);
    }

    public function getReport(): DiffReport
    {
        return $this->report;
    }
}
