<?php

namespace Miraheze\ManageWiki\Helpers;

use MediaWiki\MediaWikiServices;
use MediaWiki\Pager\TablePager;
use MediaWiki\SpecialPage\SpecialPage;

class ManageWikiDeletedWikiPager extends TablePager {

	public function __construct( $page ) {
		$config = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'managewiki' );
		$this->mDb = MediaWikiServices::getInstance()->getConnectionProvider()
			->getReplicaDatabase( 'virtual-createwiki' );

		parent::__construct( $page->getContext(), $page->getLinkRenderer() );
	}

	public function getFieldNames() {
		static $headers = null;

		$headers = [
			'wiki_dbname' => 'managewiki-label-dbname',
			'wiki_creation' => 'managewiki-label-creationdate',
			'wiki_deleted_timestamp' => 'managewiki-label-deletiondate',
			'wiki_deleted' => 'managewiki-label-undeletewiki'
		];

		foreach ( $headers as &$msg ) {
			$msg = $this->msg( $msg )->text();
		}

		return $headers;
	}

	/**
	 * Safely HTML-escape $value
	 *
	 * @param string $value
	 * @return string
	 */
	private static function escape( $value ) {
		return htmlspecialchars( $value, ENT_QUOTES );
	}

	public function formatValue( $name, $value ) {
		$row = $this->mCurrentRow;

		switch ( $name ) {
			case 'wiki_dbname':
				$formatted = $this->escape( $row->wiki_dbname );
				break;
			case 'wiki_creation':
				$formatted = $this->escape( wfTimestamp( TS_RFC2822, (int)$row->wiki_creation ) );
				break;
			case 'wiki_deleted_timestamp':
				$formatted = $this->escape( wfTimestamp( TS_RFC2822, (int)$row->wiki_deleted_timestamp ) );
				break;
			case 'wiki_deleted':
				$formatted = $this->getLinkRenderer()->makeExternalLink(
					SpecialPage::getTitleFor( 'ManageWiki', 'core' )->getFullURL() . '/' . $row->wiki_dbname,
					$this->msg( 'managewiki-label-goto' )->text(),
					SpecialPage::getTitleFor( 'ManageWiki', 'core' )
				);
				break;
			default:
				$formatted = $this->escape( "Unable to format $name" );
				break;
		}
		return $formatted;
	}

	public function getQueryInfo() {
		return [
			'tables' => [
				'cw_wikis'
			],
			'fields' => [
				'wiki_dbname',
				'wiki_creation',
				'wiki_deleted',
				'wiki_deleted_timestamp'
			],
			'conds' => [
				'wiki_deleted' => 1
			],
			'joins_conds' => [],
		];
	}

	public function getDefaultSort() {
		return 'wiki_dbname';
	}

	public function isFieldSortable( $name ) {
		return true;
	}
}
