<?php

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

class ManageWikiAddNamespaces extends Maintenance {
	public function __construct() {
		global $wgCreateWikiDatabase;
		parent::__construct();
		$this->addOption( 'defaultdb', 'Wheather to add the namespace to the \'default\' db name (Defaults to wgDBname.' );
		$this->addOption( 'nsid', 'The id for the namespace, e.g 1.', true, true );
		$this->addOption( 'nsname', 'Name of the namespace, e.g Module.', true, true );
		$this->addOption( 'nssearchable', 'Wheather the namespace is searchable.', true, true );
		$this->addOption( 'nssubpages', 'Wheather the namespace has a subpage.', true, true );
		$this->addOption( 'nscontent', 'Wheather the namespace has content', true, true );
		$this->addOption( 'nscontentmodel', 'Content model for the namespace.', true, true );
		$this->addOption( 'nsprotection', 'Wheather this namespace has protection.', true, true );
		$this->addOption( 'nscore', 'Wheather to allow the namespace to be renamed or not.', true, true );
	}

	public function execute() {
		$id = (int)$this->getOption( 'nsid' );
		$name = (string)$this->getOption( 'nsname' );
		$search = (int)$this->getOption( 'nssearchable' );
		$subpages = (int)$this->getOption( 'nssubpages' );
		$protection = (string)$this->getOption( 'nsprotection' );
		$content = (int)$this->getOption( 'nscontent' );
		$model = (string)$this->getOption( 'nscontentmodel' );
		$core = (int)$this->getOption( 'nscore' );
		$model = (string)$this->getOption( 'nscontentmodel' );
		$dbname = $this->getOption( 'defaultdb' ) ? 'default' : null;

		ManageWikiNamespaces::modifyNamespace( $id, $name, $search, $subpages, $protection, $content, $model, $core, [], [], $dbname );

		ManageWikiCDB::changes( 'namespaces' );
	}
}

$maintClass = 'ManageWikiAddNamespaces';
require_once RUN_MAINTENANCE_IF_MAIN;
