<?php

namespace Miraheze\ManageWiki\Hooks;

use MediaWiki\Context\IContextSource;
use Miraheze\CreateWiki\Services\RemoteWikiFactory;
use Wikimedia\Rdbms\IDatabase;

interface ManageWikiCoreFormSubmissionHook {

	/**
	 * @param IContextSource $context
	 * @param string $dbName
	 * @param IDatabase $dbw
	 * @param array $formData
	 * @param RemoteWikiFactory &$remoteWiki
	 * @return void
	 */
	public function onManageWikiCoreFormSubmission( $context, $dbName, $dbw, $formData, &$remoteWiki ): void;
}
