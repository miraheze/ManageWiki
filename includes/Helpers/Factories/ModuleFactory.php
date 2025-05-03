<?php

namespace Miraheze\ManageWiki\Helpers\Factories;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\MainConfigNames;
use Miraheze\CreateWiki\Helpers\RemoteWiki;
use Miraheze\ManageWiki\ConfigNames;
use Miraheze\ManageWiki\Helpers\ExtensionsModule;
use Miraheze\ManageWiki\Helpers\NamespacesModule;
use Miraheze\ManageWiki\Helpers\PermissionsModule;
use Miraheze\ManageWiki\Helpers\SettingsModule;

class ModuleFactory {

	private const DEFAULT_DATABASE = 'default';

	public const CONSTRUCTOR_OPTIONS = [
		ConfigNames::ModulesEnabled,
		MainConfigNames::DBname,
	];

	private array $coreInstances = [];

	public function __construct(
		private readonly CoreFactory $core,
		private readonly ExtensionsFactory $extensions,
		private readonly NamespacesFactory $namespaces,
		private readonly PermissionsFactory $permissions,
		private readonly SettingsFactory $settings,
		private readonly ServiceOptions $options
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
	}

	public function isEnabled( string $module ): bool {
		$modulesEnabled = $this->options->get( ConfigNames::ModulesEnabled );
		return $modulesEnabled[$module] ?? false;
	}

	public function core( string $dbname ): RemoteWiki {
		$this->coreInstances[$dbname] ??=
			$this->core->newInstance( $dbname );
		return $this->coreInstances[$dbname];
	}

	public function extensions( string $dbname ): ExtensionsModule {
		return $this->extensions->newInstance( $dbname );
	}

	public function namespaces( string $dbname ): NamespacesModule {
		return $this->namespaces->newInstance( $dbname );
	}

	public function permissions( string $dbname ): PermissionsModule {
		return $this->permissions->newInstance( $dbname );
	}

	public function settings( string $dbname ): SettingsModule {
		return $this->settings->newInstance( $dbname );
	}

	public function namespacesDefault(): NamespacesModule {
		return $this->namespaces( self::DEFAULT_DATABASE );
	}

	public function permissionsDefault(): PermissionsModule {
		return $this->permissions( self::DEFAULT_DATABASE );
	}

	public function coreLocal(): RemoteWiki {
		return $this->core( $this->options->get( MainConfigNames::DBname ) );
	}

	public function extensionsLocal(): ExtensionsModule {
		return $this->extensions( $this->options->get( MainConfigNames::DBname ) );
	}

	public function namespacesLocal(): NamespacesModule {
		return $this->namespaces( $this->options->get( MainConfigNames::DBname ) );
	}

	public function permissionsLocal(): PermissionsModule {
		return $this->permissions( $this->options->get( MainConfigNames::DBname ) );
	}

	public function settingsLocal(): SettingsModule {
		return $this->settings( $this->options->get( MainConfigNames::DBname ) );
	}
}
