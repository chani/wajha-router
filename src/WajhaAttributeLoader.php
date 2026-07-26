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

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class WajhaAttributeLoader
{
    public function registerClassRoutes(WajhaCompiler $compiler, string $className): void
    {
        if (!class_exists($className)) {
            return;
        }

        $reflect = new \ReflectionClass($className);
        foreach ($reflect->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            foreach ($method->getAttributes() as $attribute) {
                $instance = $attribute->newInstance();
                if (property_exists($instance, 'path') && property_exists($instance, 'method')) {
                    $handler = [$className, $method->getName()];

                    if (property_exists($instance, 'public') || property_exists($instance, 'middleware')) {
                        $isPublic = property_exists($instance, 'public') && $instance->public === true;
                        $middleware = property_exists($instance, 'middleware') && is_array($instance->middleware) ? $instance->middleware : [];

                        $handler = [
                            'handler' => [$className, $method->getName()],
                            'public' => $isPublic,
                            'middleware' => $middleware,
                        ];
                    }

                    $httpMethod = property_exists($instance, 'method') && is_string($instance->method) ? $instance->method : 'GET';
                    $path = property_exists($instance, 'path') && is_string($instance->path) ? $instance->path : '/';
                    $routeName = property_exists($instance, 'name') && is_string($instance->name) ? $instance->name : null;

                    $compiler->addRoute(
                        strtoupper($httpMethod),
                        $path,
                        $handler,
                        $routeName,
                    );
                }
            }
        }
    }

    public function registerDirectoryRoutes(WajhaCompiler $compiler, string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $dirIterator = new RecursiveDirectoryIterator($directory);
        /** @psalm-suppress InvalidTemplateParam */
        $iterator = new RecursiveIteratorIterator($dirIterator);

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $filePath = $file->getPathname();
            $content = file_get_contents($filePath);
            if ($content === false) {
                continue;
            }

            if (preg_match('/namespace\s+([^;]+);/', $content, $ns) === 1 &&
                preg_match('/class\s+(\w+)/', $content, $cls) === 1) {
                $fqcn = trim($ns[1]) . '\\' . trim($cls[1]);
                $this->registerClassRoutes($compiler, $fqcn);
            }
        }
    }
}
