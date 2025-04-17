<?php

namespace Miraheze\ManageWiki\Jobs;

use Job;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Shell\Shell;
use MediaWiki\Title\Title;

class MWScriptJob extends Job {

	public function __construct( Title $title, array $params ) {
		parent::__construct( 'MWScriptJob', $params );
	}

	/**
	 * @return bool
	 */
	public function run(): bool {
		$limits = [ 'memory' => 0, 'filesize' => 0 ];
		$script = $this->params['script'];
		$arguments = [
			'--wiki', $this->params['dbname'],
		];

		foreach ( (array)$this->params['options'] as $name => $val ) {
			$arguments[] = "--$name";

			if ( !is_bool( $val ) ) {
				$arguments[] = $val;
			}
		}

		$result = Shell::makeScriptCommand( $script, $arguments )
			->limits( $limits )
			->execute()
			->getExitCode();

		// An execute code higher then 0 indicates failure.
		if ( $result ) {
			$logger = LoggerFactory::getInstance( 'ManageWiki' );
			$logger->error( 'MWScriptJob failure. Status {result} running {script}', [
				'result' => $result,
				'script' => $script,
			] );
		}

		return true;
	}
}
