<?php

namespace Miraheze\ManageWiki\Hooks;

use MediaWiki\Context\IContextSource;
use Miraheze\ManageWiki\Helpers\ModuleFactory;

interface ManageWikiCoreFormSubmissionHook {

	/****
	 * Handles actions to be performed after a ManageWiki core form is submitted.
	 *
	 * Implement this method to process form submission data for a specific wiki, using the provided context and module factory.
	 *
	 * @param IContextSource $context The request context for the form submission.
	 * @param ModuleFactory $moduleFactory Factory for accessing ManageWiki modules.
	 * @param string $dbname Database name of the target wiki.
	 * @param array $formData Data submitted from the ManageWiki core form.
	 */
	public function onManageWikiCoreFormSubmission(
		IContextSource $context,
		ModuleFactory $moduleFactory,
		string $dbname,
		array $formData
	): void;
}
