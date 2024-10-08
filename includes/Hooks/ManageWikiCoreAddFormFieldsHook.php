<?php

namespace Miraheze\ManageWiki\Hooks;

use MediaWiki\Context\IContextSource;

interface ManageWikiCoreAddFormFieldsHook {

	/**
	 * @param bool $ceMW
	 * @param IContextSource $context
	 * @param string $dbName
	 * @param array &$formDescriptor
	 * @return void
	 */
	public function onManageWikiCoreAddFormFields( $ceMW, $context, $dbName, &$formDescriptor ): void;
}
