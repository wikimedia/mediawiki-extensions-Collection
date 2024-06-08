<?php

use MediaWiki\Tests\Api\ApiTestCase;

/**
 * Tests for Collection api.php?action=collection&submodule=getpopupdata.
 *
 * @group API
 * @group Database
 * @group medium
 *
 * @covers \MediaWiki\Extension\Collection\Api\ApiGetPopupData
 */
class ApiGetPopupDataTest extends ApiTestCase {

	public function testApiGetPopupData() {
		$page = $this->getExistingTestPage();

		$results = $this->doApiRequest( [
			'action' => 'collection',
			'submodule' => 'getpopupdata',
			'title' => $page->getDBkey()
		] )[0]['getpopupdata'];
		$this->assertEquals( 'add', $results['action'] );

		$this->doApiRequest( [
			'action' => 'collection',
			'submodule' => 'addarticle',
			'namespace' => $page->getNamespace(),
			'title' => $page->getDBkey()
		] );

		$results = $this->doApiRequest( [
			'action' => 'collection',
			'submodule' => 'getpopupdata',
			'title' => $page->getDBkey()
		] )[0]['getpopupdata'];
		$this->assertEquals( 'remove', $results['action'] );
	}

	public function testApiGetPopupData_NoTitle() {
		$this->expectException( ApiUsageException::class );

		$this->doApiRequest( [
			'action' => 'collection',
			'submodule' => 'getpopupdata',
		] );
	}
}
