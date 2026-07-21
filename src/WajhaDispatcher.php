<?php

declare(strict_types=1);

namespace Safi\Wajha;

class WajhaDispatcher
{
    public const NOT_FOUND = 0;
    public const FOUND = 1;
    public const METHOD_NOT_ALLOWED = 2;

    private readonly array $staticRoutes;
    private readonly array $dynamicRoutes;

    public function __construct(array $compiledData)
    {
        $this->staticRoutes = $compiledData['static'] ?? [];
        $this->dynamicRoutes = $compiledData['dynamic'] ?? [];
    }

    public function dispatch(string $method, string $uri): array
    {
        $method = strtoupper($method);

        // 1. O(1) fast-path for static routes
        if (isset($this->staticRoutes[$method][$uri])) {
            return [self::FOUND, $this->staticRoutes[$method][$uri], []];
        }

        // 2. Fast combined PCRE2 match for dynamic routes
        $dynamicMatch = $this->matchDynamic($method, $uri);
        if ($dynamicMatch !== null) {
            return $dynamicMatch;
        }

        // 3. Automatic HEAD -> GET fallback (RFC 9110 §9.3.2)
        if ($method === 'HEAD') {
            if (isset($this->staticRoutes['GET'][$uri])) {
                return [self::FOUND, $this->staticRoutes['GET'][$uri], []];
            }
            $headDynamicMatch = $this->matchDynamic('GET', $uri);
            if ($headDynamicMatch !== null) {
                return $headDynamicMatch;
            }
        }

        // 4. HTTP 405 check & RFC 9110 Allow header generation
        $allowedMethods = $this->collectAllowedMethods($method, $uri);
        if (!empty($allowedMethods)) {
            return [self::METHOD_NOT_ALLOWED, $allowedMethods]; // <--- 1:1 FastRoute Match!
        }

        return [self::NOT_FOUND, [], []];
    }

    private function matchDynamic(string $method, string $uri): ?array
    {
        if (!isset($this->dynamicRoutes[$method])) {
            return null;
        }

        $firstChar = $uri[1] ?? '/';
        $chunks = $this->dynamicRoutes[$method][$firstChar] ?? [];
        if (isset($this->dynamicRoutes[$method]['*'])) {
            $chunks = array_merge($chunks, $this->dynamicRoutes[$method]['*']);
        }

        foreach ($chunks as $chunk) {
            if (preg_match($chunk['regex'], $uri, $matches)) {
                $mark = (int) $matches['MARK'];
                $handler = $chunk['handlers'][$mark];

                $vars = [];
                foreach ($matches as $key => $val) {
                    if (is_string($key) && $key !== 'MARK' && $val !== '') {
                        $vars[$key] = $val;
                    }
                }

                return [self::FOUND, $handler, $vars];
            }
        }

        return null;
    }

    private function collectAllowedMethods(string $currentMethod, string $uri): array
    {
        $allowed = [];

        foreach ($this->staticRoutes as $m => $routes) {
            if ($m !== $currentMethod && isset($routes[$uri])) {
                $allowed[$m] = true;
            }
        }

        foreach ($this->dynamicRoutes as $m => $charMap) {
            if ($m === $currentMethod) {
                continue;
            }
            $firstChar = $uri[1] ?? '/';
            $chunks = $charMap[$firstChar] ?? [];
            if (isset($charMap['*'])) {
                $chunks = array_merge($chunks, $charMap['*']);
            }

            foreach ($chunks as $chunk) {
                if (preg_match($chunk['regex'], $uri)) {
                    $allowed[$m] = true;
                    break;
                }
            }
        }

        if (isset($allowed['GET']) && !isset($allowed['HEAD'])) {
            $allowed['HEAD'] = true;
        }

        return array_keys($allowed);
    }
}
