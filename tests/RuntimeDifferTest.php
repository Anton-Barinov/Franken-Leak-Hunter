<?php
/**
 * Runtime Differ Unit Tests
 */

require_once dirname(__DIR__) . '/src/autoload.php';
require_once __DIR__ . '/Fixtures/LeakyCartService.php';
require_once __DIR__ . '/Fixtures/CleanService.php';
require_once __DIR__ . '/Fixtures/GlobalPolluter.php';
require_once __DIR__ . '/Fixtures/ResourceLeaker.php';

use FrankenLeakHunter\Runtime\Hunter;
use Tests\Fixtures\LeakyCartService;
use Tests\Fixtures\CleanService;
use Tests\Fixtures\GlobalPolluter;
use Tests\Fixtures\ResourceLeaker;

class RuntimeDifferTest
{
    public function testDetectsLeakyCartServiceInRuntime()
    {
        // Reset static cart initially
        LeakyCartService::$items = [];

        $report = null;
        Hunter::watch(function () {
            $cart = new LeakyCartService();
            $cart->addItem(101, 'MacBook Pro', 2499.00);
            $cart->addItem(102, 'Magic Mouse', 99.00);
        }, $report);

        assert($report !== null, "Expected report to be returned");
        assert($report->hasPollution(), "Expected pollution in LeakyCartService");
        assert(!empty($report->staticPollutions), "Expected static pollutions");

        $found = false;
        foreach ($report->staticPollutions as $p) {
            if ($p['class'] === LeakyCartService::class && $p['property'] === 'items') {
                $found = true;
                assert(strpos($p['message'], '+2 items') !== false, "Message should mention +2 items");
                break;
            }
        }
        assert($found, "Did not detect static items pollution in LeakyCartService");
        echo "  ✓ testDetectsLeakyCartServiceInRuntime passed\n";
    }

    public function testCleanServiceProducesCleanReport()
    {
        $service = new CleanService();

        $report = null;
        Hunter::watch(function () use ($service) {
            $service->set('session_token', 'abc123xyz');
            // Clean service resets state at end of request
            $service->reset();
        }, $report);

        assert($report !== null);
        assert($report->isClean(), "Expected clean report when reset() is properly called");
        echo "  ✓ testCleanServiceProducesCleanReport passed\n";
    }

    public function testDetectsGlobalVariablePollution()
    {
        unset($GLOBALS['current_user_id']);

        $report = null;
        Hunter::watch(function () {
            $polluter = new GlobalPolluter();
            $polluter->setContext('user_9876');
        }, $report);

        assert($report !== null);
        assert(!empty($report->globalPollutions), "Expected global pollutions");
        $found = false;
        foreach ($report->globalPollutions as $g) {
            if (strpos($g['variable'], 'current_user_id') !== false) {
                $found = true;
                break;
            }
        }
        assert($found, "Expected current_user_id global pollution");

        // Cleanup
        unset($GLOBALS['current_user_id']);
        echo "  ✓ testDetectsGlobalVariablePollution passed\n";
    }

    public function testDetectsResourceLeak()
    {
        $report = null;
        $leaker = new ResourceLeaker();

        Hunter::watch(function () use ($leaker) {
            $leaker->openUnclosedStream();
        }, $report);

        assert($report !== null);
        assert(!empty($report->resourceLeaks), "Expected resource leak for unclosed stream");
        echo "  ✓ testDetectsResourceLeak passed\n";
    }
}
