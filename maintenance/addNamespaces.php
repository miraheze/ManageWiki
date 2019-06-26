<?php

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

class ManageWikiAddNamespaces extends Maintenance {
  private $db;

	public function __construct() {
    global $wgCreateWikiDatabase;
		parent::__construct();
		$this->addOption( 'nsdb', 'Name of the namepsace db.', false, true );
		$this->addOption( 'nsid', 'The id for the namespace, e.g 1.', true, true );
    $this->addOption( 'nsname', 'Name of the namespace, e.g Module.', true, true );
    $this->addOption( 'nssearchable', 'Wheather the namespace is searchable.', true, true );
		$this->addOption( 'nssubpages', 'Wheather the namespace has a subpage.', true, true );
		$this->addOption( 'nscontent', 'Wheather the namespace has content', true, true );
    $this->addOption( 'nscontentmodel', 'Content model for the namespace.', true, true );
    $this->addOption( 'nsprotection', 'Wheather this namespace has protection.', true, true );
    $this->addOption( 'nsaliases', 'Wheather the namespace has an alternative name.', true, true );
    $this->addOption( 'nscore', 'Wheather the namespace is provided by mediawiki core.', true, true );
    $this->addOption( 'nsadditional', 'Wheather the namespace provides additional things.', true, true );

    $this->db = wfGetDB( DB_MASTER, [], $wgCreateWikiDatabase );
	}

	public function execute() {
		global $wgDBname;

		if ( !ManageWiki::checkSetup( 'namespaces' ) ) {
			$this->fatalError( 'ManageWiki Namespaces is disabled on this wiki.' );
		}

    $this->insertNamespace();
	}
	
	public function insertNamespace() {
		global $wgDBname;

		$this->db->insert(
			'mw_namespaces',
			[
				'ns_dbname' => $this->getOption( 'nsdb' ) ? (string)$this->getOption( 'nsdb' ) : $wgDBname,
				'ns_namespace_id' => (int)$this->getOption( 'nsid' ),
				'ns_namespace_name' => (string)$this->getOption( 'nsname' ),
				'ns_searchable' => (int)$this->getOption( 'nssearchable' ),
				'ns_subpages' => (int)$this->getOption( 'nssubpages' ),
				'ns_content' => (int)$this->getOption( 'nscontent' ),
        'ns_content_model' => (string)$this->getOption( 'nscontentmodel' ),
				'ns_protection' => (string)$this->getOption( 'nsprotection' ),
				'ns_aliases' => (string)json_encode( $this->getOption( 'nsaliases' ) ),
				'ns_core' => (int)$this->getOption( 'nscore' ),
        'ns_additional' => (string)json_encode( $this->getOption( 'nsadditional' ) ),
			],
			__METHOD__
		);
    
    ManageWikiCDB::changes( 'namespaces' );
	}
}

$maintClass = 'ManageWikiAddNamespaces';
require_once RUN_MAINTENANCE_IF_MAIN;
