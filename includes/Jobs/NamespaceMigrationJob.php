<?php

namespace Miraheze\ManageWiki\Jobs;

use Job;
use Miraheze\ManageWiki\Helpers\Utils\DatabaseUtils;
use stdClass;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\IExpression;
use Wikimedia\Rdbms\LikeValue;
use function str_replace;
use function trim;
use const NS_MAIN;

/**
 * Used on namespace rename and deletion to move pages in and out of namespaces.
 */
class NamespaceMigrationJob extends Job {

	public const JOB_NAME = 'NamespaceMigrationJob';

	private readonly string $action;
	private readonly string $dbname;
	private readonly string $nsName;

	private readonly bool $maintainPrefix;

	private readonly int $nsID;
	private readonly ?int $nsNew;

	public function __construct(
		array $params,
		private readonly DatabaseUtils $databaseUtils
	) {
		parent::__construct( self::JOB_NAME, $params );

		// Action will either be 'delete' or 'rename'
		$this->action = $params['action'];
		$this->dbname = $params['dbname'];

		$this->nsID = $params['nsID'];
		$this->nsName = $params['nsName'];
		$this->nsNew = $params['nsNew'];

		$this->maintainPrefix = $params['maintainPrefix'];
	}

	/** @inheritDoc */
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
			if ( !$row instanceof stdClass ) {
				// Skip unexpected row
				continue;
			}

			$pageTitle = $row->page_title;
			$pageID = $row->page_id;

			if ( $nsSearch === NS_MAIN ) {
				$replace = '';
				$newTitle = str_replace( $pagePrefix, $replace, $pageTitle );
			} elseif ( $this->maintainPrefix && $this->action === 'delete' ) {
				$pagePrefix = $this->nsName . ':';
				$replace = '';
				$newTitle = $pagePrefix . str_replace( $pagePrefix, $replace, $pageTitle );
			} else {
				$newTitle = $pageTitle;
			}

			if ( $nsTo !== null ) {
				$baseTitle = $newTitle;
				$suffix = '~' . $this->nsName;
				$counter = 1;

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
