<?php

namespace Miraheze\ManageWiki\Maintenance;

use MediaWiki\MainConfigNames;
use MediaWiki\Maintenance\Maintenance;
use Miraheze\ManageWiki\Helpers\ManageWikiNamespaces;

class AddNamespaces extends Maintenance {

	public function __construct() {
		parent::__construct();

		$this->addOption( 'default', 'Wheather to add the namespace to the \'default\' db name (Defaults to wgDBname).' );
		$this->addOption( 'id', 'The namespace id e.g 1.', true, true );
		$this->addOption( 'name', 'The name of the namespace e.g \'Module\'.', true, true );
		$this->addOption( 'searchable', 'Whether the namespace is searchable.' );
		$this->addOption( 'subpages', 'Whether the namespace has a subpage.' );
		$this->addOption( 'content', 'Whether the namespace has content' );
		$this->addOption( 'contentmodel', 'The content model to use for the namespace.', true, true );
		$this->addOption( 'protection', 'Whether this namespace has protection.', true, true );
		$this->addOption( 'core', 'Whether to allow the namespaces to be renamed or not.' );

		$this->requireExtension( 'ManageWiki' );
	}

	public function execute() {
		$mwNamespaces = new ManageWikiNamespaces( $this->getConfig()->get( MainConfigNames::DBname ) );

		$nsData = [
			'name' => (string)$this->getOption( 'name' ),
			'searchable' => (int)$this->getOption( 'searchable' ),
			'subpages' => (int)$this->getOption( 'subpages' ),
			'protection' => (string)$this->getOption( 'protection' ),
			'content' => (int)$this->getOption( 'content' ),
			'contentmodel' => (string)$this->getOption( 'contentmodel' ),
			'core' => (int)$this->getOption( 'core' )
		];

		$mwNamespaces->modify( (int)$this->getOption( 'id' ), $nsData );
		$mwNamespaces->commit();
	}
}

// @codeCoverageIgnoreStart
return AddNamespaces::class;
// @codeCoverageIgnoreEnd
