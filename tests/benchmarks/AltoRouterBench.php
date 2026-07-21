<?php

// Installation: composer require --dev altorouter/altorouter

declare(strict_types=1);

if (!class_exists(\AltoRouter::class)) {
    return null;
}

return [
    'name' => 'AltoRouter',
    'setup' => function (array $routes) {
        $router = new AltoRouter();
        foreach ($routes as $route) {
            foreach (expandOptionalPaths($route['path']) as $p) {
                $altoPath = preg_replace(
                    ['~\{([a-zA-Z0-9_]+):\d+\}~', '~\{([a-zA-Z0-9_]+):[a-z-]+\}~', '~\{([a-zA-Z0-9_]+):[0-9a-f]\{8\}\}~'],
                    ['[i:$1]', '[a:$1]', '[h:$1]'],
                    $p
                );
                $router->map($route['method'], $altoPath, $route['handler']);
            }
        }
        return $router;
    },
    'dispatch' => function (\AltoRouter $router, array $req) {
        $router->match($req['uri'], $req['method']);
    }
];
