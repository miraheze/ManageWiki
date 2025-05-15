<?php

namespace Miraheze\ManageWiki\Helpers\Factories;

use MediaWiki\Config\ServiceOptions;
use Miraheze\CreateWiki\Services\CreateWikiDataFactory;
use Miraheze\ManageWiki\Helpers\Installer;
use Miraheze\ManageWiki\Helpers\SettingsModule;
use Miraheze\ManageWiki\Helpers\Utils\DatabaseUtils;

class SettingsFactory {

	private array $instances = [];

	public function __construct(
		private readonly CreateWikiDataFactory $dataFactory,
		private readonly DatabaseUtils $databaseUtils,
		private readonly Installer $installer,
		private readonly ServiceOptions $options
	) {
	}

	public function newInstance( string $dbname ): SettingsModule {
		return new SettingsModule(
			$this->dataFactory,
			$this->databaseUtils,
			$this->installer,
			$this->options,
			$dbname
		);
	}

	public function getInstance( string $dbname ): SettingsModule {
		$this->instances[$dbname] ??= $this->newInstance( $dbname );
		return $this->instances[$dbname];
	}
}
