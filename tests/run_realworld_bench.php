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

// Helper for benchmark adapters that expand optional bracket routes
if (!function_exists('expandOptionalPaths')) {
    /**
     * @return list<string>
     */
    function expandOptionalPaths(string $route): array
    {
        if (!str_contains($route, '[')) {
            return [$route];
        }

        $routes = [];
        $expand = static function (string $str) use (&$expand, &$routes): void {
            $len = strlen($str);
            $inParam = 0;
            $optStart = -1;
            $optEnd = -1;
            $optDepth = 0;

            for ($i = 0; $i < $len; $i++) {
                $char = $str[$i];
                if ($char === '{') {
                    $inParam++;
                } elseif ($char === '}' && $inParam > 0) {
                    $inParam--;
                } elseif ($inParam === 0) {
                    if ($char === '[') {
                        if ($optDepth === 0) {
                            $optStart = $i;
                        }
                        $optDepth++;
                    } elseif ($char === ']' && $optDepth > 0) {
                        $optDepth--;
                        if ($optDepth === 0) {
                            $optEnd = $i;
                            break;
                        }
                    }
                }
            }

            if ($optStart !== -1 && $optEnd !== -1) {
                $before = substr($str, 0, $optStart);
                $inside = substr($str, $optStart + 1, $optEnd - $optStart - 1);
                $after = substr($str, $optEnd + 1);

                $expand($before . $after);
                $expand($before . $inside . $after);
            } else {
                $routes[] = $str;
            }
        };

        $expand($route);
        return array_values(array_unique($routes));
    }
}

/** @var array{routes: list<array{method: string, path: string, handler: mixed}>, requests: array<string, array{method: string, uri: string}>} $benchData */
$benchData = require __DIR__ . '/benchmarks/RealWorldBench.php';
$routes = $benchData['routes'];
$requests = $benchData['requests'];
$iterations = 100000;

// 1. Register Wajha Benchmark
/** @var list<array{name: string, setup: callable(list<array{method: string, path: string, handler: mixed}>): mixed, dispatch: callable(mixed, array{method: string, uri: string}): mixed}> $registeredBenchmarks */
$registeredBenchmarks = [
    [
        'name' => 'Wajha',
        'setup' => static function (array $routes): WajhaDispatcher {
            $compiler = new WajhaCompiler();
            foreach ($routes as $route) {
                $compiler->addRoute($route['method'], $route['path'], $route['handler']);
            }
            return new WajhaDispatcher($compiler->compile());
        },
        'dispatch' => static function (mixed $dispatcher, array $req): mixed {
            /** @var WajhaDispatcher $dispatcher */
            return $dispatcher->dispatch($req['method'], $req['uri']);
        },
    ],
];

// 2. Load all available benchmark adapters dynamically
$benchFiles = glob(__DIR__ . '/benchmarks/*Bench.php') ?: [];
foreach ($benchFiles as $file) {
    if (basename($file) === 'RealWorldBench.php') {
        continue;
    }
    if (file_exists($file)) {
        /** @var array{name: string, setup: callable(list<array{method: string, path: string, handler: mixed}>): mixed, dispatch: callable(mixed, array{method: string, uri: string}): mixed}|mixed $benchConfig */
        $benchConfig = require $file;
        if (is_array($benchConfig) && isset($benchConfig['setup'], $benchConfig['dispatch'])) {
            $registeredBenchmarks[] = $benchConfig;
        }
    }
}

// 3. Instantiate Engine Instances
/** @var array<string, array{instance: mixed, dispatch: callable(mixed, array{method: string, uri: string}): mixed}> $engineInstances */
$engineInstances = [];
foreach ($registeredBenchmarks as $bench) {
    $engineInstances[$bench['name']] = [
        'instance' => $bench['setup']($routes),
        'dispatch' => $bench['dispatch'],
    ];
}

$engineNames = array_keys($engineInstances);

echo "=== REAL-WORLD DATASET BENCHMARK ({$iterations} Iterations per Scenario) ===\n\n";

// Table Header
printf("%-28s", "Scenario");
foreach ($engineNames as $name) {
    printf(" | %-15s", substr($name, 0, 15) . " (ops)");
}
echo "\n" . str_repeat("-", 28 + count($engineNames) * 18) . "\n";

// Run Scenarios
foreach ($requests as $scenarioName => $req) {
    // Warmup
    foreach ($engineInstances as $engine) {
        for ($i = 0; $i < 100; $i++) {
            try {
                $engine['dispatch']($engine['instance'], $req);
            } catch (\Throwable) {
                // Catch route not found or method not allowed exceptions from other engines
            }
        }
    }

    /** @var array<string, float> $rowResults */
    $rowResults = [];

    foreach ($engineInstances as $name => $engine) {
        gc_collect_cycles();
        $start = microtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            try {
                $engine['dispatch']($engine['instance'], $req);
            } catch (\Throwable) {
                // Catch exceptions during 404 / 405 benchmark runs
            }
        }
        $time = microtime(true) - $start;
        $rowResults[$name] = $time > 0 ? ($iterations / $time) : 0;
    }

    printf("%-28s", $scenarioName);
    foreach ($engineNames as $name) {
        printf(" | %15s", number_format($rowResults[$name], 0, ',', '.'));
    }
    echo "\n";
}

echo str_repeat("-", 28 + count($engineNames) * 18) . "\n";
