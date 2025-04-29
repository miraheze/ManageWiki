<?php

namespace Miraheze\ManageWiki\Jobs;

use Job;
use Miraheze\CreateWiki\Services\CreateWikiDatabaseUtils;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\IExpression;
use Wikimedia\Rdbms\LikeValue;

/**
 * Used on namespace rename and deletion to move pages in and out of namespaces.
 */
class NamespaceMigrationJob extends Job {

	public const JOB_NAME = 'NamespaceMigrationJob';

	private readonly string $action;
	private readonly string $dbname;
	private readonly string $nsName;

	private readonly int $nsID;
	private readonly ?int $nsNew;

	public function __construct(
		array $params,
		private readonly CreateWikiDatabaseUtils $databaseUtils
	) {
		parent::__construct( self::JOB_NAME, $params );

		// Action will either be 'delete' or 'rename'
		$this->action = $params['action'];
		$this->dbname = $params['dbname'];

		$this->nsID = $params['nsID'];
		$this->nsName = $params['nsName'];
		$this->nsNew = $params['nsNew'];
	}

	/**
	 * @return bool
	 */
	public function run(): bool {
		$dbw = $this->databaseUtils->getRemoteWikiPrimaryDB( $this->dbname );

		if ( $this->action === 'delete' ) {
			$nsSearch = $this->nsID;
			$pagePrefix = '';
			$nsTo = $this->nsNew;
		} else {
			$nsSearch = NS_MAIN;
			$pagePrefix = $this->nsName . ':';
			$nsTo = $this->nsID;
		}

		$res = $dbw->newSelectQueryBuilder()
			->table( 'page' )
			->fields( [
				'page_title',
				'page_id',
			] )
			->where( [
				$dbw->expr( 'page_title', IExpression::LIKE,
					new LikeValue( $pagePrefix, $dbw->anyString() )
				),
				'page_namespace' => $nsSearch,
			] )
			->caller( __METHOD__ )
			->fetchResultSet();

		foreach ( $res as $row ) {
			$pageTitle = $row->page_title;
			$pageID = $row->page_id;

			$newTitle = $pageTitle;
			if ( $nsTo !== null ) {
				$baseTitle = $newTitle;
				$suffix = '~' . $this->nsName;
				$counter = 1;

				if ( $this->action === 'delete' ) {
					$baseTitle .= $suffix;
				}

				while ( $this->pageExists( $newTitle, $nsTo, $dbw ) ) {
					$newTitle = $baseTitle . $suffix;
					if ( $counter > 1 ) {
						$newTitle .= "($counter)";
					}
					$counter++;
				}
			}

			$dbw->newUpdateQueryBuilder()
				->update( 'page' )
				->set( [
					'page_namespace' => $nsTo,
					'page_title' => trim( $newTitle, '_' ),
				] )
				->where( [ 'page_id' => $pageID ] )
				->caller( __METHOD__ )
				->execute();

			// Update recentchanges as this is not normally done
			$dbw->newUpdateQueryBuilder()
				->update( 'recentchanges' )
				->set( [
					'rc_namespace' => $nsTo,
					'rc_title' => trim( $newTitle, '_' ),
				] )
				->where( [
					'rc_namespace' => $nsSearch,
					'rc_title' => $pageTitle,
				] )
				->caller( __METHOD__ )
				->execute();
		}

		return true;
	}

	private function pageExists(
		string $pageName,
		int $nsID,
		IDatabase $dbw
	): bool {
		return (bool)$dbw->newSelectQueryBuilder()
			->select( 'page_title' )
			->from( 'page' )
			->where( [
				'page_title' => $pageName,
				'page_namespace' => $nsID,
			] )
			->caller( __METHOD__ )
			->fetchRow();
	}
}
