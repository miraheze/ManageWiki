<?php

namespace Miraheze\ManageWiki\Helpers\Factories;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\User\ActorStoreFactory;
use MediaWiki\User\UserGroupManagerFactory;
use Miraheze\ManageWiki\Helpers\PermissionsModule;
use Miraheze\ManageWiki\Helpers\Utils\DatabaseUtils;
use Wikimedia\Message\ITextFormatter;

class PermissionsFactory {

	public function __construct(
		private readonly DatabaseUtils $databaseUtils,
		private readonly DataStoreFactory $dataStoreFactory,
		private readonly ActorStoreFactory $actorStoreFactory,
		private readonly UserGroupManagerFactory $userGroupManagerFactory,
		private readonly ITextFormatter $textFormatter,
		private readonly ServiceOptions $options
	) {
	}

	public function newInstance( string $dbname ): PermissionsModule {
		return new PermissionsModule(
			$this->databaseUtils,
			$this->dataStoreFactory,
			$this->actorStoreFactory,
			$this->userGroupManagerFactory,
			$this->textFormatter,
			$this->options,
			$dbname
		);
	}
}
