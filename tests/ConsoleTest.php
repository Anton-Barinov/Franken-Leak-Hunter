<?php
/**
 * Console CLI Integration Tests
 */

require_once dirname(__DIR__) . '/src/autoload.php';

use FrankenLeakHunter\Console\Application;

class ConsoleTest
{
    public function testAnalyzeCommandExitsWithHazardsOnFixtures()
    {
        $app = new Application();
        ob_start();
        $exitCode = $app->run(['franken-leak-hunter', 'analyze', __DIR__ . '/Fixtures/LeakyCartService.php']);
        $output = ob_get_clean();

        assert($exitCode === 1, "Expected exit code 1 for hazards found");
        assert(strpos($output, 'LeakyCartService::$items') !== false, "Output must mention hazard symbol");
        echo "  ✓ testAnalyzeCommandExitsWithHazardsOnFixtures passed\n";
    }

    public function testAnalyzeCommandJsonFormat()
    {
        $app = new Application();
        ob_start();
        $exitCode = $app->run(['franken-leak-hunter', 'analyze', __DIR__ . '/Fixtures/LeakyCartService.php', '--format=json']);
        $output = ob_get_clean();

        // Extract JSON string from output
        $jsonStart = strpos($output, '{');
        assert($jsonStart !== false, "JSON start bracket found");
        $jsonStr = substr($output, $jsonStart);
        $decoded = json_decode($jsonStr, true);

        assert(json_last_error() === JSON_ERROR_NONE, "Must be valid JSON");
        assert(isset($decoded['summary']['total_hazards']) && $decoded['summary']['total_hazards'] >= 1);
        echo "  ✓ testAnalyzeCommandJsonFormat passed\n";
    }

    public function testHelpCommandExitsZero()
    {
        $app = new Application();
        ob_start();
        $exitCode = $app->run(['franken-leak-hunter', 'help']);
        $output = ob_get_clean();

        assert($exitCode === 0);
        assert(strpos($output, 'USAGE') !== false);
        echo "  ✓ testHelpCommandExitsZero passed\n";
    }
}
