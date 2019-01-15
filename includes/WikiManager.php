<?php
class WikiManager {
	private $dbname = null;
	private $dbw = null;

	public function __construct( $dbname ) {
		global $wgCreateWikiDatabase;

		$dbw = wfGetDB( DB_MASTER, [], $wgCreateWikiDatabase );

		$check = $dbw->selectRow(
			'cw_wikis',
			'wiki_dbname',
			[
				'wiki_dbname' => $dbname
			],
			__METHOD__
		);

		if ( !$check ) {
			throw new Exception( $dbname . ' does not exist!' );
		}

		$this->dbname = $dbname;
		$this->dbw = $dbw;
	}
}
