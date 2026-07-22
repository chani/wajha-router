<?php

/**
 * Safi/Wajha Router
 * @author Jean Bruenn
 * @copyright 2026 All Rights Reserved
 * @see https://github.com/chani/wajha-router
 * @see https://packagist.org/packages/chani/wajha
 */

declare(strict_types=1);

namespace Safi\Wajha;

use BackedEnum;

class WajhaCompiler
{
    /** @var array<string, array<string, mixed>> */
    private array $staticRoutes = [];

    /** @var array<string, array<string, list<array{pattern: string, vars: list<string>, handler: mixed}>>> */
    private array $dynamicRoutes = [];

    /** @var array<string, array{template: string, vars: list<string>}> */
    private array $namedRoutes = [];

    private string $currentGroupPrefix = '';

    /**
     * Common type shorthand expansions
     * @var array<string, string>
     */
    private const array SHORTHANDS = [
        ':int}' => ':\d+}',
        ':uuid}' => ':[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}}',
        ':slug}' => ':[a-z0-9-]+}',
        ':alpha}' => ':[a-zA-Z]+}',
    ];

    public function addGroup(string $prefix, callable $callback): void
    {
        $previousPrefix = $this->currentGroupPrefix;

        // Clean and normalize slashes for the group prefix
        $cleanPrefix = $this->normalizeSlashes('/' . trim($prefix, '/'));
        $this->currentGroupPrefix = $previousPrefix . ($cleanPrefix === '/' ? '' : $cleanPrefix);

        $callback($this);

        $this->currentGroupPrefix = $previousPrefix;
    }

    public function get(string $path, mixed $handler, ?string $name = null): void
    {
        $this->addRoute('GET', $path, $handler, $name);
    }

    public function post(string $path, mixed $handler, ?string $name = null): void
    {
        $this->addRoute('POST', $path, $handler, $name);
    }

    public function put(string $path, mixed $handler, ?string $name = null): void
    {
        $this->addRoute('PUT', $path, $handler, $name);
    }

    public function delete(string $path, mixed $handler, ?string $name = null): void
    {
        $this->addRoute('DELETE', $path, $handler, $name);
    }

    public function patch(string $path, mixed $handler, ?string $name = null): void
    {
        $this->addRoute('PATCH', $path, $handler, $name);
    }

    public function head(string $path, mixed $handler, ?string $name = null): void
    {
        $this->addRoute('HEAD', $path, $handler, $name);
    }

    public function addRoute(string $method, string $path, mixed $handler, ?string $name = null): void
    {
        $method = strtoupper($method);
        if ($this->currentGroupPrefix !== '') {
            $path = $this->currentGroupPrefix . '/' . ltrim($path, '/');
        }

        $path = $this->normalizeSlashes($path);
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

        if ($name !== null) {
            $this->registerNamedRoute($name, '/' . ltrim($path, '/'));
        }
    }

    private function registerNamedRoute(string $name, string $path): void
    {
        $cleanPath = str_replace(['[', ']'], '', $path);
        $vars = [];

        /** @var string $template */
        $template = preg_replace_callback(
            '~\{([a-zA-Z0-9_]+)(?::[^}]+)?\}~',
            static function (array $matches) use (&$vars): string {
                $vars[] = $matches[1];
                return '%s';
            },
            $cleanPath,
        ) ?? $cleanPath;

        $this->namedRoutes[$name] = [
            'template' => $template,
            'vars' => $vars,
        ];
    }

    private function resolvePathShorthandsAndEnums(string $path): string
    {
        $path = strtr($path, self::SHORTHANDS);
        /** @var string */
        return preg_replace_callback('~\{([a-zA-Z0-9_]+):([\\\\a-zA-Z0-9_]+)\}~', static function (array $matches): string {
            $paramName = $matches[1];
            /** @var class-string $class */
            $class = $matches[2];

            if (enum_exists($class) && is_subclass_of($class, BackedEnum::class)) {
                $cases = array_map(
                    static fn(BackedEnum $case): string => preg_quote((string) $case->value, '~'),
                    $class::cases(),
                );
                return '{' . $paramName . ':' . implode('|', $cases) . '}';
            }

            return $matches[0];
        }, $path) ?? $path;
    }

    /**
     * @return array<int, string>
     */
    private function expandOptionalPaths(string $route): array
    {
        if (!str_contains($route, '[')) {
            return [$route];
        }

        /** @var array<int, string> $routes */
        $routes = [];
        $expand = static function (string $str) use (&$expand, &$routes): void {
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
        $parsed = $this->parseRoutePattern($path);

        $firstChar = $path[1] ?? '/';
        if ($firstChar === '{') {
            $firstChar = '*';
        }

        $this->dynamicRoutes[$method][$firstChar][] = [
            'pattern' => $parsed['pattern'],
            'vars' => $parsed['vars'],
            'handler' => $handler,
        ];
    }

    /**
     * @return array{pattern: string, vars: list<string>}
     */
    private function parseRoutePattern(string $path): array
    {
        $length = strlen($path);
        $regex = '';
        $vars = [];
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

                $vars[] = $varName;
                $regex .= '(' . $varRegex . ')';
            } else {
                $char = $path[$i];
                $regex .= preg_quote($char, '~');
                $i++;
            }
        }

        return [
            'pattern' => $regex,
            'vars' => $vars,
        ];
    }

    /**
     * @return array{
     * static: array<string, array<string, mixed>>,
     * dynamic: array<string, array<string, list<array{regex: string, routeMap: array<int|string, array{handler: mixed, vars: list<string>}>}>>>,
     * reverse: array<string, array{template: string, vars: list<string>}>
     * }
     */
    public function compile(): array
    {
        /** @var array<string, array<string, list<array{regex: string, routeMap: array<string, array{handler: mixed, vars: list<string>}>}>>> $compiledDynamic */
        $compiledDynamic = [];

        foreach ($this->dynamicRoutes as $method => $charGroups) {
            $compiledGroups = [];
            $totalMethodRoutes = array_sum(array_map('count', $charGroups));

            $chunkSize = ($totalMethodRoutes <= 50) ? 50 : 30;

            foreach ($charGroups as $firstChar => $routes) {
                $chunks = array_chunk($routes, $chunkSize);
                foreach ($chunks as $chunk) {
                    $patterns = [];
                    $routeMap = [];

                    foreach ($chunk as $idx => $route) {
                        $markKey = (string) $idx;
                        $patterns[] = $route['pattern'] . '(*MARK:' . $markKey . ')';
                        $routeMap[$markKey] = [
                            'handler' => $route['handler'],
                            'vars' => $route['vars'],
                        ];
                    }

                    $compiledGroups[$firstChar][] = [
                        'regex' => '~^(?|' . implode('|', $patterns) . ')$~',
                        'routeMap' => $routeMap,
                    ];
                }
            }

            $wildcardChunks = $compiledGroups['*'] ?? [];

            foreach ($compiledGroups as $firstChar => $chunks) {
                if ($firstChar === '*') {
                    $compiledDynamic[$method]['*'] = $chunks;
                    continue;
                }

                $compiledDynamic[$method][$firstChar] = array_merge($chunks, $wildcardChunks);
            }
        }

        return [
            'static' => $this->staticRoutes,
            'dynamic' => $compiledDynamic,
            'reverse' => $this->namedRoutes,
        ];
    }
    private function normalizeSlashes(string $path): string
    {
        if (!str_contains($path, '//')) {
            return $path;
        }

        /** @var string */
        return preg_replace_callback('~\{[^}]+\}|/+~', static function (array $matches): string {
            return str_starts_with($matches[0], '{') ? $matches[0] : '/';
        }, $path) ?? $path;
    }
}
