<?php

namespace Miraheze\ManageWiki\Helpers\Factories;

use MediaWiki\User\ActorStoreFactory;
use MediaWiki\User\UserGroupManagerFactory;
use Miraheze\CreateWiki\Services\CreateWikiDatabaseUtils;
use Miraheze\CreateWiki\Services\CreateWikiDataFactory;
use Miraheze\ManageWiki\Helpers\ManageWikiPermissions;
use Wikimedia\Message\ITextFormatter;

class PermissionsFactory {

	public function __construct(
		private readonly CreateWikiDatabaseUtils $databaseUtils,
		private readonly CreateWikiDataFactory $dataFactory,
		private readonly ActorStoreFactory $actorStoreFactory,
		private readonly UserGroupManagerFactory $userGroupManagerFactory,
		private readonly ITextFormatter $textFormatter
	) {
	}

	public function newInstance( string $dbname ): ManageWikiPermissions {
		return new ManageWikiPermissions(
			$this->databaseUtils,
			$this->dataFactory,
			$this->actorStoreFactory,
			$this->userGroupManagerFactory,
			$this->textFormatter,
			$dbname
		);
	}
}
