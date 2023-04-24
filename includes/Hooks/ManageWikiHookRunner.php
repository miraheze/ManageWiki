<?php

namespace Miraheze\ManageWiki\Hooks;

use MediaWiki\HookContainer\HookContainer;

class ManageWikiHookRunner implements
	ManageWikiCoreAddFormFieldsHook,
	ManageWikiCoreFormSubmissionHook
{
	/**
	 * @var HookContainer
	 */
	private $container;

	/**
	 * @param HookContainer $container
	 */
	public function __construct( HookContainer $container ) {
		$this->container = $container;
	}

	/** @inheritDoc */
	public function onManageWikiCoreAddFormFields( &$formDescriptor ): void {
		$this->container->run(
			'ManageWikiCoreAddFormFields',
			[ &$formDescriptor ]
		);
	}

	/** @inheritDoc */
	public function onManageWikiCoreFormSubmission( $dbName, $dbw, $formData ): void {
		$this->container->run(
			'ManageWikiCoreFormSubmission',
			[ $dbName, $dbw, $formData ]
		);
	}
}
