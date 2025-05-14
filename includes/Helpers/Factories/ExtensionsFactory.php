<?php

namespace Miraheze\ManageWiki\Helpers\Factories;

use MediaWiki\Config\ServiceOptions;
use Miraheze\CreateWiki\Services\CreateWikiDataFactory;
use Miraheze\ManageWiki\Helpers\ExtensionsModule;
use Miraheze\ManageWiki\Helpers\Utils\DatabaseUtils;
use Psr\Log\LoggerInterface;

class ExtensionsFactory {

	public function __construct(
		private readonly CreateWikiDataFactory $dataFactory,
		private readonly DatabaseUtils $databaseUtils,
		private readonly LoggerInterface $logger,
		private readonly ServiceOptions $options
	) {
	}

	public function newInstance( string $dbname ): ExtensionsModule {
		return new ExtensionsModule(
			$this->dataFactory,
			$this->databaseUtils,
			$this->logger,
			$this->options,
			$dbname
		);
	}
}
