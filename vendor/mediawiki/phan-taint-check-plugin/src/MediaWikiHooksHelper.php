<?php

namespace SecurityCheckPlugin;

use Phan\Config;
use Phan\Language\FQSEN\FullyQualifiedFunctionLikeName;
use Phan\Language\FQSEN\FullyQualifiedFunctionName;
use Phan\Language\FQSEN\FullyQualifiedMethodName;

/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */

class MediaWikiHooksHelper {
	/**
	 * @var bool Whether extension.json/skin.json was already loaded
	 */
	private $extensionJsonLoaded = false;

	/**
	 * @var FullyQualifiedFunctionLikeName[][] A mapping from hook names to FQSEN that implement it
	 * @phan-var array<string,FullyQualifiedFunctionLikeName[]>
	 */
	private $hookSubscribers = [];

	/** @var self|null */
	private static $instance;

	/**
	 * @return self
	 */
	public static function getInstance() : self {
		if ( !self::$instance ) {
			self::$instance = new self;
		}
		return self::$instance;
	}

	/**
	 * Clear the extension.json cache, for testing purpose
	 *
	 * @suppress PhanUnreferencedPublicMethod
	 */
	public function clearCache() : void {
		$this->extensionJsonLoaded = false;
	}

	/**
	 * Add a hook implementation to our list.
	 *
	 * This also handles parser hooks which aren't normal hooks.
	 * Non-normal hooks start their name with a "!"
	 *
	 * @param string $hookName Name of hook
	 * @param FullyQualifiedFunctionLikeName $fqsen The implementing method
	 * @return bool true if already registered, false otherwise
	 */
	public function registerHook( string $hookName, FullyQualifiedFunctionLikeName $fqsen ) : bool {
		if ( !isset( $this->hookSubscribers[$hookName] ) ) {
			$this->hookSubscribers[$hookName] = [];
		}
		foreach ( $this->hookSubscribers[$hookName] as $subscribe ) {
			if ( (string)$subscribe === (string)$fqsen ) {
				// dupe
				return true;
			}
		}
		$this->hookSubscribers[$hookName][] = $fqsen;
		return false;
	}

	/**
	 * Register hooks from extension.json/skin.json
	 *
	 * Assumes extension.json/skin.json is in project root directory
	 * unless SECURITY_CHECK_EXT_PATH is set
	 */
	protected function loadExtensionJson() : void {
		if ( $this->extensionJsonLoaded ) {
			return;
		}
		$this->extensionJsonLoaded = true;
		foreach ( [ 'extension.json', 'skin.json' ] as $filename ) {
			$envPath = getenv( 'SECURITY_CHECK_EXT_PATH' );
			if ( $envPath ) {
				$jsonPath = $envPath . '/' . $filename;
			} else {
				$jsonPath = Config::projectPath( $filename );
			}
			if ( file_exists( $jsonPath ) ) {
				$json = json_decode( file_get_contents( $jsonPath ), true );
				if ( !is_array( $json ) ) {
					continue;
				}
				if ( isset( $json['Hooks'] ) && is_array( $json['Hooks'] ) ) {
					foreach ( $json['Hooks'] as $hookName => $cbList ) {
						foreach ( (array)$cbList as $cb ) {
							// All callbacks here are simple
							// "someFunction" or "Class::SomeMethod"
							if ( strpos( $cb, '::' ) === false ) {
								$callback = FullyQualifiedFunctionName::fromFullyQualifiedString(
									$cb
								);
							} else {
								$callback = FullyQualifiedMethodName::fromFullyQualifiedString(
									$cb
								);
							}
							$this->registerHook( $hookName, $callback );
						}
					}
				}
			}
		}
	}

	/**
	 * Get a list of subscribers for hook
	 *
	 * @param string $hookName Hook in question. Hooks starting with ! are special.
	 * @return FullyQualifiedFunctionLikeName[]
	 */
	public function getHookSubscribers( string $hookName ) : array {
		$this->loadExtensionJson();
		return $this->hookSubscribers[$hookName] ?? [];
	}

	/**
	 * Is a particular function implementing a special hook.
	 *
	 * @note This assumes that any given func will only implement
	 *   one hook
	 * @param FullyQualifiedFunctionLikeName $fqsen The function to check
	 * @return string|null The hook it is implementing or null if no hook
	 */
	public function isSpecialHookSubscriber( FullyQualifiedFunctionLikeName $fqsen ) : ?string {
		$this->loadExtensionJson();
		$specialHooks = [
			'!ParserFunctionHook',
			'!ParserHook'
		];

		// @todo This is probably not the most efficient thing.
		foreach ( $specialHooks as $hook ) {
			if ( !isset( $this->hookSubscribers[$hook] ) ) {
				continue;
			}
			foreach ( $this->hookSubscribers[$hook] as $implFQSEN ) {
				if ( (string)$implFQSEN === (string)$fqsen ) {
					return $hook;
				}
			}
		}
		return null;
	}
}
