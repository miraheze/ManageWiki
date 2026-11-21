<?php

namespace Miraheze\ManageWiki\Helpers\Factories;

use LogicException;
use MediaWiki\Config\ServiceOptions;
use Miraheze\ManageWiki\Helpers\CoreModule;
use Miraheze\ManageWiki\ICoreModule;
use Wikimedia\ObjectFactory\ObjectFactory;
use function array_keys;
use function array_values;
use function count;
use function implode;

class CoreFactory {

	public function __construct(
		private readonly ObjectFactory $objectFactory,
		private readonly SettingsFactory $settingsFactory,
		private readonly ServiceOptions $options,
		/** @var array<string, array> $providerSpecs */
		private readonly array $providerSpecs,
	) {
	}

	public function newInstance( string $dbname ): ICoreModule {
		if ( $dbname === ModuleFactory::DEFAULT_DBNAME || $this->providerSpecs === [] ) {
			return new CoreModule(
				$this->settingsFactory,
				$this->options,
				$dbname
			);
		}

		if ( count( $this->providerSpecs ) > 1 ) {
			throw new LogicException(
				'Only one ManageWikiCoreProviders attribute may be registered, found: ' .
				implode( ', ', array_keys( $this->providerSpecs ) )
			);
		}

		return $this->objectFactory->createObject(
			array_values( $this->providerSpecs )[0],
			[
				'assertClass' => ICoreModule::class,
				'extraArgs' => [ $dbname ],
			]
		);
	}
}
