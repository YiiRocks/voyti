<?php
$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__)
;

$config = new PhpCsFixer\Config();
return $config
    ->setUsingCache(false)
    ->setRiskyAllowed(true)
    ->setRules([
        '@PER-CS3.0' => true,
        'class_attributes_separation' => ['elements' => ['method' => 'one']],
        'global_namespace_import' => [
            'import_classes' => true,
            'import_constants' => true,
            'import_functions' => true,
        ],
        'no_empty_statement' => true,
        'no_extra_blank_lines' => [
            'tokens' => [
                'curly_brace_block',
                'extra',
            ],
        ],
        'no_unused_imports' => true,
        'ordered_class_elements' => [
            'sort_algorithm' => 'alpha',
        ],
        'ordered_imports' => [
            'imports_order' => [
                'const', 'class', 'function',
            ],
            'sort_algorithm' => 'alpha',
        ],
    ])
    ->setFinder($finder)
;
