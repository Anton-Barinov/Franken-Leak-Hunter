<?php
/**
 * Franken-Leak-Hunter
 * Analysis Result Collection
 *
 * @author Anton Barinov <https://github.com/Anton-Barinov>
 * @license MIT
 */

namespace FrankenLeakHunter\Analyzer;

class AnalysisResult
{
    /** @var Hazard[] */
    public array $hazards = [];

    /** @var int */
    public int $filesScanned = 0;

    /** @var float */
    public float $duration = 0.0;

    public function addHazard(Hazard $hazard): void
    {
        $this->hazards[] = $hazard;
    }

    public function hasHazards(): bool
    {
        return !empty($this->hazards);
    }

    public function count(): int
    {
        return count($this->hazards);
    }

    public function countBySeverity(string $severity): int
    {
        $c = 0;
        foreach ($this->hazards as $h) {
            if ($h->severity === $severity) {
                $c++;
            }
        }
        return $c;
    }

    public function getCriticalCount(): int
    {
        return $this->countBySeverity(Hazard::SEVERITY_CRITICAL);
    }

    public function getHighCount(): int
    {
        return $this->countBySeverity(Hazard::SEVERITY_HIGH);
    }

    public function getMediumCount(): int
    {
        return $this->countBySeverity(Hazard::SEVERITY_MEDIUM);
    }

    public function toArray(): array
    {
        return [
            'summary' => [
                'files_scanned' => $this->filesScanned,
                'total_hazards' => count($this->hazards),
                'critical'      => $this->getCriticalCount(),
                'high'          => $this->getHighCount(),
                'medium'        => $this->getMediumCount(),
                'duration_ms'   => round($this->duration * 1000, 2),
            ],
            'hazards' => array_map(function (Hazard $h) {
                return $h->toArray();
            }, $this->hazards),
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
