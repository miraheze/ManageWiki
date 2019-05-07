<?php
class ManageWikiDeletedWikiPager extends TablePager {
	function __construct() {
		global $wgCreateWikiDatabase;

		parent::__construct( $this->getContext() );
		$this->mDb = wfGetDB( DB_REPLICA, [], $wgCreateWikiDatabase );
	}

	function getFieldNames() {
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

	function formatValue( $name, $value ) {
		$row = $this->mCurrentRow;

		switch ( $name ) {
			case 'wiki_dbname':
				$formatted = $row->wiki_dbname;
				break;
			case 'wiki_creation':
				$formatted = wfTimestamp( TS_RFC2822, (int)$row->wiki_creation );
				break;
			case 'wiki_deleted_timestamp':
				$formatted = wfTimestamp( TS_RFC2822, (int)$row->wiki_deleted_timestamp );
				break;
			case 'wiki_deleted':
				$formatted = Linker::makeExternalLink( SpecialPage::getTitleFOr( 'ManageWiki' )->getFullURL() . '/' . $row->wiki_dbname, wfMessage( 'managewiki-label-goto' )->text() );
				break;
			default:
				$formatted = "Unable to format $name";
				break;
		}
		return $formatted;
	}

	function getQueryInfo() {
		$info = [
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

		return $info;
	}

	function getDefaultSort() {
		return 'wiki_dbname';
	}

	function isFieldSortable( $name ) {
		return true;
	}
}
