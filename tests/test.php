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

// ============================================================================
// 1. CONFIGURATION
// ============================================================================

$config = [
    'num_routes' => 1000,
    'num_requests' => 2000,
    'iterations' => 100000,
];

// ============================================================================
// 2. CORE HELPERS
// ============================================================================

function expandOptionalPaths(string $route): array
{
    if (!str_contains($route, '[')) {
        return [$route];
    }

    $routes = [];
    $expand = function (string $str) use (&$expand, &$routes) {
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

function generateRandomRoutes(int $count): array
{
    $methods = ['GET', 'POST', 'PUT', 'DELETE'];
    $staticWords = ['api', 'v1', 'user', 'product', 'order', 'settings', 'dashboard', 'billing', 'analytics', 'image', 'status'];
    $dynamicPatterns = ['{id:\d+}', '{slug:[a-z-]+}', '{uuid:[0-9a-f]{8}}'];

    $routes = [];
    $seenPatterns = [];

    while (count($routes) < $count) {
        $method = $methods[array_rand($methods)];
        $type = rand(0, 100);

        if ($type < 50) {
            $path = '/' . $staticWords[array_rand($staticWords)] . '/' . $staticWords[array_rand($staticWords)] . '/' . rand(100, 9999);
        } elseif ($type < 75) {
            $path = '/' . $staticWords[array_rand($staticWords)] . '/' . $dynamicPatterns[array_rand($dynamicPatterns)];
            if (rand(0, 1) === 1) {
                $path .= '/' . $staticWords[array_rand($staticWords)];
            }
        } elseif ($type < 88) {
            $path = '/' . $staticWords[array_rand($staticWords)] . '/file-' . $dynamicPatterns[0] . '.pdf';
        } else {
            $path = '/' . $staticWords[array_rand($staticWords)] . '/opt-' . rand(100, 999) . '[/' . $dynamicPatterns[0] . ']';
        }

        $normalized = preg_replace('~\{[a-zA-Z0-9_]+:([^}]+)\}~', '($1)', $path);
        $expandedPaths = expandOptionalPaths($normalized);

        $hasCollision = false;
        foreach ($expandedPaths as $p) {
            $key = $method . ':' . $p;
            if (isset($seenPatterns[$key])) {
                $hasCollision = true;
                break;
            }
        }

        if ($hasCollision) {
            continue;
        }

        foreach ($expandedPaths as $p) {
            $seenPatterns[$method . ':' . $p] = true;
        }

        $routes[] = [
            'method' => $method,
            'path' => $path,
            'handler' => "handler_id_" . count($routes),
        ];
    }

    return $routes;
}

function generateTestRequests(array $routes, int $count): array
{
    $requests = [];
    $methods = ['GET', 'POST', 'PUT', 'DELETE', 'HEAD'];

    for ($i = 0; $i < $count; $i++) {
        $type = rand(0, 100);

        if ($type < 70 && !empty($routes)) {
            $route = $routes[array_rand($routes)];
            $path = str_replace(['[', ']'], '', $route['path']);
            $path = str_replace(
                ['{id:\d+}', '{slug:[a-z-]+}', '{uuid:[0-9a-f]{8}}'],
                [(string) rand(1, 99999), 'sample-product-slug', 'deadbeef'],
                $path,
            );
            $requests[] = ['method' => $route['method'], 'uri' => $path];
        } elseif ($type < 85 && !empty($routes)) {
            $route = $routes[array_rand($routes)];
            $wrongMethod = $methods[array_rand($methods)];
            while ($wrongMethod === $route['method']) {
                $wrongMethod = $methods[array_rand($methods)];
            }
            $path = str_replace(['[', ']'], '', $route['path']);
            $path = str_replace(['{id:\d+}', '{slug:[a-z-]+}', '{uuid:[0-9a-f]{8}}'], ['123', 'test', 'abcdef12'], $path);

            $requests[] = ['method' => $wrongMethod, 'uri' => $path];
        } else {
            $requests[] = ['method' => $methods[array_rand($methods)], 'uri' => '/non/existent/route/path/' . rand(1, 100)];
        }
    }

    return $requests;
}

// ============================================================================
// 3. COMPILER FEATURE VERIFICATION
// ============================================================================

echo "=== FEATURE VALIDATION SUITE ===\n";
$featureCompiler = new WajhaCompiler();

// 1. Shorthands & Shortcuts
$featureCompiler->get('/users/{id:int}', 'UserShow');
$featureCompiler->get('/files/{id:uuid}', 'FileShow');

// 2. Groups
$featureCompiler->addGroup('/api/v1', function (WajhaCompiler $api) {
    $api->get('/products', 'ProductList');
});

$compiledFeatureData = $featureCompiler->compile();
$featureDispatcher = new WajhaDispatcher($compiledFeatureData);

assert($featureDispatcher->dispatch('GET', '/users/123')[0] === WajhaDispatcher::FOUND);
assert($featureDispatcher->dispatch('GET', '/api/v1/products')[0] === WajhaDispatcher::FOUND);
echo "SUCCESS: All compiler feature expansions validated.\n\n";

// ============================================================================
// 4. SETUP FIXTURES & REGISTER BUILT-IN ENGINE
// ============================================================================

echo "Generating {$config['num_routes']} route definitions...\n";
$routes = generateRandomRoutes($config['num_routes']);

echo "Generating {$config['num_requests']} request variations...\n\n";
$testRequests = generateTestRequests($routes, $config['num_requests']);

$registeredBenchmarks = [];

// Wajha Router
$registeredBenchmarks[] = [
    'name' => 'Safi/Wajha',
    'setup' => function (array $routes) {
        $compiler = new WajhaCompiler();
        foreach ($routes as $route) {
            $compiler->addRoute($route['method'], $route['path'], $route['handler']);
        }
        return new WajhaDispatcher($compiler->compile());
    },
    'dispatch' => function (WajhaDispatcher $dispatcher, array $req) {
        return $dispatcher->dispatch($req['method'], $req['uri']);
    },
];

// ============================================================================
// 5. LOAD BENCHMARK ADAPTERS
// ============================================================================

$benchFiles = glob(__DIR__ . '/benchmarks/*Bench.php') ?: [];
foreach ($benchFiles as $file) {
    if (file_exists($file)) {
        $benchConfig = require $file;
        if (is_array($benchConfig)) {
            $registeredBenchmarks[] = $benchConfig;
        }
    }
}

// ============================================================================
// 6. INTEGRITY SUITE
// ============================================================================

if (class_exists(\FastRoute\RouteCollector::class)) {
    echo "=== INTEGRITY SUITE (against FastRoute) ===\n";
    $fastRouteDispatcher = FastRoute\simpleDispatcher(function (FastRoute\RouteCollector $r) use ($routes) {
        foreach ($routes as $route) {
            $r->addRoute($route['method'], $route['path'], $route['handler']);
        }
    });

    $wajhaDispatcher = $registeredBenchmarks[0]['setup']($routes);
    $errors = 0;

    foreach ($testRequests as $req) {
        $frResult = $fastRouteDispatcher->dispatch($req['method'], $req['uri']);
        $wajhaResult = $wajhaDispatcher->dispatch($req['method'], $req['uri']);

        $frStatus = $frResult[0] === FastRoute\Dispatcher::METHOD_NOT_ALLOWED
            ? WajhaDispatcher::METHOD_NOT_ALLOWED
            : $frResult[0];

        if ($frStatus !== $wajhaResult[0]) {
            if ($req['method'] === 'HEAD' && $wajhaResult[0] === WajhaDispatcher::FOUND) {
                continue;
            }
            echo "FAIL: Status mismatch for [{$req['method']}] {$req['uri']}: FastRoute={$frStatus}, Wajha={$wajhaResult[0]}\n";
            $errors++;
            continue;
        }

        if ($frResult[0] === FastRoute\Dispatcher::FOUND && $frResult[1] !== $wajhaResult[1]) {
            echo "FAIL: Handler mismatch for [{$req['method']}] {$req['uri']}: FastRoute='{$frResult[1]}', Wajha='{$wajhaResult[1]}'\n";
            $errors++;
        }
    }

    if ($errors === 0) {
        echo "SUCCESS: Safi/Wajha integrity evaluation matches FastRoute outputs with 100% accuracy.\n\n";
    } else {
        echo "WARNING: {$errors} structural mismatches identified. Benchmark aborted.\n";
        exit(1);
    }
} else {
    echo "INFO: FastRoute package not detected. Skipping integrity cross-check.\n\n";
}

// ============================================================================
// 7. BENCHMARK EXECUTION
// ============================================================================

echo "=== PERFORMANCE SUITE ({$config['iterations']} Iterations) ===\n";

$results = [];

foreach ($registeredBenchmarks as $bench) {
    gc_collect_cycles();
    $instance = $bench['setup']($routes);

    // Warmup
    for ($i = 0; $i < 100; $i++) {
        $req = $testRequests[$i % count($testRequests)];
        $bench['dispatch']($instance, $req);
    }

    $start = microtime(true);
    for ($i = 0; $i < $config['iterations']; $i++) {
        $req = $testRequests[$i % count($testRequests)];
        $bench['dispatch']($instance, $req);
    }
    $totalTime = microtime(true) - $start;

    $results[] = [
        'name' => $bench['name'],
        'time' => $totalTime,
        'rps' => $config['iterations'] / $totalTime,
        'avg_latency' => ($totalTime / $config['iterations']) * 1000000,
    ];
}

// Display results
printf("%-20s | %-14s | %-14s\n", "Engine", "Throughput", "Avg Latency");
echo str_repeat("-", 54) . "\n";

foreach ($results as $res) {
    printf(
        "%-20s | %10s/s | %11.3f µs\n",
        $res['name'],
        number_format($res['rps'], 0, ',', '.'),
        $res['avg_latency'],
    );
}

echo str_repeat("-", 54) . "\n";

// Relative execution ratios
$wajhaTime = $results[0]['time'];
if (count($results) > 1) {
    echo "Execution Ratio Relative to Safi/Wajha:\n";
    for ($i = 1; $i < count($results); $i++) {
        printf("  vs. %-16s %.2fx faster\n", $results[$i]['name'] . ':', $results[$i]['time'] / $wajhaTime);
    }
}
