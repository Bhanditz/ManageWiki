<?php

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

class ManageWikiToggleExtension extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->addArg( 'ext', 'The ManageWiki name of the extension.', true );
		$this->addOption( 'disable', 'Disable the extension. If not given, enabling is assumed.' );
	}

	function execute() {
		global $wgDBname, $wgCreateWikiDatabase;

		$dbw = wfGetDB( DB_MASTER, [], $wgCreateWikiDatabase );

		$ext = $this->getArg( 0 );

		$enable = (bool)( $this->getOption( 'disable' ) ) ? false : true;

		$exts = (string)$dbw->selectRow(
			'cw_wikis',
			[ 'wiki_extensions' ],
			[ 'wiki_dbname' => $wgDBname ],
			__METHOD__
		)->wiki_extensions;

		if ( is_null( $exts ) ) {
			$extensions = [];
		} else {
			$extensions = (array)explode( ',', $exts );
		}

		if ( in_array( (string)$ext, $extensions ) && !$enable ) {
			$newextensions = array_diff( $extensions, (array)$ext );
		} elseif ( !in_array( $ext, $extensions ) && $enable ) {
			$newextensions = $extensions;
			$newextensions[] = (string)$ext;
			sort( $newextensions );
		} else {
			$this->output( "No change to extension ($ext) state on $wgDBname." );

			return false;
		}

		$dbw->update( 'cw_wikis',
			[ 'wiki_extensions' => (string)implode( ',', $newextensions ) ],
			[ 'wiki_dbname' => $wgDBname ],
			__METHOD__
		);

		Hooks::run( 'ManageWikiModifiedSettings', [ $wgDBname ] );

	}
}

$maintClass = 'ManageWikiToggleExtension';
require_once RUN_MAINTENANCE_IF_MAIN;
