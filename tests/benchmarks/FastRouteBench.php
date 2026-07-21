<?php

// Installation: composer require --dev nikic/fast-route

declare(strict_types=1);

if (!class_exists(\FastRoute\RouteCollector::class)) {
    return null;
}

return [
    'name' => 'nikic/FastRoute',
    'setup' => function (array $routes) {
        return FastRoute\simpleDispatcher(function (FastRoute\RouteCollector $r) use ($routes) {
            foreach ($routes as $route) {
                $r->addRoute($route['method'], $route['path'], $route['handler']);
            }
        });
    },
    'dispatch' => function ($dispatcher, array $req) {
        return $dispatcher->dispatch($req['method'], $req['uri']);
    }
];
