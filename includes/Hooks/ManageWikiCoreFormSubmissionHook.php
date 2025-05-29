<?php

namespace Miraheze\ManageWiki\Hooks;

use MediaWiki\Context\IContextSource;
use Miraheze\ManageWiki\Helpers\Factories\ModuleFactory;

/**
 * Hook interface for handling ManageWiki core form submission.
 *
 * This hook is triggered after a ManageWiki form is submitted and before the
 * database values are finalized, allowing extensions to process or save additional
 * core-related data based on user input.
 */
interface ManageWikiCoreFormSubmissionHook {

	/**
	 * Allows extensions to handle submitted core form data in ManageWiki.
	 *
	 * Called during core form submission, after built-in fields have been processed.
	 * Useful for saving additional state, custom field processing, or overriding default logic.
	 *
	 * @param IContextSource $context
	 *   The current request context, used to check user permissions or access localized messages.
	 * @param ModuleFactory $moduleFactory
	 *   A factory for retrieving core and module-specific configuration objects for the target wiki.
	 * @param string $dbname
	 *   The database name of the target wiki being managed (e.g., "examplewiki").
	 * @param array $formData
	 *   The submitted form data as an associative array. This contains all field values including
	 *   both core and extension-defined fields.
	 *
	 * @return void This hook must not abort, it must return no value.
	 */
	public function onManageWikiCoreFormSubmission(
		IContextSource $context,
		ModuleFactory $moduleFactory,
		string $dbname,
		array $formData
	): void;
}
