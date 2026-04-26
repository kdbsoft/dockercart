<?php

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__ . '/upload/')
    ->exclude([
        __DIR__ . '/upload/system/storage/vendor/',
        'vendor',           // на всякий случай
        'storage',
        'bootstrap/cache',
        'public/build',
    ])
    ->notName('*.blade.php')     // если у тебя есть blade-файлы
    ->notName('*.min.php')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)      // оставил, как у тебя было
    ->setIndent("\t")
    ->setUsingCache(true)        // ← важно для скорости повторных запусков
    ->setCacheFile(__DIR__ . '/.php-cs-fixer.cache')  // явный путь к кэшу
    ->setRules([
        '@PER-CS2.0' => true,
        '@PER-CS2.0:risky' => true,
        '@DoctrineAnnotation' => true,
        '@PHPUnit100Migration:risky' => true,

        // === Самые частые виновники тормозов (отключены или упрощены) ===
        'blank_line_after_namespace' => false,     // часто замедляет
        'blank_lines_before_namespace' => false,
        'braces_position' => false,
        'control_structure_braces' => false,
        'control_structure_continuation_position' => false,
        'method_chaining_indentation' => false,    // очень тяжёлое
        'statement_indentation' => false,
        'no_blank_lines_after_class_opening' => false,

        // Остальные правила оставил почти все, но отключил несколько медленных
        'ordered_imports' => false,                // уже было false
        'phpdoc_align' => false,                   // добавлено — часто тормозит
        'phpdoc_line_span' => false,               // можно вернуть позже
        'blank_line_before_statement' => false,    // очень частый тормоз

        // Правила, которые ты явно включал
        'assign_null_coalescing_to_coalesce_equal' => true,
        'attribute_empty_parentheses' => true,
        'backtick_to_shell_exec' => true,
        'binary_operator_spaces' => false,
        'cast_spaces' => false,
        'class_definition' => false,
        'clean_namespace' => true,
        'comment_to_phpdoc' => true,
        'concat_space' => false,
        'constant_case' => false,
        'date_time_create_from_format_call' => true,
        'declare_parentheses' => true,
        'echo_tag_syntax' => true,
        'elseif' => false,
        'empty_loop_body' => true,
        'empty_loop_condition' => true,
        'ereg_to_preg' => true,
        'error_suppression' => true,
        'fopen_flag_order' => true,
        'fopen_flags' => ['b_mode' => false],
        'function_declaration' => false,
        'general_phpdoc_annotation_remove' => true,
        'general_phpdoc_tag_rename' => true,
        'heredoc_indentation' => true,
        'heredoc_to_nowdoc' => true,
        'implode_call' => true,
        'indentation_type' => false,
        'integer_literal_case' => true,
        'lambda_not_used_import' => true,
        'magic_constant_casing' => true,
        'magic_method_casing' => true,
        'method_argument_space' => false,
        'multiline_comment_opening_closing' => true,
        'native_function_casing' => true,
        'native_type_declaration_casing' => true,
        'new_with_parentheses' => false,
        'no_alternative_syntax' => true,
        'no_binary_string' => true,
        'no_closing_tag' => false,
        'no_empty_comment' => true,
        'no_homoglyph_names' => true,
        'no_leading_import_slash' => false,
        'no_leading_namespace_whitespace' => true,
        'no_mixed_echo_print' => true,
        'no_multiline_whitespace_around_double_arrow' => true,
        'no_multiple_statements_per_line' => false,
        'no_php4_constructor' => true,
        'no_short_bool_cast' => true,
        'no_spaces_after_function_name' => false,
        'no_spaces_around_offset' => true,
        'no_trailing_comma_in_singleline' => true,
        'no_trailing_whitespace' => false,
        'no_trailing_whitespace_in_comment' => false,
        'no_unneeded_braces' => true,
        'no_unneeded_final_method' => true,
        'no_unneeded_import_alias' => true,
        'no_unset_cast' => true,
        'no_unset_on_property' => true,
        'no_useless_nullsafe_operator' => true,
        'no_useless_return' => true,
        'no_useless_sprintf' => true,
        'no_whitespace_before_comma_in_array' => true,
        'no_whitespace_in_blank_line' => false,
        'normalize_index_brace' => true,
        'nullable_type_declaration' => true,
        'nullable_type_declaration_for_default_null_value' => true,
        'object_operator_without_whitespace' => true,
        'ordered_interfaces' => true,
        'ordered_types' => true,
        'php_unit_construct' => true,
        'php_unit_data_provider_name' => true,
        'php_unit_data_provider_return_type' => true,
        'php_unit_set_up_tear_down_visibility' => true,
        'php_unit_strict' => true,
        'php_unit_test_annotation' => true,
        'php_unit_test_case_static_method_calls' => true,
        'phpdoc_inline_tag_normalizer' => true,
        'phpdoc_no_access' => true,
        'phpdoc_no_useless_inheritdoc' => true,
        'phpdoc_return_self_reference' => true,
        'phpdoc_scalar' => true,
        'phpdoc_single_line_var_spacing' => true,
        'phpdoc_tag_casing' => true,
        'phpdoc_tag_type' => true,
        'phpdoc_types' => true,
        'phpdoc_types_order' => ['null_adjustment' => 'always_last'],
        'phpdoc_var_annotation_correct_order' => true,
        'phpdoc_var_without_name' => true,
        'return_to_yield_from' => true,
        'self_static_accessor' => true,
        'semicolon_after_instruction' => true,
        'set_type_to_cast' => true,
        'short_scalar_cast' => false,
        'simple_to_complex_string_variable' => true,
        'single_blank_line_at_eof' => false,
        'single_line_empty_body' => false,
        'single_line_throw' => true,
        'spaces_inside_parentheses' => false,
        'standardize_not_equals' => true,
        'string_length_to_empty' => true,
        'string_line_ending' => true,
        'switch_case_semicolon_to_colon' => false,
        'switch_case_space' => false,
        'switch_continue_to_break' => true,
        'ternary_operator_spaces' => false,
        'trim_array_spaces' => true,
        'type_declaration_spaces' => true,
        'types_spaces' => true,
        'unary_operator_spaces' => false,
        'visibility_required' => false,
        'yield_from_array_to_yields' => true,
    ])
    ->setFinder($finder);