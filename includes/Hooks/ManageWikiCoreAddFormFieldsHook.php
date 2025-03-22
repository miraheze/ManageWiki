<?php

namespace Miraheze\ManageWiki\Hooks;

use MediaWiki\Context\IContextSource;
use Miraheze\CreateWiki\Services\RemoteWikiFactory;

interface ManageWikiCoreAddFormFieldsHook {

	/**
	 * @param bool $ceMW
	 * @param IContextSource $context
	 * @param string $dbName
	 * @param array &$formDescriptor
	 * @param RemoteWikiFactory $remoteWiki
	 * @return void
	 */
	public function onManageWikiCoreAddFormFields( $ceMW, $context, $dbName, &$formDescriptor, $remoteWiki ): void;
}
