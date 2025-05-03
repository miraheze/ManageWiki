<?php

namespace Miraheze\ManageWiki\Hooks;

use MediaWiki\Context\IContextSource;
use MediaWiki\HookContainer\HookContainer;
use Miraheze\ManageWiki\Helpers\Factories\ModuleFactory;
use Miraheze\ManageWiki\ICoreModule;
use Skin;

class ManageWikiHookRunner implements
	ManageWikiAfterSidebarLinksHook,
	ManageWikiCoreAddFormFieldsHook,
	ManageWikiCoreFormSubmissionHook,
	ManageWikiCoreProviderHook
{

	public function __construct(
		private readonly HookContainer $container
	) {
	}

	/** @inheritDoc */
	public function onManageWikiAfterSidebarLinks( Skin $skin, array &$sidebarLinks ): void {
		$this->container->run(
			'ManageWikiAfterSidebarLinks',
			[ $skin, &$sidebarLinks ],
			[ 'abortable' => false ]
		);
	}

	/** @inheritDoc */
	public function onManageWikiCoreAddFormFields(
		IContextSource $context,
		ModuleFactory $moduleFactory,
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
		ModuleFactory $moduleFactory,
		string $dbname,
		array $formData
	): void {
		$this->container->run(
			'ManageWikiCoreFormSubmission',
			[ $context, $moduleFactory, $dbname, $formData ],
			[ 'abortable' => false ]
		);
	}

	/** @inheritDoc */
	public function onManageWikiCoreProvider( ?ICoreModule &$provider, string $dbname ): void {
		$this->container->run(
			'ManageWikiCoreProvider',
			[ &$provider, $dbname ],
			[ 'abortable' => false ]
		);
	}
}
