<?php
/**
 * Franken-Leak-Hunter
 * State & Memory Diff Engine
 *
 * @author Anton Barinov <https://github.com/Anton-Barinov>
 * @license MIT
 */

namespace FrankenLeakHunter\Runtime;

class StateDiffer
{
    /**
     * Compute delta between two snapshots
     *
     * @param Snapshot $before
     * @param Snapshot $after
     * @return DiffReport
     */
    public function diff(Snapshot $before, Snapshot $after): DiffReport
    {
        $report = new DiffReport();
        $report->durationSeconds   = max(0.000001, $after->timestamp - $before->timestamp);
        $report->memoryDeltaBytes  = $after->memoryUsage - $before->memoryUsage;
        $report->memoryPeakBytes   = $after->memoryPeak;

        // 1. Diff Static Properties
        foreach ($after->staticProperties as $className => $afterProps) {
            $beforeProps = isset($before->staticProperties[$className]) ? $before->staticProperties[$className] : [];

            foreach ($afterProps as $propName => $afterMeta) {
                $beforeMeta = isset($beforeProps[$propName]) ? $beforeProps[$propName] : null;

                if ($beforeMeta === null) {
                    $report->addStaticPollution(
                        $className,
                        $propName,
                        "New static property initialized: {$className}::\${$propName} ({$afterMeta['type']})"
                    );
                    continue;
                }

                // Check for array growth
                if ($afterMeta['type'] === 'array' && $beforeMeta['type'] === 'array') {
                    if ($afterMeta['count'] > $beforeMeta['count']) {
                        $delta = $afterMeta['count'] - $beforeMeta['count'];
                        $report->addStaticPollution(
                            $className,
                            $propName,
                            "Static collection {$className}::\${$propName} accumulated +{$delta} items (grew from {$beforeMeta['count']} to {$afterMeta['count']})"
                        );
                    }
                } elseif ($afterMeta['type'] !== $beforeMeta['type']) {
                    $report->addStaticPollution(
                        $className,
                        $propName,
                        "Static property {$className}::\${$propName} type mutated from {$beforeMeta['type']} to {$afterMeta['type']}"
                    );
                } elseif ($afterMeta['type'] === 'object') {
                    if (isset($afterMeta['spl_id'], $beforeMeta['spl_id']) && $afterMeta['spl_id'] !== $beforeMeta['spl_id']) {
                        $report->addStaticPollution(
                            $className,
                            $propName,
                            "Static object reference {$className}::\${$propName} replaced with new instance #{$afterMeta['spl_id']}"
                        );
                    }
                } elseif (isset($afterMeta['raw'], $beforeMeta['raw']) && $afterMeta['raw'] !== $beforeMeta['raw']) {
                    $report->addStaticPollution(
                        $className,
                        $propName,
                        "Static scalar {$className}::\${$propName} changed value across request"
                    );
                }
            }
        }

        // 2. Diff $GLOBALS
        foreach ($after->globalVariables as $key => $afterMeta) {
            if (!isset($before->globalVariables[$key])) {
                $report->addGlobalPollution(
                    $key,
                    "Global variable \$GLOBALS['{$key}'] was created during request and persisted ({$afterMeta['type']})"
                );
            } else {
                $beforeMeta = $before->globalVariables[$key];
                if ($afterMeta['type'] === 'array' && $beforeMeta['type'] === 'array') {
                    if ($afterMeta['count'] > $beforeMeta['count']) {
                        $delta = $afterMeta['count'] - $beforeMeta['count'];
                        $report->addGlobalPollution(
                            $key,
                            "\$GLOBALS['{$key}'] array accumulated +{$delta} elements"
                        );
                    }
                }
            }
        }

        // 3. Diff Open Resources
        foreach ($after->openResources as $resId => $resType) {
            if (!isset($before->openResources[$resId])) {
                $report->addResourceLeak(
                    $resId,
                    $resType,
                    "Resource #{$resId} ({$resType}) was opened during request but not closed"
                );
            }
        }

        return $report;
    }
}
