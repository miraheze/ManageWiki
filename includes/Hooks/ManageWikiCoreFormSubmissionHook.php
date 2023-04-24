<?php

namespace Miraheze\ManageWiki\Hooks;

use Wikimedia\Rdbms\DBConnRef;

interface ManageWikiCoreFormSubmissionHook {
	/**
	 * @param string $dbName
	 * @param DBConnRef $dbw
	 * @param array $formData
	 * @return void
	 */
	public function onManageWikiCoreFormSubmission( $dbName, $dbw, $formData ): void;
}
