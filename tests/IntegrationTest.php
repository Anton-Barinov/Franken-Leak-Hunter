<?php
/**
 * Framework Integrations Tests
 */

require_once dirname(__DIR__) . '/src/autoload.php';
require_once __DIR__ . '/Fixtures/LeakyCartService.php';

use FrankenLeakHunter\Integration\FrankenPhpWorker;
use FrankenLeakHunter\Integration\Psr15Middleware;
use FrankenLeakHunter\Runtime\Hunter;
use FrankenLeakHunter\Runtime\StatePollutionException;
use Tests\Fixtures\LeakyCartService;

class IntegrationTest
{
    public function testFrankenPhpWorkerRunExecutesHandler()
    {
        $executed = 0;
        FrankenPhpWorker::run(function () use (&$executed) {
            $executed++;
        }, [
            'autoDumpToStderr' => false,
            'maxRequests'      => 1,
        ]);

        assert($executed === 1, "Expected worker handler to execute");
        echo "  ✓ testFrankenPhpWorkerRunExecutesHandler passed\n";
    }

    public function testPsr15MiddlewareAttachesDiagnosticsHeader()
    {
        $middleware = new Psr15Middleware([
            'autoDumpToStderr' => false,
        ]);

        $mockRequest = new \stdClass();
        $mockResponse = new class {
            public array $headers = [];
            public function withHeader(string $name, string $val) {
                $new = clone $this;
                $new->headers[$name] = $val;
                return $new;
            }
        };

        $mockHandler = new class($mockResponse) {
            private $resp;
            public function __construct($resp) { $this->resp = $resp; }
            public function handle($req) { return $this->resp; }
        };

        $res = $middleware->process($mockRequest, $mockHandler);
        assert(isset($res->headers['X-Franken-Leak-Hunter']), "Expected X-Franken-Leak-Hunter diagnostic header");
        assert(strpos($res->headers['X-Franken-Leak-Hunter'], 'status=clean') !== false, "Expected clean status in header");
        echo "  ✓ testPsr15MiddlewareAttachesDiagnosticsHeader passed\n";
    }

    public function testThrowOnPollutionOptionThrowsException()
    {
        Hunter::configure([
            'throwOnPollution' => true,
            'autoDumpToStderr' => false,
        ]);

        LeakyCartService::$items = [];
        $threw = false;

        try {
            Hunter::watch(function () {
                $cart = new LeakyCartService();
                $cart->addItem(99, 'Violating item', 10.0);
            });
        } catch (StatePollutionException $e) {
            $threw = true;
            assert($e->getReport()->hasPollution());
        } finally {
            Hunter::configure([
                'throwOnPollution' => false,
                'autoDumpToStderr' => false,
            ]);
        }

        assert($threw, "Expected StatePollutionException to be thrown when throwOnPollution=true");
        echo "  ✓ testThrowOnPollutionOptionThrowsException passed\n";
    }
}
