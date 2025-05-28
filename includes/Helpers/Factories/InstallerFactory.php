<?php

namespace Miraheze\ManageWiki\Helpers\Factories;

use Closure;
use MediaWiki\JobQueue\JobQueueGroupFactory;
use Miraheze\ManageWiki\Helpers\Installer;
use Psr\Log\LoggerInterface;
use Wikimedia\Rdbms\ILBFactory;

class InstallerFactory {

	public function __construct(
		private readonly ILBFactory $dbLoadBalancerFactory,
		private readonly JobQueueGroupFactory $jobQueueGroupFactory,
		private readonly LoggerInterface $logger,
		private readonly Closure $moduleFactoryClosure
	) {
	}

	public function getInstaller( string $dbname ): Installer {
		return new Installer(
			$this->dbLoadBalancerFactory,
			$this->jobQueueGroupFactory,
			$this->logger,
			( $this->moduleFactoryClosure )(),
			$dbname
		);
	}
}
