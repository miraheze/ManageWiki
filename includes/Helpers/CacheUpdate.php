<?php

namespace Miraheze\ManageWiki\Helpers;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\JobQueue\JobQueueGroupFactory;
use MediaWiki\JobQueue\JobSpecification;
use Miraheze\ManageWiki\ConfigNames;
use Miraheze\ManageWiki\Jobs\CacheUpdateJob;

class CacheUpdate {

	public const array CONSTRUCTOR_OPTIONS = [
		ConfigNames::Servers,
	];

	public function __construct(
		private readonly JobQueueGroupFactory $jobQueueGroupFactory,
		private readonly ServiceOptions $options,
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
	}

	public function queueJob( string $dbname ): void {
		if ( $this->options->get( ConfigNames::Servers ) === [] ) {
			// No servers configured.
			return;
		}

		$this->jobQueueGroupFactory->makeJobQueueGroup( $dbname )->push(
			new JobSpecification( CacheUpdateJob::JOB_NAME, [ 'dbname' => $dbname ] )
		);
	}
}
