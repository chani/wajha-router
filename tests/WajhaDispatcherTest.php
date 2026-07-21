<?php

declare(strict_types=1);

namespace Safi\Wajha\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Safi\Wajha\WajhaCompiler;
use Safi\Wajha\WajhaDispatcher;

enum TestStatus: string
{
    case Active = 'active';
    case Pending = 'pending';
}

final class WajhaDispatcherTest extends TestCase
{
    private function createDispatcher(callable $callback): WajhaDispatcher
    {
        $compiler = new WajhaCompiler();
        $callback($compiler);
        return new WajhaDispatcher($compiler->compile());
    }

    // =========================================================================
    // 1. FOUND DISPATCH CASES (FastRoute Suite + Wajha Features)
    // =========================================================================


    /**
     * @param array<string, string> $expectedVars
     */
    #[DataProvider('provideFoundDispatchCases')]
    public function testFoundDispatches(
        string $method,
        string $uri,
        callable $callback,
        mixed $expectedHandler,
        array $expectedVars = []
    ): void {
        $dispatcher = $this->createDispatcher($callback);
        $result = $dispatcher->dispatch($method, $uri);

        $this->assertSame(WajhaDispatcher::FOUND, $result[0], "Route should be FOUND for [$method] $uri");
        $this->assertSame($expectedHandler, $result[1], "Handler mismatch for [$method] $uri");
        $this->assertSame($expectedVars, $result[2], "Extracted variables mismatch for [$method] $uri");
    }

    /**
     * @return iterable<string, array{0: string, 1: string, 2: mixed, 3?: array<string, string>}>
     */
    public static function provideFoundDispatchCases(): iterable
    {
        // --- Static Routes ---
        yield 'single static route' => [
            'GET', '/resource/123/456',
            function (WajhaCompiler $r) { $r->get('/resource/123/456', 'handler0'); },
            'handler0'
        ];

        yield 'multiple static routes' => [
            'GET', '/handler2',
            function (WajhaCompiler $r) {
                $r->get('/handler0', 'handler0');
                $r->get('/handler1', 'handler1');
                $r->get('/handler2', 'handler2');
            },
            'handler2'
        ];

        // --- Precedence & Matching Order ---
        $precedenceCallback = function (WajhaCompiler $r) {
            $r->get('/user/{name}/{id:\d+}', 'handler0');
            $r->get('/user/{id:\d+}', 'handler1');
            $r->get('/user/{name}', 'handler2');
        };

        yield 'parameter matching precedence: full match' => [
            'GET', '/user/jean/12345', $precedenceCallback, 'handler0', ['name' => 'jean', 'id' => '12345']
        ];
        yield 'parameter matching precedence: int match' => [
            'GET', '/user/12345', $precedenceCallback, 'handler1', ['id' => '12345']
        ];
        yield 'parameter matching precedence: string match' => [
            'GET', '/user/jean', $precedenceCallback, 'handler2', ['name' => 'jean']
        ];

        // --- File Extensions & Suffixes ---
        yield 'dynamic file extensions' => [
            'GET', '/user/12345.svg',
            function (WajhaCompiler $r) {
                $r->get('/user/{id:\d+}', 'handler0');
                $r->get('/user/{id:\d+}.{extension}', 'handler2');
            },
            'handler2', ['id' => '12345', 'extension' => 'svg']
        ];

        yield 'constant suffix' => [
            'GET', '/user/jean/edit',
            function (WajhaCompiler $r) {
                $r->get('/user/{name}', 'handler0');
                $r->get('/user/{name}/edit', 'handler1');
            },
            'handler1', ['name' => 'jean']
        ];

        // --- HEAD Request Fallbacks (RFC 9110) ---
        $headCallback = function (WajhaCompiler $r) {
            $r->get('/user/{name}', 'handler0');
            $r->get('/static0', 'handler1');
            $r->head('/static1', 'handler2');
        };

        yield 'fallback to GET on HEAD route miss (dynamic)' => [
            'HEAD', '/user/jean', $headCallback, 'handler0', ['name' => 'jean']
        ];
        yield 'fallback to GET on HEAD route miss (static)' => [
            'HEAD', '/static0', $headCallback, 'handler1'
        ];
        yield 'explicit HEAD route is preferred' => [
            'HEAD', '/static1', $headCallback, 'handler2'
        ];

        // --- Optional Segments ---
        yield 'optional segments expansion (base route)' => [
            'GET', '/user',
            function (WajhaCompiler $r) { $r->get('/user[/{id:int}[/{name}]]', 'handler0'); },
            'handler0'
        ];
        yield 'optional segments expansion (first level)' => [
            'GET', '/user/42',
            function (WajhaCompiler $r) { $r->get('/user[/{id:int}[/{name}]]', 'handler0'); },
            'handler0', ['id' => '42']
        ];
        yield 'optional segments expansion (second level)' => [
            'GET', '/user/42/jean',
            function (WajhaCompiler $r) { $r->get('/user[/{id:int}[/{name}]]', 'handler0'); },
            'handler0', ['id' => '42', 'name' => 'jean']
        ];

        // --- Wajha Specifics: Shorthands ---
        yield 'wajha shorthand :int' => [
            'GET', '/item/99',
            function (WajhaCompiler $r) { $r->get('/item/{id:int}', 'handlerInt'); },
            'handlerInt', ['id' => '99']
        ];
        yield 'wajha shorthand :uuid' => [
            'GET', '/item/a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11',
            function (WajhaCompiler $r) { $r->get('/item/{id:uuid}', 'handlerUuid'); },
            'handlerUuid', ['id' => 'a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11']
        ];
        yield 'wajha shorthand :slug' => [
            'GET', '/post/my-awesome-post',
            function (WajhaCompiler $r) { $r->get('/post/{title:slug}', 'handlerSlug'); },
            'handlerSlug', ['title' => 'my-awesome-post']
        ];

        // --- Wajha Specifics: Backed Enums ---
        yield 'wajha enum constraint' => [
            'GET', '/status/active',
            function (WajhaCompiler $r) { $r->get('/status/{st:' . TestStatus::class . '}', 'handlerEnum'); },
            'handlerEnum', ['st' => 'active']
        ];
    }

    // =========================================================================
    // 2. NOT FOUND DISPATCH CASES
    // =========================================================================

    #[DataProvider('provideNotFoundDispatchCases')]
    public function testNotFoundDispatches(string $method, string $uri, callable $callback): void
    {
        $dispatcher = $this->createDispatcher($callback);
        $result = $dispatcher->dispatch($method, $uri);

        $this->assertSame(WajhaDispatcher::NOT_FOUND, $result[0]);
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function provideNotFoundDispatchCases(): iterable
    {
        $callback = function (WajhaCompiler $r) {
            $r->get('/user/{name}/{id:\d+}', 'handler0');
            $r->get('/static-route', 'handler1');
            $r->get('/status/{st:' . TestStatus::class . '}', 'handlerEnum');
        };

        yield 'completely unknown route' => ['GET', '/unknown', $callback];
        yield 'partially matching static prefix' => ['GET', '/static-route-extra', $callback];
        yield 'invalid enum value' => ['GET', '/status/archived', $callback];
        yield 'type shorthand mismatch (string instead of int)' => ['GET', '/user/jean/not-an-int', $callback];
    }

    // =========================================================================
    // 3. METHOD NOT ALLOWED DISPATCH CASES (405 & Allow Header)
    // =========================================================================

    #[DataProvider('provideMethodNotAllowedDispatchCases')]
    public function testMethodNotAllowedDispatches(
        string $method,
        string $uri,
        callable $callback,
        array $expectedAllowedMethods
    ): void {
        $dispatcher = $this->createDispatcher($callback);
        $result = $dispatcher->dispatch($method, $uri);

        $this->assertSame(WajhaDispatcher::METHOD_NOT_ALLOWED, $result[0]);
        
        $allowed = $result[1];
        sort($allowed);
        sort($expectedAllowedMethods);

        $this->assertSame($expectedAllowedMethods, $allowed, "Allowed methods mismatch for [$method] $uri");
    }

    /**
     * @return iterable<string, array{0: string, 1: string, 2: list<string>}>
     */
    public static function provideMethodNotAllowedDispatchCases(): iterable
    {
        yield 'static route wrong method' => [
            'POST', '/resource',
            function (WajhaCompiler $r) { $r->get('/resource', 'handler0'); },
            ['GET', 'HEAD'] // HEAD wird automatisch zu GET erlaubt
        ];

        yield 'static route multiple methods allowed' => [
            'DELETE', '/resource',
            function (WajhaCompiler $r) {
                $r->get('/resource', 'handler0');
                $r->post('/resource', 'handler1');
                $r->put('/resource', 'handler2');
            },
            ['GET', 'HEAD', 'POST', 'PUT']
        ];

        yield 'dynamic route wrong method' => [
            'DELETE', '/user/jean/42',
            function (WajhaCompiler $r) {
                $r->get('/user/{name}/{id:\d+}', 'handler0');
                $r->post('/user/{name}/{id:\d+}', 'handler1');
            },
            ['GET', 'HEAD', 'POST']
        ];
    }
}
