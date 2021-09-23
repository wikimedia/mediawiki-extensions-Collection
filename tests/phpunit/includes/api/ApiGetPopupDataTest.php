<?php

/**
 * Tests for Collection api.php?action=collection-getpopupdata.
 *
 * @group API
 * @group Database
 * @group medium
 *
 * @covers \MediaWiki\Extensions\Collection\Api\ApiGetPopupData
 */
class ApiGetPopupDataTest extends ApiTestCase {

	public function testApiGetPopupData() {
		$page = $this->getExistingTestPage();

		$results = $this->doApiRequest( [
			'action' => 'collection-getpopupdata',
			'title' => $page->getDBkey()
		] )[0]['collection-getpopupdata'];
		$this->assertEquals( 'add', $results['action'] );

		$this->doApiRequest( [
			'action' => 'collection-addarticle',
			'namespace' => $page->getNamespace(),
			'title' => $page->getDBkey()
		] );

		$results = $this->doApiRequest( [
			'action' => 'collection-getpopupdata',
			'title' => $page->getDBkey()
		] )[0]['collection-getpopupdata'];
		$this->assertEquals( 'remove', $results['action'] );
	}

	public function testApiGetPopupData_NoTitle() {
		$this->expectException( ApiUsageException::class );

		$this->doApiRequest( [
			'action' => 'collection-getpopupdata',
		] );
	}
}
