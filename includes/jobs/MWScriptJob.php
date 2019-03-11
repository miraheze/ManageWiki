<?php

use MediaWiki\Shell\Shell;

class MWScriptJob extends Job {
	private $dbname;

	public function __construct( $dbname, $params ) {
		parent::construct( 'mwScript', SpecialPage::getTitleFor( 'ManageWiki' ), $params );
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
		)->execute()->getExitCode();

		if ( !$result ) {
			wfDebugLog( 'ManageWiki', "MWScriptJob failure. Status {$result} running {$this->params['script']}" );
		}
	}
}
