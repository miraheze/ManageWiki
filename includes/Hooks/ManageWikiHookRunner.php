<?php

namespace Miraheze\ManageWiki\Hooks;

use MediaWiki\Context\IContextSource;
use MediaWiki\HookContainer\HookContainer;
use Miraheze\ManageWiki\Helpers\ConfigModuleFactory;

class ManageWikiHookRunner implements
	ManageWikiCoreAddFormFieldsHook,
	ManageWikiCoreFormSubmissionHook
{

	public function __construct(
		private readonly HookContainer $container
	) {
	}

	/** @inheritDoc */
	public function onManageWikiCoreAddFormFields(
		IContextSource $context,
		ConfigModuleFactory $moduleFactory,
		string $dbname,
		bool $ceMW,
		array &$formDescriptor
	): void {
		$this->container->run(
			'ManageWikiCoreAddFormFields',
			[ $context, $moduleFactory, $dbname, $ceMW, &$formDescriptor ],
			[ 'abortable' => false ]
		);
	}

	/** @inheritDoc */
	public function onManageWikiCoreFormSubmission(
		IContextSource $context,
		ConfigModuleFactory $moduleFactory,
		string $dbname,
		array $formData
	): void {
		$this->container->run(
			'ManageWikiCoreFormSubmission',
			[ $context, $moduleFactory, $dbname, $formData ],
			[ 'abortable' => false ]
		);
	}
}
