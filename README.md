# Safi/Wajha Router

Hybrid HTTP router for PHP 8.5+ combining an $O(1)$ static lookup table with first-character bucketed PCRE2 chunked regex evaluation for dynamic routes.

---

## Technical Features

## Technical Features

* **O(1) Static Lookup:** Direct 2D array hashmap access (`$staticRoutes[$method][$uri]`) bypassing the regex engine entirely.
* **First-Character Bucketing:** Dynamic routes are partitioned by path prefix to eliminate scanning unrelated route trees.
* **PCRE2 Chunked Matching:** Dynamic paths evaluate in 30-route chunks via native `(?J)` duplicate groups and `(*MARK:N)` identifiers inside C-level PCRE2.
* **Zero-Recursion Architecture:** Flat execution flow without function frame stack overhead during dispatch.
* **Compile-Time Transformations:** Type shorthands, enum constraints, and route groups compile down to raw PCRE2 patterns with zero runtime overhead.

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

$result = $dispatcher->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);

switch ($result[0]) {
    case WajhaDispatcher::FOUND:
        $handler = $result[1];
        $vars = $result[2];
        break;

    case WajhaDispatcher::METHOD_NOT_ALLOWED:
        $allowedMethods = $result[2];
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
// Compiles to: /users/(?<id>\d+)
$compiler->get('/users/{id:int}', 'UserController@show');

// Compiles to RFC 4122 UUID pattern
$compiler->get('/files/{id:uuid}', 'FileController@show');

// Compiles to: /posts/(?<slug>[a-z0-9-]+)
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

Evaluated over 100,000 request dispatch iterations against a 1,000 route dataset (70% valid routes, 15% method mismatches, 15% 404 targets).

| Engine | Throughput | Avg Latency | Speed Ratio |
| :--- | :--- | :--- | :--- |
| **Safi/Wajha** | **333,448 req/s** | **2.999 µs** | **Baseline (1.00x)** |
| **nikic/FastRoute** | 115,068 req/s | 8.691 µs | 2.90x slower |
| **Phroute** | 111,659 req/s | 8.956 µs | 2.99x slower |
| **Symfony Routing** | 102,480 req/s | 9.758 µs | 3.25x slower |
| **AltoRouter** | 4,170 req/s | 239.801 µs | 79.96x slower |

For more background on the benchmark verification, trade-offs, and implementation details, see the write-up: [Writing a PHP 8.5 Router Faster Than FastRoute](https://blog.jeanbruenn.info/2026/07/21/writing-a-php-8-5-router-faster-than-fastroute/)

---

## Running Benchmarks

```bash
composer require --dev nikic/fast-route symfony/routing phroute/phroute altorouter/altorouter
php tests/test.php
```

---

## License

MIT License. See [LICENSE.md](LICENSE.md) for details.
