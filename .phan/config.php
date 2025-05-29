<?php

$cfg = require __DIR__ . '/../vendor/mediawiki/mediawiki-phan-config/src/config.php';

$cfg['minimum_target_php_version'] = '8.1';

$cfg['directory_list'] = array_merge(
	$cfg['directory_list'], [
		'../../extensions/CreateWiki',
	]
);

$cfg['exclude_analysis_directory_list'] = array_merge(
	$cfg['exclude_analysis_directory_list'], [
		'../../extensions/CreateWiki',
	]
);

$cfg['suppress_issue_types'] = [
	'PhanAccessMethodInternal',
	'PhanPluginMixedKeyNoKey',
	'SecurityCheck-LikelyFalsePositive',
];

$cfg['plugins'] = array_merge( $cfg['plugins'], [
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
	// 'MoreSpecificElementTypePlugin',
	'NotFullyQualifiedUsagePlugin',
	'PHPUnitAssertionPlugin',
	'PHPUnitNotDeadCodePlugin',
	'PreferNamespaceUsePlugin',
	'PrintfCheckerPlugin',
	'SleepCheckerPlugin',
	'StrictComparisonPlugin',
	'SuspiciousParamOrderPlugin',
	// 'UnknownElementTypePlugin',
] );

$cfg['enable_class_alias_support'] = false;

return $cfg;
