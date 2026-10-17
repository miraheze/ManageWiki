<?php

namespace Miraheze\ManageWiki\Maintenance;

use MediaWiki\Maintenance\Maintenance;
use Miraheze\ManageWiki\Helpers\Factories\ModuleFactory;

class DeleteNamespace extends Maintenance {

	private ModuleFactory $moduleFactory;

	public function __construct() {
		parent::__construct();

		$this->addDescription(
			'Deletes a namespace from ManageWiki. ' .
			'Note that any pages remaining in the namespace will be moved to main by default.'
		);
		$this->addOption(
			'id',
			'The namespace id (e.g 1) that will be deleted.',
			true,
			true
		);
		$this->addOption(
			'newNamespace',
			'The namespace id (e.g 1) that any pages will be migrated to.',
			false,
			true
		);
		$this->addOption(
			'maintainPrefix',
			'Whether to preserve the namespace prefix upon migrating',
			false
		);

		$this->requireExtension( 'ManageWiki' );
	}

	private function initServices(): void {
		$services = $this->getServiceContainer();
		$this->moduleFactory = $services->get( 'ManageWikiModuleFactory' );
	}

	public function execute(): void {
		$this->initServices();
		$mwNamespaces = $this->moduleFactory->namespacesLocal();

		$namespaceToDelete = $this->getOption( 'id' );
		$newNamespace = $this->getOption( 'newNamespace', 0 );
		$maintainPrefix = $this->getOption( 'maintainPrefix', false );

		$mwNamespaces->remove( $namespaceToDelete, $newNamespace, $maintainPrefix );
		$mwNamespaces->commit();
	}
}

// @codeCoverageIgnoreStart
return DeleteNamespace::class;
// @codeCoverageIgnoreEnd
