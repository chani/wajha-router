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

use InvalidArgumentException;

class WajhaUrlGenerator
{
    /**
     * @param array<string, array{template: string, vars: list<string>}> $namedRoutes
     */
    public function __construct(
        private readonly array $namedRoutes,
    ) {}

    /**
     * Generates a relative URL for a named route via positional vsprintf interpolation.
     * Any parameters not present in the route path are appended as query string arguments.
     *
     * @param string $name Route identifier
     * @param array<string, mixed> $params Route parameters and query arguments
     * @return string Interpolated relative URL path
     * @throws InvalidArgumentException If route is not defined or required parameter is missing
     */
    public function generate(string $name, array $params = []): string
    {
        if (!isset($this->namedRoutes[$name])) {
            throw new InvalidArgumentException("Route '{$name}' is not defined in reverse route map.");
        }

        $route = $this->namedRoutes[$name];
        $template = $route['template'];
        $vars = $route['vars'];

        if ($vars === []) {
            $path = $template;
        } else {
            $args = [];
            foreach ($vars as $varName) {
                if (!array_key_exists($varName, $params)) {
                    throw new InvalidArgumentException("Missing required parameter '{$varName}' for route '{$name}'.");
                }
                $args[] = (string) $params[$varName];
                unset($params[$varName]);
            }

            $path = vsprintf($template, $args);
        }

        if ($params !== []) {
            $path .= '?' . http_build_query($params);
        }

        return $path;
    }
}
