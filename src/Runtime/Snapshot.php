<?php
/**
 * Franken-Leak-Hunter
 * Worker State & Memory Snapshot
 *
 * @author Anton Barinov <https://github.com/Anton-Barinov>
 * @license MIT
 */

namespace FrankenLeakHunter\Runtime;

class Snapshot
{
    /** @var int Bytes of memory allocated */
    public int $memoryUsage;

    /** @var int Bytes of memory reserved by system */
    public int $memoryRealUsage;

    /** @var int Peak memory */
    public int $memoryPeak;

    /** @var float High-resolution timestamp */
    public float $timestamp;

    /** @var array<string, array<string, mixed>> [ ClassName => [ propName => metadata ] ] */
    public array $staticProperties = [];

    /** @var array<string, mixed> Snapshot of $GLOBALS */
    public array $globalVariables = [];

    /** @var array<int, string> Open resources list */
    public array $openResources = [];

    /** @var array Garbage collector status */
    public array $gcStatus = [];

    /**
     * Capture current state snapshot
     *
     * @param array $monitoredNamespaces (optional prefix filter, e.g. ['App\\', 'Domain\\'])
     * @return self
     */
    public static function capture(array $monitoredNamespaces = []): self
    {
        $snap = new self();
        $snap->timestamp       = microtime(true);
        $snap->memoryUsage     = memory_get_usage(false);
        $snap->memoryRealUsage = memory_get_usage(true);
        $snap->memoryPeak      = memory_get_peak_usage(false);

        if (function_exists('gc_status')) {
            $snap->gcStatus = gc_status();
        }

        // 1. Capture Open Resources
        if (function_exists('get_resources')) {
            $resources = get_resources();
            foreach ($resources as $resId => $res) {
                if (is_resource($res)) {
                    $snap->openResources[(int)$resId] = get_resource_type($res);
                }
            }
        }

        // 2. Capture Monitored Static Properties
        $declaredClasses = get_declared_classes();
        foreach ($declaredClasses as $className) {
            // Skip core PHP classes and FrankenLeakHunter internals
            if (strncmp($className, 'FrankenLeakHunter\\', 18) === 0) {
                continue;
            }

            if (!empty($monitoredNamespaces)) {
                $matched = false;
                foreach ($monitoredNamespaces as $ns) {
                    if (strncmp($className, $ns, strlen($ns)) === 0) {
                        $matched = true;
                        break;
                    }
                }
                if (!$matched) {
                    continue;
                }
            } else {
                // Filter out standard vendor/internal classes if no prefix specified
                if (strncmp($className, 'Composer\\', 9) === 0 ||
                    strncmp($className, 'PHPUnit\\', 8) === 0 ||
                    strncmp($className, 'Symfony\\Polyfill', 16) === 0) {
                    continue;
                }
            }

            try {
                $ref = new \ReflectionClass($className);
                if ($ref->isInternal()) {
                    continue;
                }

                $staticProps = $ref->getStaticProperties();
                if (!empty($staticProps)) {
                    $propMeta = [];
                    foreach ($staticProps as $propName => $propValue) {
                        $propMeta[$propName] = self::summarizeValue($propValue);
                    }
                    $snap->staticProperties[$className] = $propMeta;
                }
            } catch (\Throwable $e) {
                // Ignore classes that cannot be reflected
            }
        }

        // 3. Capture $GLOBALS
        $ignoreGlobals = [
            'GLOBALS', '_SERVER', '_GET', '_POST', '_FILES', '_COOKIE', '_SESSION',
            '_ENV', 'argv', 'argc', 'http_response_header',
        ];

        foreach ($GLOBALS as $key => $val) {
            if (in_array($key, $ignoreGlobals, true) || strncmp($key, '__flh_', 6) === 0) {
                continue;
            }
            $snap->globalVariables[$key] = self::summarizeValue($val);
        }

        return $snap;
    }

    /**
     * Create lightweight fingerprint of a value without storing huge memory graphs
     */
    private static function summarizeValue($val): array
    {
        if (is_null($val)) {
            return ['type' => 'null', 'count' => 0, 'raw' => null];
        }
        if (is_bool($val)) {
            return ['type' => 'bool', 'count' => 0, 'raw' => $val];
        }
        if (is_scalar($val)) {
            return [
                'type'  => gettype($val),
                'count' => 0,
                'raw'   => is_string($val) && strlen($val) > 100 ? substr($val, 0, 100) . '...' : $val,
                'len'   => is_string($val) ? strlen($val) : null,
            ];
        }
        if (is_array($val)) {
            return [
                'type'  => 'array',
                'count' => count($val),
                'keys'  => array_slice(array_keys($val), 0, 10),
            ];
        }
        if (is_object($val)) {
            return [
                'type'     => 'object',
                'class'    => get_class($val),
                'count'    => ($val instanceof \Countable) ? count($val) : 1,
                'spl_id'   => spl_object_id($val),
            ];
        }
        if (is_resource($val)) {
            return [
                'type'          => 'resource',
                'resource_type' => get_resource_type($val),
                'id'            => (int)$val,
            ];
        }

        return ['type' => gettype($val), 'count' => 0];
    }
}
