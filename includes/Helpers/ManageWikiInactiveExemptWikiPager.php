<?php

namespace Miraheze\ManageWiki\Helpers;

use Linker;
use MediaWiki\MediaWikiServices;
use SpecialPage;
use TablePager;
use User;

class ManageWikiInactiveExemptWikiPager extends TablePager {
        public function __construct( $page ) {
                $config = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'managewiki' );
                $this->mDb = MediaWikiServices::getInstance()->getDBLoadBalancerFactory()
                        ->getMainLB( $config->get( 'CreateWikiDatabase' ) )
                        ->getMaintenanceConnectionRef( DB_REPLICA, [], $config->get( 'CreateWikiDatabase' ) );

                parent::__construct( $page->getContext(), $page->getLinkRenderer() );
        }

        public function getFieldNames() {
                static $headers = null;

                $headers = [
                        'wiki_dbname' => 'managewiki-label-dbname',
                        'wiki_inactive_exempt_timestamp' => 'managewiki-label-inactiveexemptdate',
                        'wiki_inactive_exempt_granter' => 'managewiki-label-inactiveexemptgranter',
                        'wiki_inactive_exempt_reason' => 'managewiki-label-inactiveexemptreason',
                        'wiki_inactive_exempt' => 'managewiki-label-changesettings'
                ];

                foreach ( $headers as &$msg ) {
                        $msg = $this->msg( $msg )->text();
                }

                return $headers;
        }

        public function formatValue( $name, $value ) {
                $row = $this->mCurrentRow;
                //$user = new User::UserFactory;

                switch ( $name ) {
                        case 'wiki_dbname':
                                $formatted = $row->wiki_dbname;
                                break;
                        case 'wiki_inactive_exempt_timestamp':
                                $formatted = wfTimestamp( TS_RFC2822, (int)$row->wiki_inactive_exempt_timestamp );
                                break;
                        case 'wiki_inactive_exempt_granter':
                                $formatted = User::newFromId( $row->wiki_inactive_exempt_granter )->getName();
                                break;
                      case 'wiki_inactive_exempt_reason':
                                $formatted = $row->wiki_inactive_exempt_reason;
                                break;
                        case 'wiki_inactive_exempt':
                                $formatted = Linker::makeExternalLink( SpecialPage::getTitleFor( 'ManageWiki' )->getFullURL() . '/core/' . $row->wiki_dbname, $this->msg( 'managewiki-label-goto' )->text() );
                                break;
                        default:
                                $formatted = "Unable to format $name";
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
                                'wiki_inactive_exempt_timestamp',
                                'wiki_inactive_exempt_granter',
                                'wiki_inactive_exempt_reason',
                                'wiki_inactive_exempt',
                        ],
                        'conds' => [
                                'wiki_inactive_exempt' => 1
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
