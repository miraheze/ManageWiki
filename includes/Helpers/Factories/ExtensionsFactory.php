<?php

namespace Miraheze\ManageWiki\Helpers\Factories;

use MediaWiki\Config\ServiceOptions;
use Miraheze\CreateWiki\Services\CreateWikiDataFactory;
use Miraheze\ManageWiki\Helpers\ExtensionsModule;
use Miraheze\ManageWiki\Helpers\Requirements;
use Miraheze\ManageWiki\Helpers\Utils\DatabaseUtils;
use Psr\Log\LoggerInterface;

class ExtensionsFactory {

	public function __construct(
		private readonly CreateWikiDataFactory $dataFactory,
		private readonly DatabaseUtils $databaseUtils,
		private readonly InstallerFactory $installerFactory,
		private readonly LoggerInterface $logger,
		private readonly Requirements $requirements,
		private readonly ServiceOptions $options
	) {
	}

	public function newInstance( string $dbname ): ExtensionsModule {
		return new ExtensionsModule(
			$this->dataFactory,
			$this->databaseUtils,
			$this->installerFactory,
			$this->logger,
			$this->requirements,
			$this->options,
			$dbname
		);
	}
}
