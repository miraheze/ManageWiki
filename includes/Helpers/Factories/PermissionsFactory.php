<?php

namespace Miraheze\ManageWiki\Helpers\Factories;

use MediaWiki\User\ActorStoreFactory;
use MediaWiki\User\UserGroupManagerFactory;
use Miraheze\CreateWiki\Services\CreateWikiDataFactory;
use Miraheze\ManageWiki\Helpers\ManageWikiPermissions;
use Miraheze\ManageWiki\Helpers\Utils\DatabaseUtils;
use Wikimedia\Message\ITextFormatter;

class PermissionsFactory {

	public function __construct(
		private readonly CreateWikiDataFactory $dataFactory,
		private readonly DatabaseUtils $databaseUtils,
		private readonly ActorStoreFactory $actorStoreFactory,
		private readonly UserGroupManagerFactory $userGroupManagerFactory,
		private readonly ITextFormatter $textFormatter
	) {
	}

	public function newInstance( string $dbname ): ManageWikiPermissions {
		return new ManageWikiPermissions(
			$this->dataFactory,
			$this->databaseUtils,
			$this->actorStoreFactory,
			$this->userGroupManagerFactory,
			$this->textFormatter,
			$dbname
		);
	}
}
