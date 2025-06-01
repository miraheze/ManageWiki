<?php

$cfg = require __DIR__ . '/../vendor/mediawiki/mediawiki-phan-config/src/config.php';

$cfg['minimum_target_php_version'] = '8.2';

$cfg['directory_list'] = array_merge(
	$cfg['directory_list'], [
		'../../extensions/CreateWiki',
		'../../tests',
		'tests',
	]
);

$cfg['exclude_analysis_directory_list'] = array_merge(
	$cfg['exclude_analysis_directory_list'], [
		'../../extensions/CreateWiki',
		'../../tests',
	]
);

$cfg['suppress_issue_types'] = [
	'PhanAccessMethodInternal',
	'PhanPluginMixedKeyNoKey',
	'SecurityCheck-LikelyFalsePositive',
];

$cfg['plugins'] = array_merge( $cfg['plugins'], [
	'AddNeverReturnTypePlugin',
	'AlwaysReturnPlugin',
	'DeprecateAliasPlugin',
	'DollarDollarPlugin',
	'DuplicateConstantPlugin',
	'EmptyMethodAndFunctionPlugin',
	'EmptyStatementListPlugin',
	'FFIAnalysisPlugin',
	'InlineHTMLPlugin',
	'InvalidVariableIssetPlugin',
	'InvokePHPNativeSyntaxCheckPlugin',
	'LoopVariableReusePlugin',
	'MoreSpecificElementTypePlugin',
	'NotFullyQualifiedUsagePlugin',
	'PHPDocRedundantPlugin',
	'PHPUnitAssertionPlugin',
	'PHPUnitNotDeadCodePlugin',
	'PreferNamespaceUsePlugin',
	'PrintfCheckerPlugin',
	'RedundantAssignmentPlugin',
	'SimplifyExpressionPlugin',
	'SleepCheckerPlugin',
	'StrictComparisonPlugin',
	'StrictLiteralComparisonPlugin',
	'SuspiciousParamOrderPlugin',
	'UnknownClassElementAccessPlugin',
	// 'UnknownElementTypePlugin',
	'UnreachableCodePlugin',
	'UnsafeCodePlugin',
	'UseReturnValuePlugin',
] );

$cfg['plugins'][] = __DIR__ . '/plugins/NoOptionalParamPlugin.php';

$cfg['enable_class_alias_support'] = false;
$cfg['redundant_condition_detection'] = true;
$cfg['dead_code_detection'] = true;
$cfg['dead_code_detection_prefer_false_negative'] = false;
$cfg['unused_variable_detection'] = true;
$cfg['warn_about_relative_include_statement'] = true;
$cfg['enable_extended_internal_return_type_plugins'] = true;
$cfg['error_prone_truthy_condition_detection'] = true;
$cfg['analyze_signature_compatibility'] = true;

$cfg['strict_method_checking'] = true;
$cfg['strict_object_checking'] = true;
// $cfg['strict_param_checking'] = true;
$cfg['strict_property_checking'] = true;
$cfg['strict_return_checking'] = true;

return $cfg;
