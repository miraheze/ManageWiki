<?php

namespace Miraheze\ManageWiki\Helpers;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Context\RequestContext;
use MediaWiki\MainConfigNames;
use MediaWiki\SiteStats\SiteStats;
use Miraheze\ManageWiki\ConfigNames;
use Miraheze\ManageWiki\Helpers\Factories\PermissionsFactory;
use Miraheze\ManageWiki\Helpers\Factories\SettingsFactory;

class Requirements {

	public const CONSTRUCTOR_OPTIONS = [
		ConfigNames::PermissionsDefaultPrivateGroup,
		MainConfigNames::DBname,
	];

	public function __construct(
		private readonly PermissionsFactory $permissionsFactory,
		private readonly SettingsFactory $settingsFactory,
		private readonly ServiceOptions $options
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
	}

	public function check( array $actions, array $extList ): bool {
		$stepResponse = [];
		foreach ( $actions as $action => $data ) {
			switch ( $action ) {
				case 'permissions':
					$stepResponse['permissions'] = $this->permissions( $data );
					break;
				case 'extensions':
					$stepResponse['extensions'] = $this->extensions( $data, $extList );
					break;
				case 'activeusers':
					$stepResponse['activeusers'] = $this->activeUsers( $data );
					break;
				case 'articles':
					$stepResponse['articles'] = $this->articles( $data );
					break;
				case 'pages':
					$stepResponse['pages'] = $this->pages( $data );
					break;
				case 'images':
					$stepResponse['images'] = $this->images( $data );
					break;
				case 'settings':
					$stepResponse['settings'] = $this->settings( $data );
					break;
				case 'visibility':
					$stepResponse['visibility'] = $this->visibility( $data );
					break;
				default:
					return false;
			}
		}

		return !in_array( false, $stepResponse, true );
	}

	private function permissions( array $data ): bool {
		// We don't check permissions if we are in CLI mode, so that we can
		// toggle restricted extensions in CLI.
		if ( MW_ENTRY_POINT === 'cli' ) {
			return true;
		}

		$authority = RequestContext::getMain()->getAuthority();
		return $authority->isAllowedAll( ...$data );
	}

	private function extensions( array $data, array $extList ): bool {
		foreach ( $data as $extension ) {
			if ( is_array( $extension ) ) {
				$count = 0;
				foreach ( $extension as $or ) {
					if ( in_array( $or, $extList, true ) ) {
						$count++;
					}
				}

				if ( !$count ) {
					return false;
				}
			} elseif ( !in_array( $extension, $extList, true ) ) {
				return false;
			}
		}

		return true;
	}

	private function activeUsers( int $limit ): bool {
		return SiteStats::activeUsers() <= $limit;
	}

	private function articles( int $limit ): bool {
		return SiteStats::articles() <= $limit;
	}

	private function pages( int $limit ): bool {
		return SiteStats::pages() <= $limit;
	}

	private function images( int $limit ): bool {
		return SiteStats::images() <= $limit;
	}

	private function settings( array $data ): bool {
		$dbname = $data['dbname'] ?? $this->options->get( MainConfigNames::DBname );
		$setting = $data['setting'];
		$value = $data['value'];

		$mwSettings = $this->settingsFactory->newInstance( $dbname );
		$wikiValue = $mwSettings->list( $setting );

		if ( $wikiValue !== null ) {
			// We need to cast $wikiValue to an array
			// to convert any values (boolean) to an array.
			// Otherwise TypeError is thrown.
			if ( $wikiValue === $value || in_array( $value, (array)$wikiValue, true ) ) {
				return true;
			}
		}

		return false;
	}

	private function visibility( array $data ): bool {
		$defaultPrivateGroup = $this->options->get( ConfigNames:::PermissionsDefaultPrivateGroup );
		$mwPermissions = $this->permissionsFactory->newInstance(
			$this->options->get( MainConfigNames::DBname )
		);

		$isPrivate = in_array( $defaultPrivateGroup, $mwPermissions->getGroupsWithPermission( 'read' ), true );

		$ret = [];
		foreach ( $data as $key => $val ) {
			if ( $key === 'state' ) {
				$ret['state'] = (
					( $val === 'private' && $isPrivate ) ||
					( $val === 'public' && !$isPrivate )
				);
				continue;
			}

			if ( $key === 'permissions' ) {
				$ret['permissions'] = $this->permissions( $val );
				continue;
			}
		}

		return !in_array( false, $ret, true );
	}
}
