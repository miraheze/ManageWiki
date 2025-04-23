<?php

namespace Miraheze\ManageWiki\Jobs;

use Job;
use Miraheze\CreateWiki\Services\CreateWikiDatabaseUtils;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\IExpression;
use Wikimedia\Rdbms\LikeValue;

class RestoreNamespaceJob extends Job {

	public const JOB_NAME = 'RestoreNamespaceJob';

	private readonly string $dbname;
	private readonly string $nsName;

	private readonly int $nsID;
	private readonly ?int $nsOld;

	public function __construct(
		array $params,
		private readonly CreateWikiDatabaseUtils $databaseUtils
	) {
		parent::__construct( self::JOB_NAME, $params );

		$this->dbname = $params['dbname'];
		$this->nsID = $params['nsID'];
		$this->nsName = $params['nsName'];
		$this->nsOld = $params['nsOld'];
	}

	public function run(): bool {
		$dbw = $this->databaseUtils->getRemoteWikiPrimaryDB( $this->dbname );

		$prefix = $this->nsName . ':';
		$res = $dbw->newSelectQueryBuilder()
			->table( 'page' )
			->fields( [
				'page_id',
				'page_title',
			] )
			->where( [
				$dbw->expr( 'page_title', IExpression::LIKE,
					new LikeValue( $prefix, $dbw->anyString() )
				),
				'page_namespace' => $this->nsID,
			] )
			->caller( __METHOD__ )
			->fetchResultSet();

		foreach ( $res as $row ) {
			$pageID = $row->page_id;
			$oldTitle = $row->page_title;
			$newTitle = $oldTitle;

			// Handle ~nsName and optional digit suffix
			if ( preg_match( '/^(.*)~' . preg_quote( $this->nsName, '/' ) . '(\d*)$/', $newTitle, $matches ) ) {
				$newTitle = $matches[1] . ( $matches[2] ?: '' );
			} else {
				$newTitle = preg_replace( '/~' . preg_quote( $this->nsName, '/' ) . '$/', '', $newTitle );
			}

			// Remove prefix if present
			if ( str_starts_with( $newTitle, $prefix ) ) {
				$newTitle = substr( $newTitle, strlen( $prefix ) );
			}

			// Ensure uniqueness in nsOld
			$baseTitle = $newTitle;
			$counter = 1;
			while ( $this->pageExists( $newTitle, $this->nsOld, $dbw ) ) {
				$newTitle = $baseTitle . $counter;
				$counter++;
			}

			$dbw->newUpdateQueryBuilder()
				->update( 'page' )
				->set( [
					'page_namespace' => $this->nsOld,
					'page_title' => trim( $newTitle, '_' ),
				] )
				->where( [ 'page_id' => $pageID ] )
				->caller( __METHOD__ )
				->execute();

			$dbw->newUpdateQueryBuilder()
				->update( 'recentchanges' )
				->set( [
					'rc_namespace' => $this->nsOld,
					'rc_title' => trim( $newTitle, '_' ),
				] )
				->where( [
					'rc_namespace' => $this->nsID,
					'rc_title' => $oldTitle,
				] )
				->caller( __METHOD__ )
				->execute();
		}

		return true;
	}

	private function pageExists(
		string $title,
		int $namespace,
		IDatabase $dbw
	): bool {
		return (bool)$dbw->newSelectQueryBuilder()
			->select( 'page_id' )
			->from( 'page' )
			->where( [
				'page_namespace' => $namespace,
				'page_title' => $title,
			] )
			->caller( __METHOD__ )
			->fetchRow();
	}
}
