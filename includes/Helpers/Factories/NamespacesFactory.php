<?php

namespace Miraheze\ManageWiki\Helpers\Factories;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\JobQueue\JobQueueGroupFactory;
use MediaWiki\Title\NamespaceInfo;
use Miraheze\ManageWiki\Helpers\NamespacesModule;
use Miraheze\ManageWiki\Helpers\Utils\DatabaseUtils;

class NamespacesFactory {

	public function __construct(
		private readonly DatabaseUtils $databaseUtils,
		private readonly DataFactory $dataFactory,
		private readonly JobQueueGroupFactory $jobQueueGroupFactory,
		private readonly NamespaceInfo $namespaceInfo,
		private readonly ServiceOptions $options
	) {
	}

	public function newInstance( string $dbname ): NamespacesModule {
		return new NamespacesModule(
			$this->databaseUtils,
			$this->dataFactory,
			$this->jobQueueGroupFactory,
			$this->namespaceInfo,
			$this->options,
			$dbname
		);
	}
}
