<?php

namespace Miraheze\ManageWiki\Helpers\Factories;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\JobQueue\JobQueueGroupFactory;
use MediaWiki\Title\NamespaceInfo;
use Miraheze\CreateWiki\Services\CreateWikiDataFactory;
use Miraheze\ManageWiki\Helpers\ManageWikiNamespaces;
use Miraheze\ManageWiki\Helpers\Utils\DatabaseUtils;

class NamespacesFactory {

	public function __construct(
		private readonly CreateWikiDataFactory $dataFactory,
		private readonly DatabaseUtils $databaseUtils,
		private readonly JobQueueGroupFactory $jobQueueGroupFactory,
		private readonly NamespaceInfo $namespaceInfo,
		private readonly ServiceOptions $options
	) {
	}

	public function newInstance( string $dbname ): ManageWikiNamespaces {
		return new ManageWikiNamespaces(
			$this->dataFactory,
			$this->databaseUtils,
			$this->jobQueueGroupFactory,
			$this->namespaceInfo,
			$this->options,
			$dbname
		);
	}
}
