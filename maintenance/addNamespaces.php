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
		$this->addOption( 'id', 'The id for the namespace e.g 1.', true, true );
		$this->addOption( 'name', 'Name of the namespace e.g Module.', true, true );
		$this->addOption( 'searchable', 'Wheather the namespace is searchable.', true, true );
		$this->addOption( 'subpages', 'Wheather the namespace has a subpage.', true, true );
		$this->addOption( 'content', 'Wheather the namespace has content', true, true );
		$this->addOption( 'contentmodel', 'Content model for the namespace.', true, true );
		$this->addOption( 'protection', 'Wheather this namespace has protection.', true, true );
		$this->addOption( 'core', 'Wheather to allow the namespaces to be renamed or not.', true, true );
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
