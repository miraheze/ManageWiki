<?php

use MediaWiki\Shell\Shell;

class MWScriptJob extends Job {
	private $dbname;

	public function __construct( $dbname, $params ) {
		parent::__construct( 'mwScript', $params );
		$this->$dbname = $dbname;
	}

	public function run() {
		$scriptParams = [
			'--wiki',
			$this->$dbname
		];

		foreach ( (array)$this->params['options'] as $name => $val ) {
			$scriptParams[] = "--{$name}";

			if ( !is_bool( $val ) ) {
				$scriptParams[] = $val;
			}
		}

		$result = Shell::makeScriptCommand(
			$this->params['script'],
			$scriptParams
		)->limits( [ 'memory' => 0, 'filesize' => 0 ] )->execute()->getExitCode();

		// An execute code higher then 0 indicates failure.
		if ( $result ) {
			wfDebugLog( 'ManageWiki', "MWScriptJob failure. Status {$result} running {$this->params['script']}" );
		}
	}
}
