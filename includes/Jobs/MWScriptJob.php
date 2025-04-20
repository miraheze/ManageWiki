<?php

namespace Miraheze\ManageWiki\Jobs;

use Job;
use MediaWiki\Shell\Shell;
use Psr\Log\LoggerInterface;

class MWScriptJob extends Job {

	public const JOB_NAME = 'MWScriptJob';

	private readonly string $dbname;
	private readonly string $script;

	private readonly array $options;

	public function __construct(
		array $params,
		private readonly LoggerInterface $logger
	) {
		parent::__construct( self::JOB_NAME, $params );

		$this->dbname = $params['dbname'];
		$this->options = $params['options'];
		$this->script = $params['script'];
	}

	/**
	 * @return bool
	 */
	public function run(): bool {
		$limits = [ 'memory' => 0, 'filesize' => 0 ];
		$arguments = [ '--wiki', $this->dbname ];

		foreach ( $this->options as $name => $val ) {
			$arguments[] = "--$name";

			if ( !is_bool( $val ) ) {
				$arguments[] = $val;
			}
		}

		$result = Shell::makeScriptCommand( $this->script, $arguments )
			->limits( $limits )
			->execute()
			->getExitCode();

		// An execute code higher then 0 indicates failure.
		if ( $result ) {
			$this->logger->error( 'MWScriptJob failure. Status {result} running {script}', [
				'result' => $result,
				'script' => $this->script,
			] );
		}

		return true;
	}
}
