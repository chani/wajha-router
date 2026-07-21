# Safi\Wajha

`Safi\Wajha` is a lightweight, high-throughput HTTP router for PHP 8.5+. It combines an $O(1)$ static route lookup table with a first-character radix jump tree using native PCRE2 `\G` pointer offsets for dynamic routes.

## Highlights

* **FastRoute Syntax Compatible:** Native support for FastRoute syntax (`{id:\d+}`), nested regex quantifiers (`{uuid:[0-9a-f]{8}}`), partial dynamic segments (`file-{id}.pdf`), and optional route brackets (`[/...]`).
* **Hybrid Routing Engine:** $O(1)$ static route lookup table combined with a first-character radix jump tree.
* **Zero-Allocation Hot Path:** Evaluates dynamic route segments directly on URI memory offsets via PCRE2 `\G` anchors without heap allocations.

## Motivation & Status

`Safi\Wajha` was built for projects that require FastRoute-compatible syntax with lower dispatch latency and zero memory allocations during dynamic route evaluation.

* **Compatibility:** Tested against `nikic/fast-route` behavior across static, dynamic, optional, and RFC 9110 edge cases.
* **Scope:** Designed specifically for PHP 8.5+ as the core router for the Safi framework.

## Benchmarks

Tested with 1,000 route definitions and 2,000 randomized request variations (50% static, 38% dynamic/partial, 12% optional/RFC fallbacks) over 100,000 iterations:

| Engine | Throughput | Avg Latency | Speed Ratio |
| :--- | :--- | :--- | :--- |
| **Safi/Wajha** | **202,129 req/s** | **4.947 µs** | **Baseline (1.00x)** |
| **nikic/FastRoute** | 110,620 req/s | 9.040 µs | 1.83x slower |
| **Phroute** | 110,109 req/s | 9.082 µs | 1.84x slower |
| **Symfony Routing** | 95,977 req/s | 10.419 µs | 2.11x slower |
| **AltoRouter** | 4,133 req/s | 241.939 µs | 48.90x slower |

## Quick Start

```php
use Safi\Wajha\WajhaCompiler;
use Safi\Wajha\WajhaDispatcher;

$compiler = new WajhaCompiler();

// Register routes
$compiler->addRoute('GET', '/users', 'UserController@index');
$compiler->addRoute('GET', '/users/{id:\d+}', 'UserController@show');
$compiler->addRoute('GET', '/downloads/file-{id:\d+}.pdf', 'DownloadController@file');
$compiler->addRoute('GET', '/settings[/{section}]', 'SettingsController@index');

// Compile route tree
$compiledData = $compiler->compile();

// Dispatch incoming request
$dispatcher = new WajhaDispatcher($compiledData);
$result = $dispatcher->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);

switch ($result[0]) {
    case WajhaDispatcher::FOUND:
        $handler = $result[1];
        $vars = $result[2];
        // Handle request
        break;

    case WajhaDispatcher::METHOD_NOT_ALLOWED:
        $allowedMethods = $result[2];
        header('Allow: ' . implode(', ', $allowedMethods));
        http_response_code(405);
        break;

    case WajhaDispatcher::NOT_FOUND:
        http_response_code(404);
        break;
}```
