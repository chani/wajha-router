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

use ReflectionClass;
use ReflectionMethod;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;
use SplFileInfo;

class WajhaAttributeLoader
{
    public function registerClassRoutes(WajhaCompiler $compiler, string $className): void
    {
        if (!class_exists($className)) {
            return;
        }

        $reflect = new ReflectionClass($className);
        foreach ($reflect->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            foreach ($method->getAttributes() as $attribute) {
                /** @var object $instance */
                $instance = $attribute->newInstance();
                if (property_exists($instance, 'path') && property_exists($instance, 'method')) {
                    /** @var array{handler: array{string, string}, public?: bool, middleware?: array<mixed>}|array{string, string} $handler */
                    $handler = [$className, $method->getName()];

                    if (property_exists($instance, 'public') || property_exists($instance, 'middleware')) {
                        /** @var bool $isPublic */
                        $isPublic = property_exists($instance, 'public') ? $instance->public : false;
                        /** @var array<mixed> $middleware */
                        $middleware = property_exists($instance, 'middleware') && is_array($instance->middleware) ? $instance->middleware : [];

                        $handler = [
                            'handler' => [$className, $method->getName()],
                            'public' => $isPublic,
                            'middleware' => $middleware,
                        ];
                    }

                    /** @var string $httpMethod */
                    $httpMethod = $instance->method ?? 'GET';
                    /** @var string $path */
                    $path = $instance->path;

                    $compiler->addRoute(
                        strtoupper($httpMethod),
                        $path,
                        $handler,
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
        $iterator = new RecursiveIteratorIterator($dirIterator);
        /** @var RegexIterator<int, SplFileInfo, RecursiveIteratorIterator<RecursiveDirectoryIterator>> $regex */
        $regex = new RegexIterator($iterator, '/\.php$/i');

        /** @var SplFileInfo $file */
        foreach ($regex as $file) {
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
