<?php

namespace Miraheze\ManageWiki\Helpers\Factories;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\JobQueue\JobQueueGroupFactory;
use Miraheze\CreateWiki\Hooks\CreateWikiHookRunner;
use Miraheze\CreateWiki\Services\CreateWikiDatabaseUtils;
use Miraheze\CreateWiki\Services\CreateWikiDataFactory;
use Miraheze\CreateWiki\Services\RemoteWikiFactory;

class CoreFactory {

	public function __construct(
		private readonly CreateWikiDatabaseUtils $databaseUtils,
		private readonly CreateWikiDataFactory $dataFactory,
		private readonly CreateWikiHookRunner $hookRunner,
		private readonly JobQueueGroupFactory $jobQueueGroupFactory,
		private readonly ServiceOptions $options
	) {
	}

	public function newInstance( string $dbname ): RemoteWikiFactory {
		// Temporary, we will eventually allow extensions to register
		// the provider that we use for ManageWiki core.
		return ( new RemoteWikiFactory(
			$this->databaseUtils,
			$this->dataFactory,
			$this->hookRunner,
			$this->jobQueueGroupFactory,
			$this->options,
		) )->newInstance( $dbname );
	}
}
