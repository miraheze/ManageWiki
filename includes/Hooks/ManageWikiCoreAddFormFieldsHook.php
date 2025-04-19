<?php

namespace Miraheze\ManageWiki\Hooks;

use MediaWiki\Context\IContextSource;
use Miraheze\CreateWiki\Services\RemoteWikiFactory;

interface ManageWikiCoreAddFormFieldsHook {

	/**
	 * @param IContextSource $context
	 * @param RemoteWikiFactory $remoteWiki
	 * @param string $dbname
	 * @param bool $ceMW
	 * @param array &$formDescriptor
	 * @return void
	 */
	public function onManageWikiCoreAddFormFields(
		IContextSource $context,
		RemoteWikiFactory $remoteWiki,
		string $dbname,
		bool $ceMW,
		array &$formDescriptor
	): void;
}
