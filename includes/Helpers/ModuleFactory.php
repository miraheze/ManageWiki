<?php

namespace Miraheze\ManageWiki\Helpers;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\MainConfigNames;
use Miraheze\CreateWiki\Services\RemoteWikiFactory;
use Miraheze\ManageWiki\Helpers\Factories\CoreFactory;
use Miraheze\ManageWiki\Helpers\Factories\ExtensionsFactory;
use Miraheze\ManageWiki\Helpers\Factories\NamespacesFactory;
use Miraheze\ManageWiki\Helpers\Factories\PermissionsFactory;
use Miraheze\ManageWiki\Helpers\Factories\SettingsFactory;

class ModuleFactory {

	private const DEFAULT_DATABASE = 'default';

	public const CONSTRUCTOR_OPTIONS = [
		MainConfigNames::DBname,
	];

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
