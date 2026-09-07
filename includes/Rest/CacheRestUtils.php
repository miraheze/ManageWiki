<?php

namespace Miraheze\ManageWiki\Rest;

use MediaWiki\Config\ServiceOptions;
use Miraheze\ManageWiki\ConfigNames;
use Wikimedia\ObjectCache\BagOStuff;
use function hash_equals;
use function preg_match;

class CacheRestUtils {

	public const array CONSTRUCTOR_OPTIONS = [
		ConfigNames::CacheUpdateKey,
		ConfigNames::CacheUpdateRestEnabled,
	];

	private const string VALID_DBNAME = '/^[a-z][a-z0-9_]{0,63}$/';

	private const int MAX_ATTEMPTS = 10;
	private const int THROTTLE_SECONDS = 60;

	public function __construct(
		private readonly BagOStuff $cache,
		private readonly ServiceOptions $options,
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
	}

	public function isRestEnabled(): bool {
		return (bool)$this->options->get( ConfigNames::CacheUpdateRestEnabled );
	}

	public function isValidKey( string $providedKey ): bool {
		$configuredKey = (string)$this->options->get( ConfigNames::CacheUpdateKey );
		return $configuredKey !== '' && hash_equals( $configuredKey, $providedKey );
	}

	public function isValidDbname( string $dbname ): bool {
		return (bool)preg_match( self::VALID_DBNAME, $dbname );
	}

	public function isThrottled( string $purpose, string $clientIp ): bool {
		return (int)$this->cache->get( $this->throttleKey( $purpose, $clientIp ) ) >= self::MAX_ATTEMPTS;
	}

	public function recordFailure( string $purpose, string $clientIp ): void {
		$key = $this->throttleKey( $purpose, $clientIp );
		$attempts = $this->cache->incrWithInit( $key, self::THROTTLE_SECONDS, 1, 1 );
		if ( $attempts === false ) {
			$this->cache->set( $key, 1, self::THROTTLE_SECONDS );
		}
	}

	private function throttleKey( string $purpose, string $clientIp ): string {
		return $this->cache->makeGlobalKey( 'ManageWiki', "cache-$purpose-attempts", $clientIp );
	}
}
