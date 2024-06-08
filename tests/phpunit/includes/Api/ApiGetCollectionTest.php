<?php

use MediaWiki\Session\SessionManager;
use MediaWiki\Tests\Api\ApiTestCase;

/**
 * Tests for Collection api.php?action=collection&submodule=getcollection
 *
 * @group API
 * @group medium
 *
 * @covers \MediaWiki\Extension\Collection\Api\ApiGetCollection
 */
class ApiGetCollectionTest extends ApiTestCase {
	public function testApiGetCollection() {
		$session = SessionManager::getGlobalSession();
		$session['wsCollection'] = [ 'SessionData1', 'SessionData2' ];

		$apiResult = $this->doApiRequest( [
			'action' => 'collection',
			'submodule' => 'getcollection',
		] )[0];

		$expected = [ 'getcollection' => $session['wsCollection'] ];

		$this->assertSame( $expected, $apiResult );
	}

}
