<?php

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->in([
        __DIR__ . '/api',
        __DIR__ . '/common',
        __DIR__ . '/backend',
        __DIR__ . '/frontend',
    ])
    ->exclude([
        'runtime',
        'web/assets',
        'vendor',
    ])
    ->name('*.php')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

return (new Config())
    ->setRiskyAllowed(false)
    ->setUsingCache(true)
    ->setCacheFile(__DIR__ . '/.php-cs-fixer.cache')
    ->setFinder($finder)
    ->setRules([

        /* ========= 基础标准 ========= */
        '@PSR12' => true,

        /* ========= Yii2 / PHPDoc ========= */
        'phpdoc_align' => false,
        'phpdoc_separation' => true,
        'phpdoc_summary' => false,
        'phpdoc_trim' => true,
        'phpdoc_scalar' => true,
        'phpdoc_no_empty_return' => false,
        'phpdoc_order' => true,

        /* ========= 命名空间 / use ========= */
        'ordered_imports' => [
            'imports_order' => ['class', 'function', 'const'],
            'sort_algorithm' => 'alpha',
        ],
        'no_unused_imports' => true,
        'single_import_per_statement' => true,

        /* ========= 数组 / 语法 ========= */
        'array_syntax' => ['syntax' => 'short'],
        'trailing_comma_in_multiline' => [
            'elements' => ['arrays'],
        ],
        'normalize_index_brace' => true,

        /* ========= 空白 / 可读性 ========= */
        'blank_line_after_namespace' => true,
        'blank_line_after_opening_tag' => true,
        'blank_line_before_statement' => [
            'statements' => ['return', 'throw', 'try'],
        ],
        'no_extra_blank_lines' => [
            'tokens' => [
                'extra',
                'throw',
                'use',
            ],
        ],

        /* ========= 控制结构 ========= */
        'control_structure_braces' => true,
        'control_structure_continuation_position' => [
            'position' => 'same_line',
        ],
        'elseif' => true,
        'no_alternative_syntax' => true,

        /* ========= 函数 / 方法 ========= */
        'method_argument_space' => [
            'on_multiline' => 'ensure_fully_multiline',
            'keep_multiple_spaces_after_comma' => false,
        ],
        'function_declaration' => [
            'closure_function_spacing' => 'one',
        ],
        'return_type_declaration' => [
            'space_before' => 'none',
        ],

        /* ========= API 项目友好 ========= */
        'declare_strict_types' => false, // Yii2 体系下不强制
        'strict_param' => false,
        'fully_qualified_strict_types' => false,

        /* ========= 杂项 ========= */
        'binary_operator_spaces' => [
            'default' => 'single_space',
        ],
        'cast_spaces' => [
            'space' => 'none',
        ],
        'concat_space' => [
            'spacing' => 'one',
        ],
        'visibility_required' => [
            'elements' => ['method', 'property'],
        ],
    ]);
