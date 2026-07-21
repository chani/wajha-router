<?php
/**
 * Safi/Wajha Router
 * @author Jean Bruenn
 * @copyright 2026 All Rights Reserved
 * @see https://github.com/chani/wajha-router
 * @see https://packagist.org/packages/chani/wajha
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use Safi\Wajha\WajhaCompiler;
use Safi\Wajha\WajhaDispatcher;

/** @var array{routes: list<array{method: string, path: string, handler: mixed}>, requests: array<string, array{method: string, uri: string}>} $benchData */
$benchData = require __DIR__ . '/benchmarks/RealWorldBench.php';
$routes = $benchData['routes'];
$requests = $benchData['requests'];
$iterations = 100000;

// 1. Setup Wajha
$wajhaCompiler = new WajhaCompiler();
foreach ($routes as $route) {
    $wajhaCompiler->addRoute($route['method'], $route['path'], $route['handler']);
}
$wajhaDispatcher = new WajhaDispatcher($wajhaCompiler->compile());

// 2. Setup FastRoute
$fastRouteDispatcher = FastRoute\simpleDispatcher(function (FastRoute\RouteCollector $r) use ($routes) {
    foreach ($routes as $route) {
        $r->addRoute($route['method'], $route['path'], $route['handler']);
    }
});

echo "=== REAL-WORLD DATASET BENCHMARK ({$iterations} Iterations per Scenario) ===\n\n";

printf("%-18s | %-16s | %-16s | %-10s\n", "Scenario", "Wajha (ops/s)", "FastRoute (ops/s)", "Ratio");
echo str_repeat("-", 70) . "\n";

foreach ($requests as $scenarioName => $req) {
    // Warmup
    for ($i = 0; $i < 100; $i++) {
        $wajhaDispatcher->dispatch($req['method'], $req['uri']);
        $fastRouteDispatcher->dispatch($req['method'], $req['uri']);
    }

    // Benchmark Wajha
    gc_collect_cycles();
    $start = microtime(true);
    for ($i = 0; $i < $iterations; $i++) {
        $wajhaDispatcher->dispatch($req['method'], $req['uri']);
    }
    $wajhaTime = microtime(true) - $start;
    $wajhaRps = $iterations / $wajhaTime;

    // Benchmark FastRoute
    gc_collect_cycles();
    $start = microtime(true);
    for ($i = 0; $i < $iterations; $i++) {
        $fastRouteDispatcher->dispatch($req['method'], $req['uri']);
    }
    $fastRouteTime = microtime(true) - $start;
    $fastRouteRps = $iterations / $fastRouteTime;

    $ratio = $fastRouteTime / $wajhaTime;

    printf(
        "%-18s | %12s/s | %12s/s | %8.2fx\n",
        $scenarioName,
        number_format($wajhaRps, 0, ',', '.'),
        number_format($fastRouteRps, 0, ',', '.'),
        $ratio,
    );
}

echo str_repeat("-", 70) . "\n";
