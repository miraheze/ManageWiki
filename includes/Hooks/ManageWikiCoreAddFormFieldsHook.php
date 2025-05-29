<?php

namespace Miraheze\ManageWiki\Hooks;

use MediaWiki\Context\IContextSource;
use Miraheze\ManageWiki\Helpers\Factories\ModuleFactory;

/**
 * Hook interface for adding or modifying core ManageWiki form fields.
 *
 * This hook is triggered during the construction of the core ManageWiki form descriptor,
 * allowing extensions to add or modify form fields for ManageWiki core.
 */
interface ManageWikiCoreAddFormFieldsHook {

	/**
	 * Allows extensions to add or modify fields in the ManageWiki core form descriptor.
	 *
	 * Called after core fields like sitename, language, privacy settings, etc. are added,
	 * and before rendering the ManageWiki settings form. Useful for customizing the form
	 * or introducing additional settings from extensions.
	 *
	 * @param IContextSource $context
	 *   The request context used to determine user permissions and message localization.
	 * @param ModuleFactory $moduleFactory
	 *   The module factory used to fetch per-wiki configuration and enabled status.
	 * @param string $dbname
	 *   The target wiki's database name (e.g., "examplewiki").
	 * @param bool $ceMW
	 *   Whether the current user has permission to edit ManageWiki.
	 * @param array &$formDescriptor
	 *   The HTMLForm descriptor array. This is passed by reference and may be modified
	 *   to add new form fields or update existing ones. Array keys should be unique field names,
	 *   and values should conform to HTMLForm field configuration.
	 *
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
