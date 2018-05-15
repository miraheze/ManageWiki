<?php

require_once '/srv/mediawiki/w/maintenance/commandLine.inc';

$dbw = wfGetDB( DB_MASTER );

$settingsource = file( "" );

foreach ( $settingsource as $input ) {
	$wikiDB = explode( '|' $line, 2 );
	list( $DBname, $settingvalue ) = array_pad( $wikiDB, 2, '' );

	$remoteWiki = RemoteWiki::newFromName

	$settingsarray = 

	$settingsarray[] = str_replace( "\n", '', $settingvalue );

	$settings = json_encode( $settingsarray );

	$dbw->update( 'cw_wikis',
		array(
			'wiki_settings' => $settings
		),
		array(
			'wiki_dbname' => $DBname
		),
		__METHOD__
	);
}
