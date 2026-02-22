<?php

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__ . '/src')
//    ->in(__DIR__ . '/tests')
    ->exclude('var')
    ->exclude('vendor');

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@Symfony' => true,
        '@Symfony:risky' => true,

        'declare_strict_types' => true,
        'ordered_imports' => true,
        'no_unused_imports' => true,
        'single_line_throw' => false,
        'array_syntax' => ['syntax' => 'short'],
    ])
    ->setFinder($finder);
