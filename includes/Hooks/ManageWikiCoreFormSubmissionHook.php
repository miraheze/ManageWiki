<?php

namespace Miraheze\ManageWiki\Hooks;

use MediaWiki\Context\IContextSource;
use Miraheze\ManageWiki\Helpers\ConfigModuleFactory;

interface ManageWikiCoreFormSubmissionHook {

	/**
	 * @param IContextSource $context
	 * @param ConfigModuleFactory $moduleFactory 
	 * @param string $dbname
	 * @param array $formData
	 * @return void
	 */
	public function onManageWikiCoreFormSubmission(
		IContextSource $context,
		ConfigModuleFactory $moduleFactory,
		string $dbname,
		array $formData
	): void;
}
