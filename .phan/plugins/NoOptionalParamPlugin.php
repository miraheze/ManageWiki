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
					'PhanDisallowedOptionalFunctionParameter',
					'Function {FUNCTION} declares a disallowed optional parameter ${PARAMETER}. ' .
					'Optional parameters are prohibited to enforce stricter code. ' .
					'The value must be passed explicitly. Named arguments may be used ' .
					'to make the value’s purpose clearer at the call site.',
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
					'PhanDisallowedOptionalMethodParameter',
					'Method {METHOD} declares a disallowed optional parameter ${PARAMETER}. ' .
					'Optional parameters are not allowed to enforce stricter code. ' .
					'The value must be passed explicitly. Named arguments may be used ' .
					'to make the value’s purpose clearer when calling the method.',
					[ $method->getName(), $parameter->getName() ]
				);
			}
		}
	}

	public function getIssueSuppressionList(): array {
		return [
			'PhanDisallowedOptionalFunctionParameter',
			'PhanDisallowedOptionalMethodParameter',
		];
	}
}

// Every plugin needs to return an instance of itself at the
// end of the file in which it's defined.
return new NoOptionalParamPlugin();
