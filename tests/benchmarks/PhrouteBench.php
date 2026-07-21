<?php

// Installation: composer require --dev phroute/phroute

declare(strict_types=1);

use Phroute\Phroute\RouteCollector;
use Phroute\Phroute\Dispatcher;

if (!class_exists(RouteCollector::class)) {
    return null;
}

return [
    'name' => 'Phroute',
    'setup' => function (array $routes) {
        $collector = new RouteCollector();
        foreach ($routes as $route) {
            foreach (expandOptionalPaths($route['path']) as $p) {
                $collector->addRoute($route['method'], $p, $route['handler']);
            }
        }
        return new Dispatcher($collector->getData());
    },
    'dispatch' => function (Dispatcher $dispatcher, array $req) {
        try {
            $dispatcher->dispatch($req['method'], $req['uri']);
        } catch (\Throwable $e) {}
    }
];
