<?php
/**
 * Franken-Leak-Hunter
 * Console Application Runner
 *
 * @author Anton Barinov <https://github.com/Anton-Barinov>
 * @license MIT
 */

namespace FrankenLeakHunter\Console;

use FrankenLeakHunter\Analyzer\StaticAnalyzer;
use FrankenLeakHunter\Analyzer\Hazard;
use FrankenLeakHunter\Runtime\Hunter;

class Application
{
    public const VERSION = '1.0.0';

    public function run(array $argv): int
    {
        $command = isset($argv[1]) ? $argv[1] : 'help';

        switch ($command) {
            case 'analyze':
                return $this->runAnalyze(array_slice($argv, 2));

            case 'profile':
                return $this->runProfile(array_slice($argv, 2));

            case 'init':
                return $this->runInit(array_slice($argv, 2));

            case '--version':
            case '-v':
            case 'version':
                echo "Franken-Leak-Hunter (PHP-Sentinel) v" . self::VERSION . "\n";
                return 0;

            case 'help':
            case '--help':
            case '-h':
            default:
                $this->renderHelp();
                return 0;
        }
    }

    private function runAnalyze(array $args): int
    {
        $target = isset($args[0]) && strncmp($args[0], '-', 1) !== 0 ? $args[0] : '.';
        $format = 'table';
        $minSeverity = 'ALL';

        foreach ($args as $arg) {
            if (strncmp($arg, '--format=', 9) === 0) {
                $format = substr($arg, 9);
            }
            if (strncmp($arg, '--min-severity=', 15) === 0) {
                $minSeverity = strtoupper(substr($arg, 15));
            }
        }

        echo "\033[1;36m🔎 Franken-Leak-Hunter: Static State Pollution Scan\033[0m\n";
        echo "Target: {$target}\n\n";

        $analyzer = new StaticAnalyzer();
        $result = $analyzer->analyze($target);

        if ($format === 'json') {
            echo $result->toJson() . "\n";
            return $result->hasHazards() ? 1 : 0;
        }

        if (!$result->hasHazards()) {
            echo "\033[32m✅ No state pollution hazards found in {$result->filesScanned} files!\033[0m\n";
            echo sprintf("Scan completed in %s ms.\n", round($result->duration * 1000, 2));
            return 0;
        }

        echo "\033[1;31m🚨 FOUND " . count($result->hazards) . " HAZARDS in {$result->filesScanned} files:\033[0m\n";
        echo "──────────────────────────────────────────────────────────────────\n";

        foreach ($result->hazards as $h) {
            $badge = match ($h->severity) {
                Hazard::SEVERITY_CRITICAL => "\033[1;41;37m CRITICAL \033[0m",
                Hazard::SEVERITY_HIGH     => "\033[1;31m HIGH \033[0m",
                Hazard::SEVERITY_MEDIUM   => "\033[1;33m MEDIUM \033[0m",
                default                   => "\033[1;34m INFO \033[0m",
            };

            echo "{$badge} \033[1m{$h->symbol}\033[0m in \033[36m{$h->file}:{$h->line}\033[0m\n";
            echo "  \033[37m{$h->message}\033[0m\n";
            if (!empty($h->codeSnippet)) {
                echo "  \033[90m>\033[0m \033[33m{$h->codeSnippet}\033[0m\n";
            }
            if (!empty($h->remediation)) {
                echo "  \033[32m💡 Fix: {$h->remediation}\033[0m\n";
            }
            echo "\n";
        }

        echo "──────────────────────────────────────────────────────────────────\n";
        echo sprintf(
            "Summary: \033[31m%d Critical\033[0m | \033[33m%d High\033[0m | \033[34m%d Medium\033[0m (Duration: %s ms)\n",
            $result->getCriticalCount(),
            $result->getHighCount(),
            $result->getMediumCount(),
            round($result->duration * 1000, 2)
        );

        return $result->getCriticalCount() > 0 ? 1 : 0;
    }

    private function runProfile(array $args): int
    {
        $script = isset($args[0]) && strncmp($args[0], '-', 1) !== 0 ? $args[0] : '';
        $iterations = 5;

        foreach ($args as $arg) {
            if (strncmp($arg, '--requests=', 11) === 0) {
                $iterations = (int)substr($arg, 11);
            }
        }

        if (empty($script) || !file_exists($script)) {
            fwrite(STDERR, "\033[31mError: Target script '{$script}' not found.\033[0m\n");
            fwrite(STDERR, "Usage: franken-leak-hunter profile <script.php> [--requests=10]\n");
            return 1;
        }

        echo "\033[1;36m🔬 Franken-Leak-Hunter: Multi-Request Simulation Profiler\033[0m\n";
        echo "Script: {$script} | Simulated Requests: {$iterations}\n\n";

        $fullPath = realpath($script);

        $results = Hunter::simulate(function () use ($fullPath) {
            // Run script inside simulated worker turn
            (function ($file) {
                include $file;
            })($fullPath);
        }, $iterations);

        echo "──────────────────────────────────────────────────────────────────\n";
        echo sprintf("Completed %d simulated worker requests.\n", $results['iterations']);
        echo sprintf("Total Memory Delta: \033[1m%s bytes\033[0m\n", $results['total_memory_growth']);
        echo sprintf("Average Delta / Request: %s bytes\n", $results['avg_growth_per_request']);

        if ($results['monotonic_leak_suspect']) {
            echo "\033[1;41;37m ⚠️ PERSISTENT MEMORY LEAK SUSPECTED: Memory grew monotonically across all iterations! \033[0m\n";
        }

        if (!empty($results['static_pollutions'])) {
            echo "\n\033[31m🚨 Static state pollution detected during execution:\033[0m\n";
            foreach ($results['static_pollutions'] as $reqIdx => $pollutions) {
                echo "  Request #{$reqIdx}:\n";
                foreach ($pollutions as $p) {
                    echo "    • [{$p['class']}::\${$p['property']}] {$p['message']}\n";
                }
            }
        } else {
            echo "\033[32m✅ No static state cross-request pollutions detected during runtime simulation!\033[0m\n";
        }

        echo "──────────────────────────────────────────────────────────────────\n";
        return (!empty($results['static_pollutions']) || $results['monotonic_leak_suspect']) ? 1 : 0;
    }

    private function runInit(array $args): int
    {
        $target = 'worker.php';
        if (file_exists($target)) {
            echo "\033[33mWarning: worker.php already exists.\033[0m\n";
            return 0;
        }

        $template = <<<PHP
<?php
/**
 * FrankenPHP Worker with Franken-Leak-Hunter Protection
 */

require_once __DIR__ . '/vendor/autoload.php';

use FrankenLeakHunter\Integration\FrankenPhpWorker;

FrankenPhpWorker::run(function () {
    // Your request handling logic:
    // \$response = \$app->handle(\$request);
    // \$response->send();
    echo "Hello from clean worker mode!\n";
}, [
    'autoDumpToStderr' => true,
    'throwOnPollution' => false,
]);
PHP;

        file_put_contents($target, $template);
        echo "\033[32m✅ Created worker.php boilerplate with Franken-Leak-Hunter integration!\033[0m\n";
        return 0;
    }

    private function renderHelp(): void
    {
        $v = self::VERSION;
        echo <<<HELP
\033[1;36mFranken-Leak-Hunter (PHP-Sentinel)\033[0m v{$v}
Memory Leak & State Pollution Detector for FrankenPHP, RoadRunner & Worker-Mode PHP.

\033[1mUSAGE:\033[0m
  franken-leak-hunter <command> [options]

\033[1mCOMMANDS:\033[0m
  \033[32manalyze <path>\033[0m     Static AST scan for static properties, local variables & singletons
  \033[32mprofile <script>\033[0m   Simulate multi-request worker loop & detect runtime memory creep
  \033[32minit\033[0m               Generate a clean worker.php boilerplate with leak protection
  \033[32mhelp\033[0m               Display this help message

\033[1mOPTIONS for analyze:\033[0m
  --format=table|json        Output format (default: table)
  --min-severity=CRITICAL    Minimum severity threshold to report

\033[1mOPTIONS for profile:\033[0m
  --requests=N               Number of simulated requests to execute (default: 5)

\033[1mEXAMPLES:\033[0m
  franken-leak-hunter analyze src/
  franken-leak-hunter analyze src/ --format=json
  franken-leak-hunter profile worker.php --requests=20

HELP;
    }
}
