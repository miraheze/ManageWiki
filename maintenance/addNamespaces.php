<?php

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

class ManageWikiAddNamespaces extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->addOption( 'default', 'Wheather to add the namespace to the \'default\' db name (Defaults to wgDBname).' );
		$this->addOption( 'id', 'The namespace id e.g 1.', true, true );
		$this->addOption( 'name', 'The name of the namespace e.g \'Module\'.' );
		$this->addOption( 'searchable', 'Whether the namespace is searchable.' );
		$this->addOption( 'subpages', 'Whether the namespace has a subpage.' );
		$this->addOption( 'content', 'Whether the namespace has content' );
		$this->addOption( 'contentmodel', 'The content model to use for the namespace.' );
		$this->addOption( 'protection', 'Whether this namespace has protection.' );
		$this->addOption( 'core', 'Whether to allow the namespaces to be renamed or not.' );
	}

	public function execute() {
		$id = (int)$this->getOption( 'id' );
		$name = (string)$this->getOption( 'name' );
		$searchable = (int)$this->getOption( 'searchable' );
		$subpages = (int)$this->getOption( 'subpages' );
		$protection = (string)$this->getOption( 'protection' );
		$content = (int)$this->getOption( 'content' );
		$contentmodel = (string)$this->getOption( 'contentmodel' );
		$core = (int)$this->getOption( 'core' );
		$model = (string)$this->getOption( 'contentmodel' );
		$dbname = $this->getOption( 'default' ) ? 'default' : null;

		ManageWikiNamespaces::modifyNamespace( $id, $name, $searchable, $subpages, $protection, $content, $contentmodel, $core, [], [], $dbname );

		ManageWikiCDB::changes( 'namespaces' );
	}
}

$maintClass = 'ManageWikiAddNamespaces';
require_once RUN_MAINTENANCE_IF_MAIN;
