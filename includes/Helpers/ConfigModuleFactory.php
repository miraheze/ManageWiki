<?php

namespace Miraheze\ManageWiki\Helpers;

use InvalidArgumentException;
use Miraheze\CreateWiki\IConfigModule;
use Miraheze\CreateWiki\Services\RemoteWikiFactory;

class ConfigModuleFactory {

	private const DEFAULT_DATABASE = 'default';

	public function __construct(
		private readonly ManageWikiExtensions $extensions,
		private readonly ManageWikiNamespaces $namespaces,
		private readonly ManageWikiPermissions $permissions,
		private readonly ManageWikiSettings $settings,
		private readonly RemoteWikiFactory $core
	) {
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

	public function newDefault( string $module ): IConfigModule {
		return match ( $module ) {
			'namespaces' => $this->namespaces->newInstance( self::DEFAULT_DATABASE ),
			'permissions' => $this->permissions->newInstance( self::DEFAULT_DATABASE ),
			default => throw new InvalidArgumentException( "$module does not support default" ),
		}
	}
}
	
