<?php

namespace Miraheze\ManageWiki\Hooks;

use IContextSource;
use Miraheze\CreateWiki\RemoteWiki;
use Wikimedia\Rdbms\DBConnRef;

interface ManageWikiCoreFormSubmissionHook {
	/**
	 * @param IContextSource $context
	 * @param string $dbName
	 * @param DBConnRef $dbw
	 * @param RemoteWiki $wiki
	 * @param array $formData
	 * @return void
	 */
	public function onManageWikiCoreFormSubmission( $context, $dbName, $dbw, $wiki, $formData ): void;
}
