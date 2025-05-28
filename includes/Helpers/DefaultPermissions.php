<?php

namespace Miraheze\ManageWiki\Helpers;

use MediaWiki\Config\ServiceOptions;
use Miraheze\ManageWiki\ConfigNames;
use Miraheze\ManageWiki\Helpers\Factories\ModuleFactory;

class DefaultPermissions {

	public const CONSTRUCTOR_OPTIONS = [
		ConfigNames::PermissionsDefaultPrivateGroup,
	];

	public function __construct(
		private readonly ModuleFactory $moduleFactory,
		private readonly ServiceOptions $options
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
	}

	public function populatePermissions( string $dbname, bool $private ): void {
		$mwPermissionsDefault = $this->moduleFactory->permissionsDefault();
		$mwPermissions = $this->moduleFactory->permissions( $dbname );
		$defaultGroups = array_diff(
			$mwPermissionsDefault->listGroups(),
			[ $this->options->get( ConfigNames::PermissionsDefaultPrivateGroup ) ]
		);

		foreach ( $defaultGroups as $newGroup ) {
			$groupData = $mwPermissionsDefault->list( $newGroup );
			$groupArray = [];

			foreach ( $groupData as $name => $value ) {
				if ( $name === 'autopromote' ) {
					$groupArray[$name] = $value;
					continue;
				}

				$groupArray[$name]['add'] = $value;
			}

			$mwPermissions->modify( $newGroup, $groupArray );
		}

		$mwPermissions->commit();

		if ( $private ) {
			$this->populatePrivatePermissons( $dbname );
		}
	}

	public function populatePrivatePermissons( string $dbname ): void {
		$defaultPrivateGroup = $this->options->get( ConfigNames::PermissionsDefaultPrivateGroup );
		if ( !$defaultPrivateGroup ) {
			return;
		}

		$mwPermissionsDefault = $this->moduleFactory->permissionsDefault();
		$mwPermissions = $this->moduleFactory->permissions( $dbname );

		$defaultPrivate = $mwPermissionsDefault->list( $defaultPrivateGroup );

		$privateArray = [];
		foreach ( $defaultPrivate as $name => $value ) {
			if ( $name === 'autopromote' ) {
				$privateArray[$name] = $value;
				continue;
			}

			$privateArray[$name]['add'] = $value;
		}

		$mwPermissions->modify( $defaultPrivateGroup, $privateArray );

		$mwPermissions->modify( 'sysop', [
			'addgroups' => [
				'add' => [ $defaultPrivateGroup ],
			],
			'removegroups' => [
				'add' => [ $defaultPrivateGroup ],
			],
		] );

		$mwPermissions->commit();
	}
}
