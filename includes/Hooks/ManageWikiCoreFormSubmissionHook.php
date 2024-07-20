<?php

namespace Miraheze\ManageWiki\Hooks;

use IContextSource;
use Miraheze\CreateWiki\RemoteWiki;
use Wikimedia\Rdbms\DBConnRef;

interface ManageWikiCoreFormSubmissionHook {
	/**
     * Use this hook to modify what happens when someone clicks "submit" in ManageWiki/core
	 * @param IContextSource $context
	 * @param string $dbName
	 * @param DBConnRef $dbw
	 * @param array $formData
	 * @param RemoteWiki &$wiki
	 * @return void
	 */
	public function onManageWikiCoreFormSubmission( $context, $dbName, $dbw, $formData, &$wiki ): void;
}
