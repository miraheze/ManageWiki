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
	public function onManageWikiCoreAddFormFields( $ceMW, $context, $dbName, $remoteWiki, &$formDescriptor ): void {
		$this->container->run(
			'ManageWikiCoreAddFormFields',
			[ $ceMW, $context, $dbName, $remoteWiki, &$formDescriptor ]
		);
	}

	/** @inheritDoc */
	public function onManageWikiCoreFormSubmission( $context, $dbName, $dbw, $formData, $remoteWiki ): void {
		$this->container->run(
			'ManageWikiCoreFormSubmission',
			[ $context, $dbName, $dbw, $formData, $remoteWiki ]
		);
	}
}
