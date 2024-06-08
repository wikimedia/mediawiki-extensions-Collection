<?php

use MediaWiki\Tests\Api\ApiTestCase;
use MediaWiki\Title\Title;

/**
 * Tests for Collection api.php?action=collection&submodule=getbookcreatorboxcontent
 *
 * @group API
 * @group Database
 * @group medium
 *
 * @covers \MediaWiki\Extension\Collection\Api\ApiGetBookCreatorBoxContent
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
		] )[0]['getbookcreatorboxcontent']['html'];

		$this->assertStringContainsString( "Show book (1 page)", $apiResult );

		// Let's use other params
		$apiResult1 = $this->doApiRequest( [
			'action' => 'collection',
			'submodule' => 'getbookcreatorboxcontent',
			'pagename' => $page->getDBkey()
		] )[0]['getbookcreatorboxcontent']['html'];

		$this->assertStringContainsString( "UTPage", $apiResult1 );
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
		] )[0]['getbookcreatorboxcontent']['html'];

		$this->assertStringContainsString( "Show book (0 pages)", $apiResult );

		// When "pagename" is not available, default to Main_Page
		$this->assertStringContainsString( "Main+Page", $apiResult );
	}

}
