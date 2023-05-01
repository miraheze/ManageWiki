<?php

namespace Miraheze\ManageWiki\Hooks;

use IContextSource;
use Wikimedia\Rdbms\DBConnRef;

interface ManageWikiCoreFormSubmissionHook {
	/**
	 * @param IContextSource $context
	 * @param string $dbName
	 * @param DBConnRef $dbw
	 * @param array $formData
	 * @return void
	 */
	public function onManageWikiCoreFormSubmission( $context, $dbName, $dbw, $formData ): void;
}
