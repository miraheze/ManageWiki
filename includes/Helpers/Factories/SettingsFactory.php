<?php

namespace Miraheze\ManageWiki\Helpers\Factories;

use MediaWiki\Config\ServiceOptions;
use Miraheze\CreateWiki\Services\CreateWikiDataFactory;
use Miraheze\ManageWiki\DatabaseUtils;
use Miraheze\ManageWiki\Helpers\ManageWikiSettings;

class SettingsFactory {

	public function __construct(
		private readonly CreateWikiDataFactory $dataFactory,
		private readonly DatabaseUtils $databaseUtils,
		private readonly ServiceOptions $options
	) {
	}

	public function newInstance( string $dbname ): ManageWikiSettings {
		return new ManageWikiSettings(
			$this->dataFactory,
			$this->databaseUtils,
			$this->options,
			$dbname
		);
	}
}
