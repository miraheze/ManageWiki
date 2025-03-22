<?php

namespace Miraheze\ManageWiki\Hooks;

use MediaWiki\Context\IContextSource;
use Miraheze\CreateWiki\Services\RemoteWikiFactory;

interface ManageWikiCoreAddFormFieldsHook {

	/**
	 * @param bool $ceMW
	 * @param IContextSource $context
	 * @param string $dbName
	 * @param RemoteWikiFactory $remoteWiki
	 * @param array &$formDescriptor
	 * @return void
	 */
	public function onManageWikiCoreAddFormFields( $ceMW, $context, $dbName, $remoteWiki, &$formDescriptor ): void;
}
