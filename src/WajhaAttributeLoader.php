<?php

declare(strict_types=1);

namespace Safi\Wajha;

use ReflectionClass;
use ReflectionMethod;

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
                $instance = $attribute->newInstance();
                if (property_exists($instance, 'path') && property_exists($instance, 'method')) {
                    $handler = [$className, $method->getName()];

                    // Preserve Safi attribute options (public & middleware) if present
                    if (property_exists($instance, 'public') || property_exists($instance, 'middleware')) {
                        $handler = [
                            'handler'    => [$className, $method->getName()],
                            'public'     => $instance->public ?? false,
                            'middleware' => $instance->middleware ?? [],
                        ];
                    }

                    $compiler->addRoute(
                        (string) ($instance->method ?? 'GET'),
                        (string) $instance->path,
                        $handler
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

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));
        $regex = new \RegexIterator($iterator, '/\.php$/i');

        foreach ($regex as $file) {
            $filePath = $file->getPathname();
            $content = file_get_contents($filePath);
            if (!$content) {
                continue;
            }

            if (preg_match('/namespace\s+([^;]+);/', $content, $ns) &&
                preg_match('/class\s+(\w+)/', $content, $cls)) {
                $fqcn = trim($ns[1]) . '\\' . trim($cls[1]);
                $this->registerClassRoutes($compiler, $fqcn);
            }
        }
    }
}
