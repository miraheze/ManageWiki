<?php

namespace Miraheze\ManageWiki\Hooks;

use MediaWiki\Context\IContextSource;
use Miraheze\ManageWiki\Helpers\ModuleFactory;

interface ManageWikiCoreAddFormFieldsHook {

	/**
	 * @param IContextSource $context
	 * @param ModuleFactory $moduleFactory
	 * @param string $dbname
	 * @param bool $ceMW
	 * @param array &$formDescriptor
	 * @return void
	 */
	public function onManageWikiCoreAddFormFields(
		IContextSource $context,
		ModuleFactory $moduleFactory,
		string $dbname,
		bool $ceMW,
		array &$formDescriptor
	): void;
}
