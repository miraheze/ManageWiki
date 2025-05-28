<?php

namespace Miraheze\ManageWiki\Hooks;

use MediaWiki\Context\IContextSource;
use Miraheze\ManageWiki\Helpers\Factories\ModuleFactory;

interface ManageWikiCoreFormSubmissionHook {

	/**
	 * @param IContextSource $context
	 * @param ModuleFactory $moduleFactory
	 * @param string $dbname
	 * @param array $formData
	 * @return void
	 */
	public function onManageWikiCoreFormSubmission(
		IContextSource $context,
		ModuleFactory $moduleFactory,
		string $dbname,
		array $formData
	): void;
}
