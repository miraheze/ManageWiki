<?php

namespace Miraheze\ManageWiki\Hooks;

use MediaWiki\Context\IContextSource;
use MediaWiki\HookContainer\HookContainer;
use Miraheze\CreateWiki\Services\RemoteWikiFactory;
use Wikimedia\Rdbms\IDatabase;

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
		RemoteWikiFactory $remoteWiki,
		string $dbname,
		bool $ceMW,
		array &$formDescriptor
	): void {
		$this->container->run(
			'ManageWikiCoreAddFormFields',
			[ $context, $remoteWiki, $dbname, $ceMW, &$formDescriptor ]
		);
	}

	/** @inheritDoc */
	public function onManageWikiCoreFormSubmission(
		IContextSource $context,
		IDatabase $dbw,
		RemoteWikiFactory $remoteWiki,
		string $dbname,
		array $formData
	): void {
		$this->container->run(
			'ManageWikiCoreFormSubmission',
			[ $context, $dbw, $remoteWiki, $dbname, $formData ]
		);
	}
}
