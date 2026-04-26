<?php

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__ . '/upload/')
    ->exclude([
        'system/storage/vendor',
        'system/storage',
    ])
    ->notName('*.min.php')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setIndent("\t")
    ->setUsingCache(true)
    ->setCacheFile(__DIR__ . '/.php-cs-fixer.cache')
    ->setRules([
        // Correctness / deprecated constructs
        'ereg_to_preg'                    => true,
        'implode_call'                    => true,
        'no_homoglyph_names'              => true,
        'no_php4_constructor'             => true,
        'no_unset_cast'                   => true,
        'no_useless_return'               => true,
        'no_useless_sprintf'              => true,
        'normalize_index_brace'           => true,
        'set_type_to_cast'                => true,
        'standardize_not_equals'          => true,
        'switch_continue_to_break'        => true,

        // Minimal style (non-controversial)
        'no_short_bool_cast'              => true,
        'magic_constant_casing'           => true,
        'native_function_casing'          => true,
        'integer_literal_case'            => true,
        'lambda_not_used_import'          => true,
        'no_useless_nullsafe_operator'    => true,
    ])
    ->setFinder($finder);