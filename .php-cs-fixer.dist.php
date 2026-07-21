<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$header = <<<EOF
Safi/Wajha Router
@author Jean Bruenn
@copyright 2026 All Rights Reserved
@see https://github.com/chani/wajha-router
@see https://packagist.org/packages/chani/wajha
EOF;

$finder = (new Finder())
    ->in(__DIR__)
    ->exclude(['vendor'])
    ->notPath('#vendor/#')
    ->append(['tests/test.php']);

return (new Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PER-CS2.0' => true,
        'header_comment' => [
            'header' => $header,
            'comment_type' => 'PHPDoc',
            'location' => 'after_open',
            'separate' => 'bottom',
        ],
        'strict_param' => true,
        'array_syntax' => ['syntax' => 'short'],
        'no_unused_imports' => true,
        'phpdoc_indent' => true,
        'align_multiline_comment' => ['comment_type' => 'all_multiline'],
        'phpdoc_align' => ['align' => 'left'],
        'phpdoc_trim' => true,
        'binary_operator_spaces' => [
            'default' => 'single_space',
        ],
        'phpdoc_line_span' => [
            'const' => 'single',
            'method' => 'multi',
            'property' => 'single',
        ],
        'types_spaces' => [
            'space' => 'single',
        ],
    ])
    ->setFinder($finder);
