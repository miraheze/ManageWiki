<?php

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

class ChangeDbCluster extends Maintenance {
	private $dbObj = null;

	public function __construct() {
		parent::__construct();
		$this->addOption( 'file', 'Path to file where the wikinames are store. Must be one wikidb name per line. (Optional, fallsback to current dbname)', false, true );
		$this->addOption( 'db-cluster', 'Sets the wikis requested to a different db cluster.', true, true );
	}

	public function execute() {
		global $wgCreateWikiDatabase, $wgDBname;
		
		$this->dbObj = wfGetDB( DB_MASTER, [], $wgCreateWikiDatabase );

		if ( $this->getOption( 'file' ) ) {
			$file = fopen( $this->getOption( 'file' ), 'r' );
			if ( !$file ) {
				$this->fatalError( "Unable to read file, exiting" );
			}
		} else {
			$this->updateDbCluster( $wgDBname );
			return;
		}

		for ( $linenum = 1; !feof( $file ); $linenum++ ) {
			$line = trim( fgets( $file ) );
			if ( $line == '' ) {
				continue;
			}

			$this->updateDbCluster( $line );
		}
	}

	private function updateDbCluster( string $wiki ) {
		$this->dbObj->update(
			'cw_wikis',
			[
				'wiki_dbcluster' => (string)$this->getOption( 'db-cluster' ),
			],
			[
				'wiki_dbname' => $wiki,
			],
			__METHOD__
		);
	}
}

$maintClass = 'ChangeDbCluster';
require_once RUN_MAINTENANCE_IF_MAIN;
