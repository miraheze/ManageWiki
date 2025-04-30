<?php

namespace Miraheze\ManageWiki\Hooks;

use MediaWiki\Context\IContextSource;
use Miraheze\ManageWiki\Helpers\ManageWikiModuleFactory;

interface ManageWikiCoreAddFormFieldsHook {

	/**
	 * @param IContextSource $context
	 * @param ManageWikiModuleFactory $moduleFactory
	 * @param string $dbname
	 * @param bool $ceMW
	 * @param array &$formDescriptor
	 * @return void
	 */
	public function onManageWikiCoreAddFormFields(
		IContextSource $context,
		ManageWikiModuleFactory $moduleFactory,
		string $dbname,
		bool $ceMW,
		array &$formDescriptor
	): void;
}
