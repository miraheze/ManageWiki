<?php

namespace Miraheze\ManageWiki\Jobs;

use Job;
use Miraheze\CreateWiki\Services\CreateWikiDatabaseUtils;
use Wikimedia\Rdbms\IDatabase;

/**
 * Used on namespace creation and deletion to move pages into and out of namespaces
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
		private readonly CreateWikiDatabaseUtils $databaseUtils
	) {
		parent::__construct( self::JOB_NAME, $params );

		$this->action = $params['action'];
		$this->dbname = $params['dbname'];

		$this->nsID = $params['nsID'];
		$this->nsName = $params['nsName'];
		$this->nsNew = $params['nsNew'];

		$this->maintainPrefix = $params['maintainPrefix'];
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
			$nsSearch = 0;
			$pagePrefix = $this->nsName . ':';
			$nsTo = $this->nsID;
		}

		$res = $dbw->select(
			'page',
			[
				'page_title',
				'page_id',
			],
			[
				'page_namespace' => $nsSearch,
				"page_title LIKE '$pagePrefix%'",
			],
			__METHOD__
		);

		foreach ( $res as $row ) {
			$pageTitle = $row->page_title;
			$pageID = $row->page_id;

			if ( $nsSearch === 0 ) {
				$replace = '';
				$newTitle = str_replace( $pagePrefix, $replace, $pageTitle );
			} elseif ( $this->maintainPrefix && $this->action === 'delete' ) {
				$pagePrefix = $this->nsName . ':';
				$replace = '';
				$newTitle = $pagePrefix . str_replace( $pagePrefix, $replace, $pageTitle );
			} else {
				$newTitle = $pageTitle;
			}

			if ( $nsTo !== null && $this->pageExists( $newTitle, $nsTo, $dbw ) ) {
				$newTitle .= '~' . $this->nsName;
			}

			$dbw->update(
				'page',
				[
					'page_namespace' => $nsTo,
					'page_title' => trim( $newTitle, '_' ),
				],
				[
					'page_id' => $pageID,
				],
				__METHOD__
			);

			// Update recentchanges as this is not normally done
			$dbw->update(
				'recentchanges',
				[
					'rc_namespace' => $nsTo,
					'rc_title' => trim( $newTitle, '_' ),
				],
				[
					'rc_namespace' => $nsSearch,
					'rc_title' => $pageTitle,
				],
				__METHOD__
			);
		}

		return true;
	}

	private function pageExists(
		string $pageName,
		int $nsID,
		IDatabase $dbw
	): bool {
		$row = $dbw->selectRow(
			'page',
			'page_title',
			[
				'page_title' => $pageName,
				'page_namespace' => $nsID,
			],
			__METHOD__
		);

		return (bool)$row;
	}
}
