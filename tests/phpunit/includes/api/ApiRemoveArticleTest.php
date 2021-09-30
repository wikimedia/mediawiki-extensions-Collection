<?php

/**
 * Tests for Collection api.php?action=collection&submodule=removearticle
 *
 * @group API
 * @group Database
 * @group medium
 *
 * @covers \MediaWiki\Extensions\Collection\Api\ApiRemoveArticle
 */
class ApiRemoveArticleTest extends ApiTestCase {

	public function testApiRemoveArticle_Good() {
		// Add a namespace to use for collecting articles
		// Also, set the project namespace to use when testing
		$this->setMwGlobals( [
			'wgCommunityCollectionNamespace' => NS_PROJECT,
			'wgMetaNamespace' => 'Collection',
		] );

		// First add an article in the collection before removing
		$title = Title::makeTitle( NS_PROJECT, 'UTPage' );
		$page = $this->getExistingTestPage( $title );

		$this->doApiRequest( [
			'action' => 'collection',
			'submodule' => 'addarticle',
			'namespace' => NS_PROJECT,
			'title' => $page->getDBkey()
		] )[0];

		// Now, remove the article from the collection
		$apiResultRemoveArticle = $this->doApiRequest( [
			'action' => 'collection',
			'submodule' => 'removearticle',
			'namespace' => NS_PROJECT,
			'title' => $page->getDBkey()
		] )[0];

		$collect = $apiResultRemoveArticle['removearticle']['collection']['items'];

		// Make sure that the single item added is removed,
		// because here we have an empty array (no items)
		$this->assertSame(
			[],
			$collect,
			"After the article added has been removed from the user's collection."
		);
	}

	public function testApiRemoveArticleBad_Request() {
		$this->expectException( ApiUsageException::class );
		$title = $this->getNonexistingTestPage()->getTitle();

		$this->doApiRequest( [
			'action' => 'collection',
			'submodule' => 'removearticle',
			'namespace' => $title->getNamespace(),
			'title' => $title->getDBkey()
		] )[0];
	}
}
