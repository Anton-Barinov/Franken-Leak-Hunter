<?php
/**
 * Franken-Leak-Hunter
 * State & Memory Diff Report
 *
 * @author Anton Barinov <https://github.com/Anton-Barinov>
 * @license MIT
 */

namespace FrankenLeakHunter\Runtime;

class DiffReport
{
    /** @var float Request execution time in seconds */
    public float $durationSeconds = 0.0;

    /** @var int Net memory allocated in bytes (+ for growth, - for freed) */
    public int $memoryDeltaBytes = 0;

    /** @var int Peak memory in bytes */
    public int $memoryPeakBytes = 0;

    /** @var array List of static property pollutions */
    public array $staticPollutions = [];

    /** @var array List of global variable pollutions */
    public array $globalPollutions = [];

    /** @var array List of leaked unclosed resources */
    public array $resourceLeaks = [];

    public function addStaticPollution(string $class, string $property, string $message): void
    {
        $this->staticPollutions[] = [
            'class'    => $class,
            'property' => $property,
            'message'  => $message,
        ];
    }

    public function addGlobalPollution(string $key, string $message): void
    {
        $this->globalPollutions[] = [
            'variable' => '$GLOBALS[' . $key . ']',
            'message'  => $message,
        ];
    }

    public function addResourceLeak(int $id, string $type, string $message): void
    {
        $this->resourceLeaks[] = [
            'resource_id' => $id,
            'type'        => $type,
            'message'     => $message,
        ];
    }

    public function hasPollution(): bool
    {
        return !empty($this->staticPollutions) || !empty($this->globalPollutions);
    }

    public function hasLeaks(): bool
    {
        return !empty($this->resourceLeaks) || ($this->memoryDeltaBytes > 65536); // > 64KB net leak threshold
    }

    public function isClean(): bool
    {
        return empty($this->staticPollutions) && empty($this->globalPollutions) && empty($this->resourceLeaks);
    }

    public function formatBytes(int $bytes): string
    {
        $sign = $bytes < 0 ? '-' : '+';
        $abs = abs($bytes);
        if ($abs >= 1048576) {
            return $sign . round($abs / 1048576, 2) . ' MB';
        }
        if ($abs >= 1024) {
            return $sign . round($abs / 1024, 2) . ' KB';
        }
        return $sign . $abs . ' B';
    }

    public function renderTerminal(bool $useColor = true): string
    {
        $red    = $useColor ? "\033[31m" : "";
        $green  = $useColor ? "\033[32m" : "";
        $yellow = $useColor ? "\033[33m" : "";
        $cyan   = $useColor ? "\033[36m" : "";
        $bold   = $useColor ? "\033[1m" : "";
        $reset  = $useColor ? "\033[0m" : "";

        $out = [];
        $out[] = "{$cyan}────────────────────────────────────────────────────────{$reset}";
        $out[] = "{$bold}Franken-Leak-Hunter Runtime Diff Report{$reset}";
        $out[] = "{$cyan}────────────────────────────────────────────────────────{$reset}";
        $out[] = sprintf(
            "⏱️ Execution: %s ms | 💾 Net Memory: %s | 📈 Peak: %s",
            round($this->durationSeconds * 1000, 2),
            $this->formatBytes($this->memoryDeltaBytes),
            $this->formatBytes($this->memoryPeakBytes)
        );

        if ($this->isClean()) {
            $out[] = "{$green}✅ CLEAN WORKER STATE: No static property, global, or resource leaks detected.{$reset}";
            $out[] = "{$cyan}────────────────────────────────────────────────────────{$reset}";
            return implode("\n", $out);
        }

        if (!empty($this->staticPollutions)) {
            $out[] = "\n{$red}{$bold}🚨 STATIC STATE POLLUTION (Cross-Request Leaks):{$reset}";
            foreach ($this->staticPollutions as $p) {
                $out[] = "  {$red}• [{$p['class']}::\${$p['property']}]{$reset} {$p['message']}";
            }
        }

        if (!empty($this->globalPollutions)) {
            $out[] = "\n{$yellow}{$bold}⚠️ GLOBAL ENVIRONMENT POLLUTION:{$reset}";
            foreach ($this->globalPollutions as $g) {
                $out[] = "  {$yellow}• [{$g['variable']}]{$reset} {$g['message']}";
            }
        }

        if (!empty($this->resourceLeaks)) {
            $out[] = "\n{$red}{$bold}🔌 UNCLOSED RESOURCE LEAKS:{$reset}";
            foreach ($this->resourceLeaks as $r) {
                $out[] = "  {$red}• [Resource #{$r['resource_id']} - {$r['type']}]{$reset} {$r['message']}";
            }
        }

        $out[] = "{$cyan}────────────────────────────────────────────────────────{$reset}";
        return implode("\n", $out);
    }

    public function toArray(): array
    {
        return [
            'is_clean'           => $this->isClean(),
            'duration_ms'        => round($this->durationSeconds * 1000, 2),
            'memory_delta_bytes' => $this->memoryDeltaBytes,
            'memory_delta'       => $this->formatBytes($this->memoryDeltaBytes),
            'memory_peak'        => $this->formatBytes($this->memoryPeakBytes),
            'static_pollutions'  => $this->staticPollutions,
            'global_pollutions'  => $this->globalPollutions,
            'resource_leaks'     => $this->resourceLeaks,
        ];
    }

    public function toJson(bool $pretty = true): string
    {
        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
        if ($pretty) {
            $flags |= JSON_PRETTY_PRINT;
        }
        return json_encode($this->toArray(), $flags);
    }
}
