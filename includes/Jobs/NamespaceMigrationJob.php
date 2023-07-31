<?php

namespace Miraheze\ManageWiki\Jobs;

use Job;
use Title;

/**
 * Used on namespace creation and deletion to move pages into and out of namespaces
 */
class NamespaceMigrationJob extends Job {
	/**
	 * @param Title $title
	 * @param string[] $params
	 */
	public function __construct( Title $title, $params ) {
		parent::__construct( 'NamespaceMigrationJob', $params );
	}

	/**
	 * @return bool
	 */
	public function run() {
		$mwn = new NamespaceMigration();
		$mwn->commit( $this->params );

		return true;
	}
}
