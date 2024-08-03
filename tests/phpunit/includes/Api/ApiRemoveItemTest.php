<?php

use MediaWiki\MainConfigNames;
use MediaWiki\Tests\Api\ApiTestCase;
use MediaWiki\Title\Title;

/**
 * Tests for Collection api.php?action=collection&submodule=removeitem.
 *
 * @group API
 * @group Database
 * @group medium
 *
 * @covers \MediaWiki\Extension\Collection\Api\ApiRemoveItem
 */
class ApiRemoveItemTest extends ApiTestCase {

	protected function setUp(): void {
		parent::setUp();

		// Add a namespace to use for collecting articles
		// Also, set the project namespace to use when testing
		$this->overrideConfigValues( [
			'CommunityCollectionNamespace' => NS_PROJECT,
			MainConfigNames::MetaNamespace => 'Collection',
		] );
	}

	/**
	 * @param string $pageName
	 *
	 * @return WikiPage
	 */
	private function getTestPage( string $pageName = 'UTPage' ) {
		// Create the page to add to a collection so it's valid.
		$title = Title::makeTitle( NS_PROJECT, $pageName );

		return $this->getExistingTestPage( $title );
	}

	public function testApiRemoveItemWithDefaultIndex() {
		$apiResultAddArticle = $this->doApiRequest( [
			'action' => 'collection',
			'submodule' => 'addarticle',
			'namespace' => NS_PROJECT,
			'title' => $this->getTestPage()->getDBkey()
		] )[0];

		$collectionItemZeroTitle = $apiResultAddArticle['addarticle']['collection']['items'][0]['title'];
		$this->assertSame(
			'Collection:UTPage',
			$collectionItemZeroTitle,
			"After the article has been added to the user's collection."
		);

		$apiResult = $this->doApiRequest( [
			'action' => 'collection',
			'submodule' => 'removeitem',
		] )[0];

		$collectionItems = $apiResult['removeitem']['collection']['items'];
		$this->assertSame(
			[],
			$collectionItems,
			"After removing the only item from the collection, it's now empty."
		);
	}

	public function testApiRemoveItemWithSpecificIndex() {
		// Add 3 pages to the collection first.
		$this->doApiRequest( [
			'action' => 'collection',
			'submodule' => 'addarticle',
			'namespace' => NS_PROJECT,
			'title' => $this->getTestPage( 'Page1' )->getDBkey()
		] )[0];

		$this->doApiRequest( [
			'action' => 'collection',
			'submodule' => 'addarticle',
			'namespace' => NS_PROJECT,
			'title' => $this->getTestPage( 'Page2' )->getDBkey()
		] )[0];

		$apiResultAddedArticles = $this->doApiRequest( [
			'action' => 'collection',
			'submodule' => 'addarticle',
			'namespace' => NS_PROJECT,
			'title' => $this->getTestPage( 'Page3' )->getDBkey()
		] )[0];

		// Before removing from index 2, assert we have 3 items in the list now.
		$collectionItems = $apiResultAddedArticles['addarticle']['collection']['items'];
		$this->assertCount(
			3,
			$collectionItems,
			"There should be 3 items in the list."
		);

		// Now remove the item at index 1
		$apiResult = $this->doApiRequest( [
			'action' => 'collection',
			'submodule' => 'removeitem',
			'index' => 1,
		] )[0];

		$collectionItems = $apiResult['removeitem']['collection']['items'];
		$this->assertSame(
			'Collection:Page3',
			$collectionItems[1]['title'],
			"Reindex the items after removing index 1, we now Collection:Page3 at index 2"
		);

		// Grab the collection list and see if it was indeed removed.
		$collection = $this->doApiRequest( [
			'action' => 'collection',
			'submodule' => 'getcollection',
		] )[0];

		// At this point, we should have 2 items. One has been removed above.
		$this->assertCount( 2, $collection['getcollection']['items'] );
	}

	public function testApiRemoveItemWithBadParam() {
		$this->expectException( ApiUsageException::class );
		$this->expectExceptionMessage( 'Invalid value "badparam" for integer parameter "index".' );

		$this->doApiRequest( [
			'action' => 'collection',
			'submodule' => 'removeitem',
			'index' => 'badparam',
		] )[0];
	}
}
