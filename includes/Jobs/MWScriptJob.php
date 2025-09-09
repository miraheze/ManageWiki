<?php

namespace Miraheze\ManageWiki\Jobs;

use MediaWiki\JobQueue\Job;
use MediaWiki\JobQueue\JobQueueGroupFactory;
use MediaWiki\JobQueue\JobSpecification;
use MediaWiki\Shell\Shell;
use Psr\Log\LoggerInterface;
use function is_bool;
use function json_encode;

class MWScriptJob extends Job {

	public const JOB_NAME = 'MWScriptJob';

	private readonly array $data;
	private readonly string $dbname;

	public function __construct(
		array $params,
		private readonly JobQueueGroupFactory $jobQueueGroupFactory,
		private readonly LoggerInterface $logger
	) {
		parent::__construct( self::JOB_NAME, $params );

		$this->data = $params['data'];
		$this->dbname = $params['dbname'];
	}

	/** @inheritDoc */
	public function run(): true {
		$limits = [ 'memory' => 0, 'filesize' => 0 ];
		foreach ( $this->data as $script => $options ) {
			$arguments = [ '--wiki', $this->dbname ];
			$repeatWith = [];
			if ( isset( $options['repeat-with'] ) ) {
				$repeatWith = $options['repeat-with'];
				unset( $options['repeat-with'] );
			}

			foreach ( $options as $name => $val ) {
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
			if ( $result !== 0 ) {
				$this->logger->error( 'MWScriptJob failure. Status {result} running {script}', [
					'arguments' => json_encode( $arguments ),
					'result' => $result,
					'script' => $script,
				] );
			}

			if ( $repeatWith ) {
				$jobQueueGroup = $this->jobQueueGroupFactory->makeJobQueueGroup();
				$jobQueueGroup->push(
					new JobSpecification(
						self::JOB_NAME,
						[
							'data' => [ $script => $repeatWith ],
							'dbname' => $this->dbname,
						]
					)
				);
			}
		}

		return true;
	}
}
