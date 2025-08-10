<?php

namespace Miraheze\ManageWiki\Helpers\Factories;

use MediaWiki\Config\ServiceOptions;
use Miraheze\ManageWiki\Helpers\SettingsModule;
use Miraheze\ManageWiki\Helpers\Utils\DatabaseUtils;

class SettingsFactory {

	private array $instances = [];

	public function __construct(
		private readonly DatabaseUtils $databaseUtils,
		private readonly DataFactory $dataFactory,
		private readonly InstallerFactory $installerFactory,
		private readonly ServiceOptions $options
	) {
	}

	public function newInstance( string $dbname ): SettingsModule {
		return new SettingsModule(
			$this->databaseUtils,
			$this->dataFactory,
			$this->installerFactory,
			$this->options,
			$dbname
		);
	}

	public function getInstance( string $dbname ): SettingsModule {
		$this->instances[$dbname] ??= $this->newInstance( $dbname );
		return $this->instances[$dbname];
	}
}
