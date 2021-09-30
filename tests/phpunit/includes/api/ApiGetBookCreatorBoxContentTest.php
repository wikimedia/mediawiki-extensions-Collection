<?php

/**
 * Tests for Collection api.php?action=collection&submodule=getbookcreatorboxcontent
 *
 * @group API
 * @group Database
 * @group medium
 *
 * @covers \MediaWiki\Extensions\Collection\Api\ApiGetBookCreatorBoxContent
 */
class ApiGetBookCreatorBoxContentTest extends ApiTestCase {

	public function testGetBookCreatorBoxContentWithCollectionItems() {
		// Create the page to add to a collection so it's valid.
		$title = Title::makeTitle( NS_PROJECT, 'UTPage' );
		$page = $this->getExistingTestPage( $title );

		$this->doApiRequest( [
			'action' => 'collection',
			'submodule' => 'addarticle',
			'namespace' => NS_PROJECT,
			'title' => $page->getDBkey()
		] )[0];

		// Now attempt to get the box content. We should have 1 page.
		$apiResult = $this->doApiRequest( [
			'action' => 'collection',
			'submodule' => 'getbookcreatorboxcontent',
		] )[0];

		$this->assertStringContainsString(
			"Show book (1 page)",
			$apiResult['collection-result']['html']
		);

		// Let's use other params
		$apiResult1 = $this->doApiRequest( [
			'action' => 'collection',
			'submodule' => 'getbookcreatorboxcontent',
			'pagename' => $page->getDBkey()
		] )[0];

		$this->assertStringContainsString(
			"UTPage",
			$apiResult1['collection-result']['html']
		);
	}

	public function testGetBookCreatorBoxContentWithNoCollectionItems() {
		// Let's just empty the book to be sure
		$this->doApiRequest( [
			'action' => 'collection',
			'submodule' => 'clearcollection',
		] );

		// Now attempt to get the book creator box content
		// with no collection items. We should have 0 pages.
		$apiResult = $this->doApiRequest( [
			'action' => 'collection',
			'submodule' => 'getbookcreatorboxcontent',
		] )[0];

		$this->assertStringContainsString(
			"Show book (0 pages)",
			$apiResult['collection-result']['html']
		);

		// When "pagename" is not avaiable, default to Main_Page
		$this->assertStringContainsString(
			"Main+Page",
			$apiResult['collection-result']['html']
		);
	}

}
