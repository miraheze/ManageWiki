<?php

namespace Miraheze\ManageWiki\Helpers\Factories;

use MediaWiki\JobQueue\JobQueueGroupFactory;
use Miraheze\ManageWiki\Helpers\Installer;
use Psr\Log\LoggerInterface;
use Wikimedia\Rdbms\ILBFactory;

class InstallerFactory {

	public function __construct(
		private readonly ILBFactory $dbLoadBalancerFactory,
		private readonly JobQueueGroupFactory $jobQueueGroupFactory,
		private readonly LoggerInterface $logger,
		private readonly callable $moduleFactoryClosure
	) {
	}

	public function newInstance( string $dbname ): Installer {
		return new Installer(
			$this->dbLoadBalancerFactory,
			$this->jobQueueGroupFactory,
			$this->loggerInterface,
			( $this->moduleFactoryClosure )(),
			$dbname
		);
	}
}
