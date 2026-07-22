# Safi/Wajha Router

A fast, lightweight HTTP router for PHP 8.5+ combining $O(1)$ static hash map lookups with first-character bucketed PCRE2 regex evaluation for dynamic routes.

---

## Technical Features

* **O(1) Static Fast-Path:** Direct 2D array lookup (`$staticRoutes[$method][$uri]`) bypassing the PCRE2 engine entirely.
* **First-Character Path Bucketing:** Dynamic routes are partitioned by URI prefix to avoid evaluating unrelated regex chunks.
* **PCRE2 Positional Branch-Reset Matching:** Dynamic paths are evaluated in 30-route chunks using PCRE2 branch-reset groups `(?|...)` with positional capture extraction (`$matches[1]`) and `(*MARK:N)` identifiers to eliminate C-level named subpattern lookup overhead.
* **Zero-Stack Dispatching:** Inlined execution path without VM function frame call stack overhead during dispatching.
* **Compile-Time Transformations:** Type shorthands, enum constraints, and route groups compile down to raw PCRE2 patterns with no runtime overhead.

---

## Requirements

* PHP 8.5 or higher
* `pcre2` extension enabled

---

## Installation

```bash
composer require chani/wajha
```

---

## Usage Examples

### 1. Basic Setup & Dispatching

Routes are registered via `WajhaCompiler` and evaluated via `WajhaDispatcher`.

```php
use Safi\Wajha\WajhaCompiler;
use Safi\Wajha\WajhaDispatcher;

$compiler = new WajhaCompiler();

$compiler->get('/users', 'UserController@index');
$compiler->post('/users', 'UserController@store');

$compiledData = $compiler->compile();
$dispatcher = new WajhaDispatcher($compiledData);

// HTTP Method MUST be uppercase
$result = $dispatcher->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);

switch ($result[0]) {
    case WajhaDispatcher::FOUND:
        $handler = $result[1];
        $vars = $result[2];
        break;

    case WajhaDispatcher::METHOD_NOT_ALLOWED:
        $allowedMethods = $result[1]; // Array of allowed HTTP methods
        break;

    case WajhaDispatcher::NOT_FOUND:
        http_response_code(404);
        break;
}
```

---

### 2. Type Shorthands

Pattern aliases expand during compilation into native PCRE2 patterns.

```php
// Compiles to: /users/(\d+)
$compiler->get('/users/{id:int}', 'UserController@show');

// Compiles to RFC 4122 UUID pattern
$compiler->get('/files/{id:uuid}', 'FileController@show');

// Compiles to: /posts/([a-z0-9-]+)
$compiler->get('/posts/{slug:slug}', 'PostController@show');
```

Supported shorthands:
* `{var:int}` $\rightarrow$ `\d+`
* `{var:uuid}` $\rightarrow$ `[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}`
* `{var:slug}` $\rightarrow$ `[a-z0-9-]+`
* `{var:alpha}` $\rightarrow$ `[a-zA-Z]+`

---

### 3. Backed Enum Matching

String-backed PHP 8.1+ Enums translate into regex alternation groups (`case1|case2`) at compile time.

```php
enum OrderStatus: string {
    case Pending = 'pending';
    case Paid = 'paid';
    case Shipped = 'shipped';
}

// Compiled regex constraint: (pending|paid|shipped)
$compiler->get('/orders/{status:' . OrderStatus::class . '}', 'OrderController@index');
```

---

### 4. Route Grouping

Prefix concatenation occurs strictly during compilation.

```php
$compiler->addGroup('/api/v1', function (WajhaCompiler $api) {
    // Path: /api/v1/products
    $api->get('/products', 'ProductController@index');

    // Path: /api/v1/admin/stats
    $api->addGroup('/admin', function (WajhaCompiler $admin) {
        $admin->get('/stats', 'AdminController@stats');
    });
});
```

---

### 5. Optional Path Segments

Optional segments marked with square brackets (`[/...]`) expand recursively at compile time.

```php
// Compiles into two discrete routes: /archive and /archive/{year:\d+}
$compiler->get('/archive[/{year:int}]', 'ArchiveController@index');
```

---

## Benchmarks

Evaluated on PHP 8.5 over 100,000 request dispatch iterations against a 1,000 route dataset (70% valid routes, 15% method mismatches, 15% 404 targets):

| Engine | Throughput | Avg Latency | Speed Ratio |
| :--- | :--- | :--- | :--- |
| **Safi/Wajha** | **446,189 req/s** | **2.241 µs** | **Baseline (1.00x)** |
| **nikic/FastRoute** | 118,841 req/s | 8.415 µs | 3.75x slower |
| **Phroute** | 115,164 req/s | 8.683 µs | 3.87x slower |
| **Symfony Routing** | 103,832 req/s | 9.631 µs | 4.30x slower |
| **AltoRouter** | 4,063 req/s | 246.140 µs | 109.82x slower |

For detailed insights into the architecture, Zend VM micro-benchmarks, and real-world scenario trade-offs, see the write-up: [Writing a PHP 8.5 Router Faster Than FastRoute](https://blog.jeanbruenn.info/2026/07/21/writing-a-php-8-5-router-faster-than-fastroute/)

---

## Running Benchmarks & Tests

```bash
# Install development dependencies
composer require --dev

# Run PHPUnit test suite
./vendor/bin/phpunit tests

# Run benchmark suites
php tests/test.php
php tests/run_realworld_bench.php
```

---

## License

MIT License. See [LICENSE.md](LICENSE.md) for details.
