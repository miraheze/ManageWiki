<?php

namespace Miraheze\ManageWiki\Helpers\Factories;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\JobQueue\JobQueueGroupFactory;
use MediaWiki\Title\NamespaceInfo;
use Miraheze\ManageWiki\Helpers\ManageWikiNamespaces;
use Miraheze\CreateWiki\Services\CreateWikiDatabaseUtils;
use Miraheze\CreateWiki\Services\CreateWikiDataFactory;

class NamespacesFactory {

	public function __construct(
		private readonly CreateWikiDatabaseUtils $databaseUtils,
		private readonly CreateWikiDataFactory $dataFactory,
		private readonly JobQueueGroupFactory $jobQueueGroupFactory,
		private readonly NamespaceInfo $namespaceInfo,
		private readonly ServiceOptions $options
	) {
	}

	public function newInstance( string $dbname ): ManageWikiNamespaces {
		return new ManageWikiNamespaces(
			$this->databaseUtils,
			$this->dataFactory,
			$this->jobQueueGroupFactory,
			$this->namespaceInfo,
			$this->options,
			$dbname
		);
	}
}
