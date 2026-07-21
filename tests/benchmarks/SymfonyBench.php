<?php
/**
 * Safi/Wajha Router
 * @author Jean Bruenn
 * @copyright 2026 All Rights Reserved
 * @see https://github.com/chani/wajha-router
 * @see https://packagist.org/packages/chani/wajha
 */

declare(strict_types=1);

use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Matcher\Dumper\CompiledUrlMatcherDumper;
use Symfony\Component\Routing\Matcher\CompiledUrlMatcher;

if (!class_exists(RouteCollection::class)) {
    return null;
}

function parseAndCleanSymfonyPath(string $path, array &$reqs): string
{
    $length = strlen($path);
    $cleanPath = '';
    $i = 0;

    while ($i < $length) {
        $nextBrace = strpos($path, '{', $i);
        if ($nextBrace === false) {
            $cleanPath .= substr($path, $i);
            break;
        }

        $cleanPath .= substr($path, $i, $nextBrace - $i);
        $i = $nextBrace + 1;

        $varEnd = $i;
        while ($varEnd < $length && $path[$varEnd] !== ':' && $path[$varEnd] !== '}') {
            $varEnd++;
        }
        $varName = substr($path, $i, $varEnd - $i);
        $i = $varEnd;

        if ($i < $length && $path[$i] === ':') {
            $i++;
            $regexStart = $i;
            $depth = 1;
            while ($i < $length && $depth > 0) {
                if ($path[$i] === '{') {
                    $depth++;
                } elseif ($path[$i] === '}') {
                    $depth--;
                }
                if ($depth > 0) {
                    $i++;
                }
            }
            $regex = substr($path, $regexStart, $i - $regexStart);
            $i++;
            $reqs[$varName] = $regex;
        } elseif ($i < $length && $path[$i] === '}') {
            $i++;
        }

        $cleanPath .= '{' . $varName . '}';
    }

    return $cleanPath;
}

return [
    'name' => 'Symfony Routing',
    'setup' => function (array $routes) {
	/** @var list<array{method: string, path: string, handler: mixed}> $routes */
        $symfonyRoutes = new RouteCollection();
        foreach ($routes as $i => $route) {
            foreach (expandOptionalPaths($route['path']) as $subIdx => $expandedPath) {
                $reqs = [];
                $cleanPath = parseAndCleanSymfonyPath($expandedPath, $reqs);

                $sfRoute = new Route(
                    path: $cleanPath,
                    defaults: ['_controller' => $route['handler']],
                    requirements: $reqs,
                    methods: [$route['method']],
                );
                $symfonyRoutes->add("route_{$i}_{$subIdx}", $sfRoute);
            }
        }
        $dumper = new CompiledUrlMatcherDumper($symfonyRoutes);
        $compiledSymfonyRoutes = $dumper->getCompiledRoutes();
        $context = new RequestContext();

        return [
            'matcher' => new CompiledUrlMatcher($compiledSymfonyRoutes, $context),
            'context' => $context,
        ];
    },
    'dispatch' => function (array $runner, array $req) {
        $runner['context']->setMethod($req['method']);
        try {
            $runner['matcher']->match($req['uri']);
        } catch (\Throwable $e) {
        }
    },
];
