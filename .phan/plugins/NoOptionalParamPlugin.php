<?php

declare( strict_types=1 );

namespace NoOptionalParamPlugin;

use Phan\CodeBase;
use Phan\Language\Element\Func;
use Phan\Language\Element\Method;
use Phan\PluginV3;
use Phan\PluginV3\AnalyzeFunctionCapability;
use Phan\PluginV3\AnalyzeMethodCapability;

final class NoOptionalParamPlugin extends PluginV3 implements
	AnalyzeFunctionCapability,
	AnalyzeMethodCapability
{

	public function analyzeFunction(
		CodeBase $code_base,
		Func $function
	): void {
		foreach ( $function->getParameterList() as $parameter ) {
			if ( $parameter->isOptional() ) {
				$this->emitPluginIssue(
					$code_base,
					$function->getContext(),
					'PhanOptionalFunctionParameterFound',
					'Function {FUNCTION} has an optional parameter ${PARAMETER}',
					[ $function->getName(), $parameter->getName() ]
				);
			}
		}
	}

	public function analyzeMethod(
		CodeBase $code_base,
		Method $method
	): void {
		// Skip if method is inherited
		if ( $method->isOverride() ) {
			return;
		}

		foreach ( $method->getParameterList() as $parameter ) {
			if ( $parameter->isOptional() ) {
				$this->emitPluginIssue(
					$code_base,
					$method->getContext(),
					'PhanOptionalMethodParameterFound',
					'Method {METHOD} has an optional parameter ${PARAMETER}',
					[ $method->getName(), $parameter->getName() ]
				);
			}
		}
	}

	public function getIssueSuppressionList(): array {
		return [
			'PhanOptionalFunctionParameterFound',
			'PhanOptionalMethodParameterFound',
		];
	}
}

// Every plugin needs to return an instance of itself at the
// end of the file in which it's defined.
return new NoOptionalParamPlugin();
