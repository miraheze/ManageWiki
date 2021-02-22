<?php

/**
 * Used on namespace creation and deletion to move pages into and out of namespaces
 */
class NamespaceMigrationJob extends Job {
	public function __construct( $title, $params ) {
		parent::__construct( 'NamespaceMigrationJob', $params );
	}

	public function run() {
		$dbw = wfGetDB( DB_MASTER );

		$maintainPrefix = $this->params['maintainPrefix'];

		if ( $this->params['action'] == 'delete' ) {
			$nsSearch = $this->params['nsID'];
			$pagePrefix = '';
			$nsTo = $this->params['nsNew'];
			$nsContentModel = $this->params['nsNewContentModel'];
		} else {
			$nsSearch = 0;
			$pagePrefix = $this->params['nsName'] . ':';
			$nsTo = $this->params['nsID'];
			$nsContentModel = $this->params['nsContentModel'];
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
			} elseif ( $maintainPrefix && $this->params['action'] == 'delete' ) {
				$pagePrefix = $this->params['nsName'] . ':';
				$replace = '';
				$newTitle = $pagePrefix . str_replace( $pagePrefix, $replace, $pageTitle );
			} else {
				$newTitle = $pageTitle;
			}

			if ( $this->params['action'] !== 'create' && $this->pageExists( $newTitle, $nsTo, $dbw ) ) {
				$newTitle .= '~' . $this->params['nsName'];
			}

			$dbw->update(
				'page',
				[
					'page_namespace' => $nsTo,
					'page_title' => $newTitle,
					'page_content_model' => $nsContentModel
				],
				[
					'page_id' => $pageID
				],
				__METHOD__
			);

			if ( $maintainPrefix && $this->params['action'] == 'delete' ) {
				$dbw->query( "UPDATE content SET content_model = 
					( SELECT model_id FROM content_models WHERE model_name = '{$nsContentModel}' ) 
					WHERE content.content_sha1 IN ( SELECT rev_sha1 FROM revision JOIN page 
					ON revision.rev_page = page.page_id WHERE page.page_title = '{$newTitle}' )"
				);
			} elseif ( $maintainPrefix ) {
				$namespaceIndex = MWNamespace::getCanonicalIndex( strtolower( $this->params['nsName'] ) );
				$namespaceContentModel = MWNamespace::getNamespaceContentModel( $namespaceIndex );

				$dbw->update(
					'page',
					[
						'page_namespace' => $namespaceIndex,
						'page_content_model' => $namespaceContentModel
					],
					[
						'page_id' => $pageID
					],
					__METHOD__
				);

				$dbw->query( "UPDATE content SET content_model = 
					( SELECT model_id FROM content_models WHERE model_name = '{$namespaceContentModel}' ) 
					WHERE content.content_sha1 IN ( SELECT rev_sha1 FROM revision JOIN page 
					ON revision.rev_page = page.page_id WHERE page.page_title = '{$newTitle}' )"
				);
			}

			// Update recentchanges as this is not normally done
			$dbw->update(
				'recentchanges',
				[
					'rc_namespace' => $nsTo,
					'rc_title' => $newTitle
				],
				[
					'rc_namespace' => $nsSearch,
					'rc_title' => $pageTitle
				],
				__METHOD__
			);
		}
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
