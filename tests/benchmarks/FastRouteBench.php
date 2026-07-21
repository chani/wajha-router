<?php
/**
 * Safi/Wajha Router
 * @author Jean Bruenn
 * @copyright 2026 All Rights Reserved
 * @see https://github.com/chani/wajha-router
 * @see https://packagist.org/packages/chani/wajha
 */

declare(strict_types=1);

if (!class_exists(\FastRoute\RouteCollector::class)) {
    return null;
}

return [
    'name' => 'nikic/FastRoute',
    'setup' => function (array $routes) {
	/** @var list<array{method: string, path: string, handler: mixed}> $routes */
        return FastRoute\simpleDispatcher(function (FastRoute\RouteCollector $r) use ($routes) {
            foreach ($routes as $route) {
                $r->addRoute($route['method'], $route['path'], $route['handler']);
            }
        });
    },
    'dispatch' => function ($dispatcher, array $req) {
        return $dispatcher->dispatch($req['method'], $req['uri']);
    },
];
