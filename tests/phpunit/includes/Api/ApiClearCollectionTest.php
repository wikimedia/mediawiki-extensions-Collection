<?php

use MediaWiki\Extension\Collection\Session as CollectionSession;
use MediaWiki\Session\SessionManager;

/**
 * Tests for Collection api.php?action=collection&submodule=clearcollection.
 *
 * @group API
 * @group medium
 *
 * @covers \MediaWiki\Extension\Collection\Api\ApiClearCollection
 */
class ApiClearCollectionTest extends ApiTestCase {

	public function testApiClear() {
		CollectionSession::startSession();
		$session = SessionManager::getGlobalSession();
		$session['wsCollection'] = [ 'title' => 'Test', 'items' => [ 0, 1 ] ];
		$session['wsCollectionSuggestBan'] = 'testSuggestBan';
		$session['wsCollectionSuggestProp'] = 'testSuggestProp';

		$this->doApiRequest( [
			'action' => 'collection',
			'submodule' => 'clearcollection',
		] );

		$this->assertArraySubmapSame( [
			'wsCollection' => [
				'title' => '',
				'items' => []
			]
		], iterator_to_array( $session ) );
		$this->assertNull( $session['wsCollectionSuggestBan'] );
		$this->assertNull( $session['wsCollectionSuggestProp'] );
	}

}
