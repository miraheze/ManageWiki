<?php

namespace Miraheze\ManageWiki\Helpers\Factories;

use Miraheze\ManageWiki\Helpers\Requirements;
use Miraheze\ManageWiki\Helpers\Utils\DatabaseUtils;

class RequirementsFactory {

	public function __construct(
		private readonly DatabaseUtils $databaseUtils,
		private readonly CoreFactory $coreFactory,
		private readonly SettingsFactory $settingsFactory
	) {
	}

	public function getRequirements( string $dbname ): Requirements {
		return new Requirements(
			$this->databaseUtils,
			$this->coreFactory,
			$this->settingsFactory,
			$dbname
		);
	}
}
