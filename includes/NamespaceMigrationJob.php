<?php

/**
 * Used on namespace creation and deletion to move pages into and out of namespaces
 */
class NamespaceMigrationJob extends Job {
	public function __construct( $title, $params ) {
		parent::__construct( 'NamespaceMigrationJob', $title, $params );
	}

	public function run() {
		$dbw = wfGetDB( DB_MASTER );

		if ( $this->params['action'] == 'delete' ) {
			$nsSearch = $this->params['nsID'];
			$pagePrefix = '';
			$nsTo = $this->params['nsNew'];
		} else {
			$nsSearch = 0;
			$pagePrefix = $this->params['nsName'] . ':';
			$nsTo = $this->params['nsID'];
		}

		$res = $dbw->select(
			'page',
			[
				'page_title',
				'page_id',
			],
			[
				'page_namespace' => $nsSearch,
				"page_title LIKE '$pagePrefix%'"
			],
			__METHOD__
		);

		foreach ( $res as $row ) {
			$pageTitle = $row->page_title;
			$pageID = $row->page_id;

			if ( $nsSearch == 0 ) {
				$replace = '';
				$newTitle = str_replace( $pagePrefix, $replace, $pageTitle );
			} else {
				$newTitle = $pageTitle;
			}

			if ( $this->params['action'] !== 'create' ) {
				if ( $this->pageExists( $newTitle, $nsTo, $dbw ) ) {
					$newTitle .= '~' . $this->params['nsName'];
				}
			}

			$dbw->update(
				'page',
				[
					'page_namespace' => $nsTo,
					'page_title' => $newTitle
				],
				[
					'page_id' => $pageID
				],
				__METHOD__
			);

			// Update recentchanges as this is not normally done
			$dbw->update(
				'recentchanges',
				[
					'rc_namespace' => $nsTo,
					'rc_title' => $newTitle
				],
				[
					'rc_namespace' => $nsSearch,
					'rc_title' => $pageTitle
				],
				__METHOD__
			);
		}
	}

	private function pageExists( $pageName, $nsID, $dbw ) {
		$row = $dbw->selectRow(
			'page',
			'page_title',
			[
				'page_title' => $pageName,
				'page_namespace' => $nsID
			],
			__METHOD__
		);

		if ( $row ) {
			return true;
		} else {
			return false;
		}
	}
}