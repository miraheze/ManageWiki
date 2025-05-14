<?php

namespace Miraheze\ManageWiki;

use MediaWiki\Context\IContextSource;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Pager\TablePager;
use MediaWiki\SpecialPage\SpecialPage;
use Miraheze\ManageWiki\Helpers\Utils\DatabaseUtils;

class DeletedWikisPager extends TablePager {

	public function __construct(
		DatabaseUtils $databaseUtils,
		IContextSource $context,
		LinkRenderer $linkRenderer
	) {
		parent::__construct( $context, $linkRenderer );
		$this->mDb = $databaseUtils->getGlobalReplicaDB();
	}

	/** @inheritDoc */
	public function getFieldNames(): array {
		return [
			'wiki_dbname' => $this->msg( 'managewiki-label-dbname' )->text(),
			'wiki_creation' => $this->msg( 'managewiki-label-creationdate' )->text(),
			'wiki_deleted_timestamp' => $this->msg( 'managewiki-label-deletiondate' )->text(),
			'wiki_deleted' => $this->msg( 'managewiki-label-undeletewiki' )->text(),
		];
	}

	/** @inheritDoc */
	public function formatValue( $name, $value ): string {
		$row = $this->getCurrentRow();

		switch ( $name ) {
			case 'wiki_dbname':
				$formatted = $this->escape( $row->wiki_dbname );
				break;
			case 'wiki_creation':
				$formatted = $this->escape( $this->getLanguage()->userTimeAndDate(
					$row->wiki_creation, $this->getUser()
				) );
				break;
			case 'wiki_deleted_timestamp':
				$formatted = $this->escape( $this->getLanguage()->userTimeAndDate(
					$row->wiki_deleted_timestamp, $this->getUser()
				) );
				break;
			case 'wiki_deleted':
				$formatted = $this->getLinkRenderer()->makeExternalLink(
					SpecialPage::getTitleFor( 'ManageWiki', "core/{$row->wiki_dbname}" )->getFullURL(),
					$this->msg( 'managewiki-label-goto' ),
					SpecialPage::getTitleFor( 'ManageWiki', 'core' )
				);
				break;
			default:
				$formatted = $this->escape( "Unable to format $name" );
		}

		return $formatted;
	}

	/**
	 * Safely HTML-escapes $value
	 */
	private function escape( string $value ): string {
		return htmlspecialchars( $value, ENT_QUOTES );
	}

	/** @inheritDoc */
	public function getQueryInfo(): array {
		return [
			'tables' => [
				'cw_wikis',
			],
			'fields' => [
				'wiki_dbname',
				'wiki_creation',
				'wiki_deleted',
				'wiki_deleted_timestamp',
			],
			'conds' => [
				'wiki_deleted' => 1,
			],
			'joins_conds' => [],
		];
	}

	/** @inheritDoc */
	public function getDefaultSort(): string {
		return 'wiki_dbname';
	}

	/** @inheritDoc */
	public function isFieldSortable( $name ): bool {
		return true;
	}
}
