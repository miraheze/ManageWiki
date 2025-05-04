<?php

namespace Miraheze\ManageWiki\Jobs;

use Job;
use JobSpecification;
use MediaWiki\JobQueue\JobQueueGroupFactory;
use MediaWiki\Shell\Shell;
use Psr\Log\LoggerInterface;

class MWScriptJob extends Job {

	public const JOB_NAME = 'MWScriptJob';

	private readonly string $dbname;
	private readonly string $script;

	private readonly array $options;

	public function __construct(
		array $params,
		private readonly JobQueueGroupFactory $jobQueueGroupFactory,
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
		$repeatWith = [];
		$options = $this->options;
		if ( isset( $options['repeat-with'] ) ) {
			$repeatWith = $options['repeat-with'];
			unset( $options['repeat-with'] );
		}

		$limits = [ 'memory' => 0, 'filesize' => 0 ];
		$arguments = [ '--wiki', $this->dbname ];

		foreach ( $options as $name => $val ) {
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

		if ( $repeatWith ) {
			$jobQueueGroup = $this->jobQueueGroupFactory->makeJobQueueGroup();
			$jobQueueGroup->push(
				new JobSpecification(
					self::JOB_NAME,
					[
						'dbname' => $this->dbname,
						'script' => $this->script,
						'options' => $repeatWith,
					]
				)
			);
		}

		return true;
	}
}
