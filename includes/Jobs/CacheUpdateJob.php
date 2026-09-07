<?php

namespace Miraheze\ManageWiki\Jobs;

use MediaWiki\JobQueue\Job;
use Miraheze\ManageWiki\Helpers\CacheUpdate;

class CacheUpdateJob extends Job {

	public const string JOB_NAME = 'CacheUpdateJob';

	private readonly string $action;
	private readonly string $dbname;

	public function __construct(
		array $params,
		private readonly CacheUpdate $cacheUpdate,
	) {
		parent::__construct( self::JOB_NAME, $params );
		$this->action = $params['action'];
		$this->dbname = $params['dbname'];
	}

	public function run(): bool {
		return $this->cacheUpdate->executeNow( $this->action, $this->dbname );
	}
}
