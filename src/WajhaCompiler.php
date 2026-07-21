<?php

declare(strict_types=1);

namespace Safi\Wajha;

use BackedEnum;

class WajhaCompiler
{
    private array $staticRoutes = [];
    private array $dynamicRoutes = [];
    private string $currentGroupPrefix = '';

    /**
     * Common type shorthand expansions
     */
    private const array SHORTHANDS = [
        ':int}'   => ':\d+}',
        ':uuid}'  => ':[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}}',
        ':slug}'  => ':[a-z0-9-]+}',
        ':alpha}' => ':[a-zA-Z]+}',
    ];

    public function addGroup(string $prefix, callable $callback): void
    {
        $previousPrefix = $this->currentGroupPrefix;
        $this->currentGroupPrefix = $previousPrefix . '/' . trim($prefix, '/');

        $callback($this);

        $this->currentGroupPrefix = $previousPrefix;
    }

    public function get(string $path, mixed $handler): void
    {
        $this->addRoute('GET', $path, $handler);
    }
    public function post(string $path, mixed $handler): void
    {
        $this->addRoute('POST', $path, $handler);
    }
    public function put(string $path, mixed $handler): void
    {
        $this->addRoute('PUT', $path, $handler);
    }
    public function delete(string $path, mixed $handler): void
    {
        $this->addRoute('DELETE', $path, $handler);
    }
    public function patch(string $path, mixed $handler): void
    {
        $this->addRoute('PATCH', $path, $handler);
    }
    public function head(string $path, mixed $handler): void
    {
        $this->addRoute('HEAD', $path, $handler);
    }

    public function addRoute(string $method, string $path, mixed $handler): void
    {
        $method = strtoupper($method);

        if ($this->currentGroupPrefix !== '') {
            $path = $this->currentGroupPrefix . '/' . ltrim($path, '/');
        }

        $path = $this->resolvePathShorthandsAndEnums($path);
        $expandedPaths = $this->expandOptionalPaths($path);

        foreach ($expandedPaths as $expandedPath) {
            $normalizedPath = '/' . ltrim($expandedPath, '/');

            if (!str_contains($normalizedPath, '{')) {
                $this->staticRoutes[$method][$normalizedPath] = $handler;
            } else {
                $this->registerDynamicRoute($method, $normalizedPath, $handler);
            }
        }
    }

    private function resolvePathShorthandsAndEnums(string $path): string
    {
        $path = strtr($path, self::SHORTHANDS);

        return (string) preg_replace_callback('~\{([a-zA-Z0-9_]+):([\\\\a-zA-Z0-9_]+)\}~', function (array $matches): string {
            $paramName = $matches[1];
            $class = $matches[2];

            if (enum_exists($class) && is_subclass_of($class, BackedEnum::class)) {
                $cases = array_map(
                    static fn (BackedEnum $case): string => preg_quote((string) $case->value, '~'),
                    $class::cases()
                );
                return '{' . $paramName . ':' . implode('|', $cases) . '}';
            }

            return $matches[0];
        }, $path);
    }

    private function expandOptionalPaths(string $route): array
    {
        if (!str_contains($route, '[')) {
            return [$route];
        }

        $routes = [];
        $expand = function (string $str) use (&$expand, &$routes): void {
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

    private function registerDynamicRoute(string $method, string $path, mixed $handler): void
    {
        $pattern = $this->parseRoutePattern($path);

        $firstChar = $path[1] ?? '/';
        if ($firstChar === '{') {
            $firstChar = '*';
        }

        $this->dynamicRoutes[$method][$firstChar][] = [
            'pattern' => $pattern,
            'handler' => $handler,
        ];
    }

    private function parseRoutePattern(string $path): string
    {
        $length = strlen($path);
        $regex = '';
        $i = 0;

        while ($i < $length) {
            if ($path[$i] === '{') {
                $i++;
                $paramStart = $i;
                $braceDepth = 1;

                while ($i < $length && $braceDepth > 0) {
                    if ($path[$i] === '{') {
                        $braceDepth++;
                    } elseif ($path[$i] === '}') {
                        $braceDepth--;
                    }
                    if ($braceDepth > 0) {
                        $i++;
                    }
                }

                $paramContent = substr($path, $paramStart, $i - $paramStart);
                $i++;

                $colonPos = strpos($paramContent, ':');
                if ($colonPos !== false) {
                    $varName = substr($paramContent, 0, $colonPos);
                    $varRegex = substr($paramContent, $colonPos + 1);
                } else {
                    $varName = $paramContent;
                    $varRegex = '[^/]+';
                }

                $regex .= '(?<' . $varName . '>' . $varRegex . ')';
            } else {
                $char = $path[$i];
                $regex .= preg_quote($char, '~');
                $i++;
            }
        }

        return $regex;
    }

    public function compile(): array
    {
        $compiledDynamic = [];

        foreach ($this->dynamicRoutes as $method => $charGroups) {
            foreach ($charGroups as $firstChar => $routes) {
                $chunks = array_chunk($routes, 30);
                foreach ($chunks as $chunk) {
                    $patterns = [];
                    $handlers = [];

                    foreach ($chunk as $idx => $route) {
                        $patterns[] = $route['pattern'] . '(*MARK:' . $idx . ')';
                        $handlers[$idx] = $route['handler'];
                    }

                    $compiledDynamic[$method][$firstChar][] = [
                        'regex'    => '~(?J)^(?:' . implode('|', $patterns) . ')$~u',
                        'handlers' => $handlers,
                    ];
                }
            }
        }

        return [
            'static'  => $this->staticRoutes,
            'dynamic' => $compiledDynamic,
        ];
    }
}
