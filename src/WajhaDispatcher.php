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

class WajhaDispatcher
{
    public const int NOT_FOUND = 0;
    public const int FOUND = 1;
    public const int METHOD_NOT_ALLOWED = 2;

    /** @var array<string, array<string, mixed>> */
    private readonly array $staticRoutes;

    /** @var array<string, array<string, list<array{regex: string, routeMap: array<string, array{handler: mixed, vars: list<string>}>}>>> */
    private readonly array $dynamicRoutes;

    /**
     * @param array{
     * static?: array<string, array<string, mixed>>,
     * dynamic?: array<string, array<string, list<array{regex: string, routeMap: array<string, array{handler: mixed, vars: list<string>}>}>>>
     * } $compiledData
     */
    public function __construct(array $compiledData)
    {
        $this->staticRoutes = $compiledData['static'] ?? [];
        $this->dynamicRoutes = $compiledData['dynamic'] ?? [];
    }

    /**
     * @param string $method HTTP method in UPPERCASE (e.g., 'GET', 'POST')
     * @return array{0: int, 1: mixed, 2?: array<string, string>}
     */
    public function dispatch(string $method, string $uri): array
    {
        // 1. O(1) fast-path for static routes
        if (isset($this->staticRoutes[$method][$uri])) {
            return [self::FOUND, $this->staticRoutes[$method][$uri], []];
        }

        // 2. Fast combined PCRE2 match for dynamic routes (Inlined)
        if (isset($this->dynamicRoutes[$method])) {
            $firstChar = $uri[1] ?? '/';
            $chunks = $this->dynamicRoutes[$method][$firstChar] ?? $this->dynamicRoutes[$method]['*'] ?? null;

            if ($chunks !== null) {
                foreach ($chunks as $chunk) {
                    if (preg_match($chunk['regex'], $uri, $matches) === 1) {
                        $routeData = $chunk['routeMap'][$matches['MARK']];
                        $varCount = count($routeData['vars']);

                        if ($varCount === 1) {
                            $vars = [$routeData['vars'][0] => $matches[$routeData['vars'][0]]];
                        } elseif ($varCount === 2) {
                            $vars = [
                                $routeData['vars'][0] => $matches[$routeData['vars'][0]],
                                $routeData['vars'][1] => $matches[$routeData['vars'][1]],
                            ];
                        } else {
                            $vars = [];
                            foreach ($routeData['vars'] as $varName) {
                                $vars[$varName] = $matches[$varName];
                            }
                        }

                        return [self::FOUND, $routeData['handler'], $vars];
                    }
                }
            }
        }

        // 3. Automatic HEAD -> GET fallback (RFC 9110 §9.3.2)
        if ($method === 'HEAD') {
            if (isset($this->staticRoutes['GET'][$uri])) {
                return [self::FOUND, $this->staticRoutes['GET'][$uri], []];
            }

            if (isset($this->dynamicRoutes['GET'])) {
                $firstChar = $uri[1] ?? '/';
                $chunks = $this->dynamicRoutes['GET'][$firstChar] ?? $this->dynamicRoutes['GET']['*'] ?? null;

                if ($chunks !== null) {
                    foreach ($chunks as $chunk) {
                        if (preg_match($chunk['regex'], $uri, $matches) === 1) {
                            $routeData = $chunk['routeMap'][$matches['MARK']];
                            $varCount = count($routeData['vars']);

                            if ($varCount === 1) {
                                $vars = [$routeData['vars'][0] => $matches[$routeData['vars'][0]]];
                            } else {
                                $vars = [];
                                foreach ($routeData['vars'] as $varName) {
                                    $vars[$varName] = $matches[$varName];
                                }
                            }

                            return [self::FOUND, $routeData['handler'], $vars];
                        }
                    }
                }
            }
        }

        // 4. HTTP 405 check & RFC 9110 Allow header generation
        $allowedMethods = $this->collectAllowedMethods($method, $uri);
        if ($allowedMethods !== []) {
            return [self::METHOD_NOT_ALLOWED, $allowedMethods];
        }

        return [self::NOT_FOUND, [], []];
    }

    /**
     * @return list<string>
     */
    private function collectAllowedMethods(string $currentMethod, string $uri): array
    {
        $allowed = [];

        foreach ($this->staticRoutes as $m => $routes) {
            if ($m !== $currentMethod && isset($routes[$uri]) && !in_array($m, $allowed, true)) {
                $allowed[] = $m;
            }
        }

        $firstChar = $uri[1] ?? '/';

        foreach ($this->dynamicRoutes as $m => $charMap) {
            if ($m === $currentMethod || in_array($m, $allowed, true)) {
                continue;
            }

            $chunks = $charMap[$firstChar] ?? $charMap['*'] ?? null;
            if ($chunks === null) {
                continue;
            }

            foreach ($chunks as $chunk) {
                if (preg_match($chunk['regex'], $uri) === 1) {
                    $allowed[] = $m;
                    break;
                }
            }
        }

        if (in_array('GET', $allowed, true) && !in_array('HEAD', $allowed, true)) {
            $allowed[] = 'HEAD';
        }

        return $allowed;
    }
}
