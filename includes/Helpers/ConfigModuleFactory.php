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

	public function core( string $dbname ): RemoteWikiFactory {
		return $this->core->newInstance( $dbname );
	}

	public function extensions( string $dbname ): ManageWikiExtensions {
		return $this->extensions->newInstance( $dbname );
	}

	public function namespaces( string $dbname ): ManageWikiNamespaces {
		return $this->namespaces->newInstance( $dbname );
	}

	public function permissions( string $dbname ): ManageWikiPermissions {
		return $this->permissions->newInstance( $dbname );
	}

	public function settings( string $dbname ): ManageWikiSettings {
		return $this->settings->newInstance( $dbname );
	}

	public function namespacesDefault(): ManageWikiNamespaces {
		return $this->namespaces( self::DEFAULT_DATABASE );
	}

	public function permissionsDefault(): ManageWikiPermissions {
		return $this->permissions( self::DEFAULT_DATABASE );
	}

	public function coreLocal(): RemoteWikiFactory {
		return $this->core( $this->options->get( MainConfigNames::DBname ) );
	}

	public function extensionsLocal(): ManageWikiExtensions {
	    return $this->extensions( $this->options->get( MainConfigNames::DBname ) );
	}

	public function namespacesLocal(): ManageWikiNamespaces {
		return $this->namespaces( $this->options->get( MainConfigNames::DBname ) );
	}

	public function permissionsLocal(): ManageWikiPermissions {
		return $this->permissions( $this->options->get( MainConfigNames::DBname ) );
	}

	public function settingsLocal(): ManageWikiSettings {
		return $this->settings( $this->options->get( MainConfigNames::DBname ) );
	}
}
