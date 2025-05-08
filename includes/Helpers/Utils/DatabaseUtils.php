<?php

namespace Miraheze\ManageWiki\Helpers\Utils;

use MediaWiki\WikiMap\WikiMap;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\IReadableDatabase;

class DatabaseUtils {

	public function __construct(
		private readonly IConnectionProvider $connectionProvider
	) {
	}

	public function getCentralWikiID(): bool|string {
		return $this->getCentralWikiReplicaDB()->getDomainID();
	}

	public function getCentralWikiPrimaryDB(): IDatabase {
		return $this->connectionProvider->getPrimaryDatabase( 'virtual-managewiki-central' );
	}

	public function getCentralWikiReplicaDB(): IReadableDatabase {
		return $this->connectionProvider->getReplicaDatabase( 'virtual-managewiki-central' );
	}

	public function getGlobalPrimaryDB(): IDatabase {
		return $this->connectionProvider->getPrimaryDatabase( 'virtual-managewiki' );
	}

	public function getGlobalReplicaDB(): IReadableDatabase {
		return $this->connectionProvider->getReplicaDatabase( 'virtual-managewiki' );
	}

	public function getRemoteWikiPrimaryDB( string $dbname ): IDatabase {
		return $this->connectionProvider->getPrimaryDatabase( $dbname );
	}

	public function getRemoteWikiReplicaDB( string $dbname ): IReadableDatabase {
		return $this->connectionProvider->getReplicaDatabase( $dbname );
	}

	public function isCurrentWikiCentral(): bool {
		return WikiMap::isCurrentWikiDbDomain( $this->getCentralWikiID() );
	}

	public function isRemoteWikiCentral( string $dbname ): bool {
		return $dbname === $this->getCentralWikiID();
	}
}
