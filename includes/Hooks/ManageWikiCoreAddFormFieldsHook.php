<?php

namespace Miraheze\ManageWiki\Hooks;

use MediaWiki\Context\IContextSource;
use Miraheze\ManageWiki\Helpers\ModuleFactory;

interface ManageWikiCoreAddFormFieldsHook {

	/**
	 * Allows modification or addition of fields to the ManageWiki core form descriptor.
	 *
	 * Implementations can use this hook to customize the form fields presented during ManageWiki core form processing for a specific wiki.
	 *
	 * @param string $dbname Database name of the target wiki.
	 * @param bool $ceMW Indicates whether Centralized Extensions for ManageWiki are enabled.
	 * @param array &$formDescriptor Reference to the form descriptor array to be modified.
	 */
	public function onManageWikiCoreAddFormFields(
		IContextSource $context,
		ModuleFactory $moduleFactory,
		string $dbname,
		bool $ceMW,
		array &$formDescriptor
	): void;
}
