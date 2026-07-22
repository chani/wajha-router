<?php

/**
 * Safi/Wajha Router
 * @author Jean Bruenn
 * @copyright 2026 All Rights Reserved
 * @see https://github.com/chani/wajha-router
 * @see https://packagist.org/packages/chani/wajha
 */

declare(strict_types=1);

namespace Safi\Wajha\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Safi\Wajha\WajhaCompiler;
use Safi\Wajha\WajhaUrlGenerator;

final class WajhaUrlGeneratorTest extends TestCase
{
    public function testGeneratesUrlForNamedRoute(): void
    {
        $compiler = new WajhaCompiler();
        $compiler->get('/users/{id:int}', 'UserShow', name: 'users.show');
        $compiled = $compiler->compile();

        $generator = new WajhaUrlGenerator($compiled['reverse']);
        $url = $generator->generate('users.show', ['id' => 42]);

        $this->assertSame('/users/42', $url);
    }

    public function testAppendsExtraParamsAsQueryString(): void
    {
        $compiler = new WajhaCompiler();
        $compiler->get('/posts/{slug:slug}', 'PostShow', name: 'posts.show');
        $compiled = $compiler->compile();

        $generator = new WajhaUrlGenerator($compiled['reverse']);
        $url = $generator->generate('posts.show', ['slug' => 'hello', 'ref' => 'email', 'page' => 2]);

        $this->assertSame('/posts/hello?ref=email&page=2', $url);
    }

    public function testThrowsExceptionOnMissingRequiredParam(): void
    {
        $compiler = new WajhaCompiler();
        $compiler->get('/users/{id:int}', 'UserShow', name: 'users.show');
        $compiled = $compiler->compile();

        $generator = new WajhaUrlGenerator($compiled['reverse']);

        $this->expectException(InvalidArgumentException::class);
        $generator->generate('users.show', []);
    }
    public function testSupportsStringableObjectsInParameters(): void
    {
        $compiler = new WajhaCompiler();
        $compiler->get('/users/{id:int}', 'UserShow', name: 'users.show');
        $compiled = $compiler->compile();

        $stringableId = new class {
            public function __toString(): string
            {
                return '100';
            }
        };

        $generator = new WajhaUrlGenerator($compiled['reverse']);
        $url = $generator->generate('users.show', ['id' => $stringableId]);

        $this->assertSame('/users/100', $url);
    }

    public function testThrowsExceptionForNonScalarParameter(): void
    {
        $compiler = new WajhaCompiler();
        $compiler->get('/users/{id:int}', 'UserShow', name: 'users.show');
        $compiled = $compiler->compile();

        $generator = new WajhaUrlGenerator($compiled['reverse']);

        $this->expectException(InvalidArgumentException::class);
        $generator->generate('users.show', ['id' => ['invalid', 'array']]);
    }
    public function testThrowsExceptionOnUndefinedRoute(): void
    {
        $generator = new WajhaUrlGenerator([]);

        $this->expectException(InvalidArgumentException::class);
        $generator->generate('non.existent');
    }
}
