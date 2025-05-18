<?php

namespace Miraheze\ManageWiki\Helpers\Factories;

use MediaWiki\SiteStats\SiteStatsInit;
use Miraheze\ManageWiki\Helpers\Requirements;
use Miraheze\ManageWiki\Helpers\Utils\DatabaseUtils;

class RequirementsFactory {

	public function __construct(
		private readonly CoreFactory $coreFactory,
		private readonly DatabaseUtils $databaseUtils,
		private readonly SettingsFactory $settingsFactory
	) {
	}

	public function getRequirements( string $dbname ): Requirements {
		$dbr = $this->databaseUtils->getRemoteWikiReplicaDB( $dbname );
		$siteStatsInit = new SiteStatsInit( $dbr );
		return new Requirements(
			$this->coreFactory,
			$this->settingsFactory,
			$siteStatsInit,
			$dbname
		);
	}
}
