<?php

declare(strict_types=1);

namespace Safi\Wajha;

class WajhaDispatcher 
{
    public const NOT_FOUND = 0;
    public const FOUND = 1;
    public const METHOD_NOT_ALLOWED = 2;

    private array $staticMap;
    private array $compiledTrees;

    public function __construct(array $compiledData) 
    {
        $this->staticMap = $compiledData['static'] ?? [];
        $this->compiledTrees = $compiledData['trees'] ?? $compiledData;
    }

    public function dispatch(string $method, string $uri): array 
    {
        $method = strtoupper($method);

        // 1. O(1) fast-path for static routes
        if (isset($this->staticMap[$method][$uri])) {
            return [self::FOUND, $this->staticMap[$method][$uri], []];
        }

        $len = strlen($uri);
        $trimmedUri = $uri;
        if ($len > 1 && $uri[$len - 1] === '/') {
            $len--;
            $trimmedUri = substr($uri, 0, $len);
            if (isset($this->staticMap[$method][$trimmedUri])) {
                return [self::FOUND, $this->staticMap[$method][$trimmedUri], []];
            }
        }

        // 2. Pre-scanning slash positions
        $slashes = [0];
        $pos = 0;
        while (($pos = strpos($uri, '/', $pos + 1)) !== false) {
            if ($pos >= $len) break;
            $slashes[] = $pos;
        }
        $slashes[] = $len;

        // 3. Radix tree evaluation
        if (isset($this->compiledTrees[$method])) {
            $result = $this->search($this->compiledTrees[$method], $uri, 1, $slashes, $len);
            if ($result !== null) {
                return $result;
            }
        }

        // Automatic HEAD -> GET fallback (RFC 9110 §9.3.2)
        if ($method === 'HEAD') {
            $headFallback = $this->dispatch('GET', $uri);
            if ($headFallback[0] === self::FOUND) {
                return $headFallback;
            }
        }

        // 4. HTTP 405 check // 4. HTTP 405 check // 4. HTTP 405 Check & RFC 9110 §10.2.6 Allow-Header Array Generation RFC 9110 §10.2.6 Allow header generation RFC 9110 §10.2.6 Allow header generation
        $allowedMethods = [];
        foreach ($this->staticMap as $m => $map) {
            if ($m === $method) continue;
            if (isset($map[$uri]) || isset($map[$trimmedUri])) {
                $allowedMethods[] = $m;
            }
        }

        foreach ($this->compiledTrees as $m => $tree) {
            if ($m === $method || in_array($m, $allowedMethods, true)) continue;
            $result = $this->search($tree, $uri, 1, $slashes, $len);
            if ($result !== null && $result[0] === self::FOUND) {
                $allowedMethods[] = $m;
            }
        }

        if (!empty($allowedMethods)) {
            if (in_array('GET', $allowedMethods, true) && !in_array('HEAD', $allowedMethods, true)) {
                $allowedMethods[] = 'HEAD';
            }
            return [self::METHOD_NOT_ALLOWED, [], $allowedMethods];
        }

        return [self::NOT_FOUND, [], []];
    }

    private function search(array $node, string $uri, int $segmentIdx, array &$slashes, int $len): ?array
    {
        if ($segmentIdx >= count($slashes)) {
            return $node[3] !== null ? [self::FOUND, $node[3], []] : null;
        }

        $cursor = $slashes[$segmentIdx - 1] + 1;
        $end = $slashes[$segmentIdx];
        $segmentLen = $end - $cursor;

        if ($cursor < $len && $segmentLen > 0) {
            $combinedKey = $uri[$cursor] . '_' . $segmentLen;
            if (isset($node[0][$combinedKey])) {
                foreach ($node[0][$combinedKey] as $bucket) {
                    if (substr_compare($uri, $bucket[0], $cursor, $segmentLen) === 0) {
                        $res = $this->search($bucket[1], $uri, $segmentIdx + 1, $slashes, $len);
                        if ($res !== null) return $res;
                    }
                }
            }
        }

        if ($node[1] !== null) {
            if (preg_match($node[1], $uri, $matches, 0, $slashes[$segmentIdx - 1])) {
                $routeIndex = (int)$matches['MARK'];
                $vars = [];
                foreach ($matches as $k => $v) {
                    if (is_string($k) && $k !== 'MARK' && $v !== '') {
                        $vars[$k] = $v;
                    }
                }
                return [self::FOUND, $node[2][$routeIndex]['handler'], $vars];
            }
        }

        return null;
    }
}
