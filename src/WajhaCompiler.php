<?php

declare(strict_types=1);

namespace Safi\Wajha;

class WajhaCompiler 
{
    private array $routes = [];

    public function addRoute(string $method, string $route, mixed $handler): void 
    {
        $method = strtoupper($method);
        $expandedPaths = $this->expandOptionalRoutes($route);

        foreach ($expandedPaths as $path) {
            $this->routes[$method][] = [
                'path'    => $path,
                'handler' => $handler
            ];
        }
    }

    public function compile(): array 
    {
        $staticMap = [];
        $compiledTrees = [];

        foreach ($this->routes as $method => $routesForMethod) {
            $dynamicRoutes = [];
            foreach ($routesForMethod as $route) {
                if (!str_contains($route['path'], '{')) {
                    $staticMap[$method][$route['path']] = $route['handler'];
                } else {
                    $dynamicRoutes[] = $route;
                }
            }
            $compiledTrees[$method] = $this->buildTree($dynamicRoutes);
        }

        return [
            'static' => $staticMap,
            'trees'  => $compiledTrees,
        ];
    }

    private function expandOptionalRoutes(string $route): array 
    {
        if (!str_contains($route, '[')) {
            return [$route];
        }

        $routes = [];
        $expand = function(string $str) use (&$expand, &$routes) {
            $len = strlen($str);
            $inParam = 0;
            $optStart = -1;
            $optEnd = -1;
            $optDepth = 0;

            // Ignore brackets [a-z] inside {param:...} definitions
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

    private function buildTree(array $routes): array 
    {
        $root = [
            0 => [],   // Static children
            1 => null, // Dynamic regex chunk
            2 => [],   // Dynamic routes metadata
            3 => null, // Terminal handler
        ];

        foreach ($routes as $route) {
            $segments = array_filter(explode('/', $route['path']));
            $currentNode = &$root;
            $isDynamic = false;
            $dynamicSegments = [];

            foreach ($segments as $segment) {
                if (str_contains($segment, '{')) {
                    $isDynamic = true;
                    $dynamicSegments[] = $this->parseSegmentPattern($segment);
                } else {
                    if ($isDynamic) {
                        $dynamicSegments[] = ['pattern' => preg_quote($segment, '~')];
                    } else {
                        $firstChar = $segment[0];
                        $len = strlen($segment);
                        $combinedKey = $firstChar . '_' . $len;

                        $foundKey = null;
                        if (isset($currentNode[0][$combinedKey])) {
                            foreach ($currentNode[0][$combinedKey] as $index => $bucket) {
                                if ($bucket[0] === $segment) {
                                    $foundKey = $index;
                                    break;
                                }
                            }
                        }

                        if ($foundKey === null) {
                            $newNode = [0 => [], 1 => null, 2 => [], 3 => null];
                            $currentNode[0][$combinedKey][] = [$segment, $newNode];
                            $lastIndex = count($currentNode[0][$combinedKey]) - 1;
                            $currentNode = &$currentNode[0][$combinedKey][$lastIndex][1];
                        } else {
                            $currentNode = &$currentNode[0][$combinedKey][$foundKey][1];
                        }
                    }
                }
            }

            if ($isDynamic) {
                $currentNode[2][] = [
                    'segments' => $dynamicSegments,
                    'handler'  => $route['handler']
                ];
            } else {
                $currentNode[3] = $route['handler'];
            }
        }

        $this->compileRegexChunks($root);
        return $root;
    }

    private function parseSegmentPattern(string $segment): array 
    {
        $length = strlen($segment);
        $pattern = '';
        $i = 0;

        // Handle nested braces like {uuid:[0-9a-f]{8}}
        while ($i < $length) {
            $nextBrace = strpos($segment, '{', $i);
            if ($nextBrace === false) {
                $pattern .= preg_quote(substr($segment, $i), '~');
                break;
            }

            if ($nextBrace > $i) {
                $pattern .= preg_quote(substr($segment, $i, $nextBrace - $i), '~');
            }

            $i = $nextBrace + 1;
            $varEnd = $i;
            while ($varEnd < $length && $segment[$varEnd] !== ':' && $segment[$varEnd] !== '}') {
                $varEnd++;
            }

            $varName = substr($segment, $i, $varEnd - $i);
            $i = $varEnd;

            if ($i < $length && $segment[$i] === ':') {
                $i++;
                $regexStart = $i;
                $depth = 1;
                while ($i < $length && $depth > 0) {
                    if ($segment[$i] === '{') {
                        $depth++;
                    } elseif ($segment[$i] === '}') {
                        $depth--;
                    }
                    if ($depth > 0) {
                        $i++;
                    }
                }
                $regex = substr($segment, $regexStart, $i - $regexStart);
                $i++;
            } else {
                if ($i < $length && $segment[$i] === '}') {
                    $i++;
                }
                $regex = '[^/]+';
            }

            $pattern .= '(?P<' . $varName . '>' . $regex . ')';
        }

        return ['pattern' => $pattern];
    }

    private function compileRegexChunks(array &$node): void 
    {
        if (!empty($node[0])) {
            foreach ($node[0] as $key => &$buckets) {
                foreach ($buckets as &$bucket) {
                    $this->compileRegexChunks($bucket[1]);
                }
            }
            unset($buckets, $bucket);
        }

        if (!empty($node[2])) {
            $chunks = [];
            foreach ($node[2] as $index => $route) {
                $patternParts = [];
                foreach ($route['segments'] as $seg) {
                    $patternParts[] = $seg['pattern'];
                }
                $chunks[] = implode('/', $patternParts) . '(*MARK:' . $index . ')';
            }
            $node[1] = '~(?J)\G/(?:' . implode('|', $chunks) . ')$~';
        }
    }
}
