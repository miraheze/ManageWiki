<?php

namespace Miraheze\ManageWiki\Helpers\Factories;

use LocalisationCache;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\JobQueue\JobQueueGroupFactory;
use MediaWiki\Title\NamespaceInfo;
use Miraheze\ManageWiki\Helpers\NamespacesModule;
use Miraheze\ManageWiki\Helpers\Utils\DatabaseUtils;
use Psr\Log\LoggerInterface;

class NamespacesFactory {

	public function __construct(
		private readonly DatabaseUtils $databaseUtils,
		private readonly DataFactory $dataFactory,
		private readonly LoggerInterface $logger,
		private readonly JobQueueGroupFactory $jobQueueGroupFactory,
		private readonly LocalisationCache $localisationCache,
		private readonly NamespaceInfo $namespaceInfo,
		private readonly ServiceOptions $options
	) {
	}

	public function newInstance( string $dbname ): NamespacesModule {
		return new NamespacesModule(
			$this->databaseUtils,
			$this->dataFactory,
			$this->jobQueueGroupFactory,
			$this->localisationCache,
			$this->logger,
			$this->namespaceInfo,
			$this->options,
			$dbname
		);
	}
}
