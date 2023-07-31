<?php

namespace Miraheze\ManageWiki;

use MediaWiki\MediaWikiServices;

/**
 * Common logic for migrating namespaces.
 */
class NamespaceMigration {

	public function commit( array $params ) {
		$dbw = MediaWikiServices::getInstance()
			->getDBLoadBalancer()
			->getMaintenanceConnectionRef( DB_PRIMARY );

		$maintainPrefix = $params['maintainPrefix'];

		if ( $params['action'] == 'delete' ) {
			$nsSearch = $params['nsID'];
			$pagePrefix = '';
			$nsTo = $params['nsNew'];
		} else {
			$nsSearch = 0;
			$pagePrefix = $params['nsName'] . ':';
			$nsTo = $params['nsID'];
		}

		$res = $dbw->select(
			'page',
			[
				'page_title',
				'page_id',
			],
			[
				'page_namespace' => $nsSearch,
				"page_title LIKE '$pagePrefix%'"
			],
			__METHOD__
		);

		foreach ( $res as $row ) {
			$pageTitle = $row->page_title;
			$pageID = $row->page_id;

			if ( $nsSearch == 0 ) {
				$replace = '';
				$newTitle = str_replace( $pagePrefix, $replace, $pageTitle );
			} elseif ( $maintainPrefix && $params['action'] == 'delete' ) {
				$pagePrefix = $params['nsName'] . ':';
				$replace = '';
				$newTitle = $pagePrefix . str_replace( $pagePrefix, $replace, $pageTitle );
			} else {
				$newTitle = $pageTitle;
			}

			if ( $params['action'] !== 'create' && $this->pageExists( $newTitle, $nsTo, $dbw ) ) {
				$newTitle .= '~' . $params['nsName'];
			}

			$dbw->update(
				'page',
				[
					'page_namespace' => $nsTo,
					'page_title' => trim( $newTitle, '_' ),
				],
				[
					'page_id' => $pageID
				],
				__METHOD__
			);

			// Update recentchanges as this is not normally done
			$dbw->update(
				'recentchanges',
				[
					'rc_namespace' => $nsTo,
					'rc_title' => trim( $newTitle, '_' )
				],
				[
					'rc_namespace' => $nsSearch,
					'rc_title' => $pageTitle
				],
				__METHOD__
			);
		}

		return true;
	}

	private function pageExists( $pageName, $nsID, $dbw ) {
		$row = $dbw->selectRow(
			'page',
			'page_title',
			[
				'page_title' => $pageName,
				'page_namespace' => $nsID
			],
			__METHOD__
		);

		return (bool)$row;
	}
}
