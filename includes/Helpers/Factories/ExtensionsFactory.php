<?php

namespace Miraheze\ManageWiki\Helpers\Factories;

use MediaWiki\Config\ServiceOptions;
use Miraheze\CreateWiki\Services\CreateWikiDataFactory;
use Miraheze\ManageWiki\DatabaseUtils;
use Miraheze\ManageWiki\Helpers\ManageWikiExtensions;
use Psr\Log\LoggerInterface;

class ExtensionsFactory {

	public function __construct(
		private readonly CreateWikiDataFactory $dataFactory,
		private readonly DatabaseUtils $databaseUtils,
		private readonly LoggerInterface $logger,
		private readonly ServiceOptions $options
	) {
	}

	public function newInstance( string $dbname ): ManageWikiExtensions {
		return new ManageWikiExtensions(
			$this->dataFactory,
			$this->databaseUtils,
			$this->logger,
			$this->options,
			$dbname
		);
	}
}
