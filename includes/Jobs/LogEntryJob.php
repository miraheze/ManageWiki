<?php

namespace Miraheze\ManageWiki\Jobs;

use Job;
use ManualLogEntry;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\User;

class LogEntryJob extends Job {

	public const JOB_NAME = 'LogEntryJob';

	private readonly string $logType;
	private readonly string $action;
	private readonly string $comment;
	private readonly string $target;
	private readonly array $logParams;

	public function __construct(
		array $params,
		private readonly TitleFactory $titleFactory
	) {
		parent::__construct( self::JOB_NAME, $params );

		$this->logType = $params['logType'];
		$this->action = $params['action'];
		$this->comment = $params['comment'];
		$this->target = $params['target'];
		$this->logParams = $params['logParams'];
	}

	public function run(): bool {
		$title = $this->titleFactory->newFromText( $this->target );
		if ( !$title ) {
			return false;
		}

		$user = User::newSystemUser( 'ManageWiki', [ 'steal' => true ] );
		if ( !$user ) {
			return false;
		}

		$entry = new ManualLogEntry( $this->logType, $this->action );
		$entry->setTarget( $title );
		$entry->setComment( $this->comment );
		$entry->setPerformer( $user );
		$entry->setParameters( $this->logParams );

		$logId = $entry->insert();
		$entry->publish( $logId );

		return true;
	}
}
