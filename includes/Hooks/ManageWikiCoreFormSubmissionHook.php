<?php

namespace Miraheze\ManageWiki\Hooks;

use MediaWiki\Context\IContextSource;
use Miraheze\CreateWiki\Services\RemoteWikiFactory;
use Wikimedia\Rdbms\IDatabase;

interface ManageWikiCoreFormSubmissionHook {

	/**
	 * @param IContextSource $context
	 * @param IDatabase $dbw
	 * @param RemoteWikiFactory $remoteWiki
	 * @param string $dbname
	 * @param array $formData
	 * @return void
	 */
	public function onManageWikiCoreFormSubmission(
		IContextSource $context,
		IDatabase $dbw,
		RemoteWikiFactory $remoteWiki,
		string $dbname,
		array $formData
	): void;
}
