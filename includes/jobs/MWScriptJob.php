<?php

use MediaWiki\Shell\Shell;

class MWScriptJob extends Job {
	private $dbname;

	public function __construct( $dbname, $params ) {
		parent::__construct( 'mwScript', $params );
		self::$dbname = $dbname;
	}

	public function run() {
		$scriptParams = [
			'--wiki',
			self::$dbname
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

		if ( !$result ) {
			wfDebugLog( 'ManageWiki', "MWScriptJob failure. Status {$result} running {$this->params['script']}" );
		}
	}
}
