<?php

namespace Miraheze\ManageWiki\Hooks;

use MediaWiki\Context\IContextSource;
use Miraheze\ManageWiki\Helpers\ManageWikiModuleFactory;

interface ManageWikiCoreFormSubmissionHook {

	/**
	 * @param IContextSource $context
	 * @param ManageWikiModuleFactory $moduleFactory
	 * @param string $dbname
	 * @param array $formData
	 * @return void
	 */
	public function onManageWikiCoreFormSubmission(
		IContextSource $context,
		ManageWikiModuleFactory $moduleFactory,
		string $dbname,
		array $formData
	): void;
}
