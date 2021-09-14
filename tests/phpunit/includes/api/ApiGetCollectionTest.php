<?php

use MediaWiki\Session\SessionManager;

/**
 * Tests for Collection api.php?action=collection-list.
 *
 * @group API
 * @group medium
 *
 * @covers \MediaWiki\Extensions\Collection\Api\ApiGetCollection
 */
class ApiGetCollectionTest extends ApiTestCase {
	public function testApiGetCollection() {
		$session = SessionManager::getGlobalSession();
		$session['wsCollection'] = [ 'SessionData1', 'SessionData2' ];

		$apiResult = $this->doApiRequest( [
			'action' => 'collection-list'
		] )[0];

		$expected = [ 'collection-list' => $session['wsCollection'] ];

		$this->assertSame( $expected, $apiResult );
	}

}
