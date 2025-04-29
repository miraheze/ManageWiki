<?php

namespace Miraheze\ManageWiki\Helpers;

use InvalidArgumentException;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\MainConfigNames;
use Miraheze\CreateWiki\IConfigModule;
use Miraheze\CreateWiki\Services\RemoteWikiFactory;

class ConfigModuleFactory {

	private const DEFAULT_DATABASE = 'default';

	public const CONSTRUCTOR_OPTIONS = [
		MainConfigNames::DBname,
	];

	public function __construct(
		private readonly ManageWikiExtensions $extensions,
		private readonly ManageWikiNamespaces $namespaces,
		private readonly ManageWikiPermissions $permissions,
		private readonly ManageWikiSettings $settings,
		private readonly RemoteWikiFactory $core,
		private readonly ServiceOptions $options
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
	}

	public function newFromDB( string $module, string $dbname ): IConfigModule {
		return match ( $module ) {
			'core' => $this->core->newInstance( $dbname ),
			'extensions' => $this->extensions->newInstance( $dbname ),
			'namespaces' => $this->namespaces->newInstance( $dbname ),
			'permissions' => $this->permissions->newInstance( $dbname ),
			'settings' => $this->settings->newInstance( $dbname ),
			default => throw new InvalidArgumentException( "$module not recognized" ),
		}
	}

	public function newFromLocal( string $module ): IConfigModule {
		$dbname = $this->options->get( MainConfigNames::DBname );
		return match ( $module ) {
			'core' => $this->core->newInstance( $dbname ),
			'extensions' => $this->extensions->newInstance( $dbname ),
			'namespaces' => $this->namespaces->newInstance( $dbname ),
			'permissions' => $this->permissions->newInstance( $dbname ),
			'settings' => $this->settings->newInstance( $dbname ),
			default => throw new InvalidArgumentException( "$module not recognized" ),
		}
	}

	public function newDefault( string $module ): IConfigModule {
		return match ( $module ) {
			'namespaces' => $this->namespaces->newInstance( self::DEFAULT_DATABASE ),
			'permissions' => $this->permissions->newInstance( self::DEFAULT_DATABASE ),
			default => throw new InvalidArgumentException( "$module does not support default" ),
		}
	}

	public function remoteWiki( string $dbname ): RemoteWikiFactory {
		return $this->newFromDB( 'core', $dbname );
	}
}
