<?php

namespace Miraheze\ManageWiki\Helpers\Factories;

use MediaWiki\Config\ServiceOptions;
use Miraheze\CreateWiki\Services\CreateWikiDataFactory;
use Miraheze\ManageWiki\Helpers\SettingsModule;
use Miraheze\ManageWiki\Helpers\Utils\DatabaseUtils;

class SettingsFactory {

	public function __construct(
		private readonly CreateWikiDataFactory $dataFactory,
		private readonly DatabaseUtils $databaseUtils,
		private readonly ServiceOptions $options
	) {
	}

	public function newInstance( string $dbname ): SettingsModule {
		return new SettingsModule(
			$this->dataFactory,
			$this->databaseUtils,
			$this->options,
			$dbname
		);
	}
}
