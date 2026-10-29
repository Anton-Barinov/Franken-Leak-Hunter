<?php
/**
 * Franken-Leak-Hunter
 * State Pollution & Memory Leak Hazard
 *
 * @author Anton Barinov <https://github.com/Anton-Barinov>
 * @license MIT
 */

namespace FrankenLeakHunter\Analyzer;

class Hazard
{
    const SEVERITY_CRITICAL = 'CRITICAL';
    const SEVERITY_HIGH     = 'HIGH';
    const SEVERITY_MEDIUM   = 'MEDIUM';
    const SEVERITY_INFO     = 'INFO';

    /** @var string */
    public $file;

    /** @var int */
    public $line;

    /** @var string */
    public $severity;

    /** @var string */
    public $rule;

    /** @var string */
    public $symbol;

    /** @var string */
    public $message;

    /** @var string */
    public $codeSnippet;

    /** @var string */
    public $remediation;

    public function __construct(
        string $file,
        int $line,
        string $severity,
        string $rule,
        string $symbol,
        string $message,
        string $codeSnippet = '',
        string $remediation = ''
    ) {
        $this->file        = $file;
        $this->line        = $line;
        $this->severity    = $severity;
        $this->rule        = $rule;
        $this->symbol      = $symbol;
        $this->message     = $message;
        $this->codeSnippet = $codeSnippet;
        $this->remediation = $remediation;
    }

    public function toArray(): array
    {
        return [
            'file'        => $this->file,
            'line'        => $this->line,
            'severity'    => $this->severity,
            'rule'        => $this->rule,
            'symbol'      => $this->symbol,
            'message'     => $this->message,
            'snippet'     => $this->codeSnippet,
            'remediation' => $this->remediation,
        ];
    }
}
