<?php

use MediaWiki\MainConfigNames;
use MediaWiki\Tests\Api\ApiTestCase;
use MediaWiki\Title\Title;

/**
 * Tests for Collection api.php?action=collection&submodule=addarticle.
 *
 * @group API
 * @group Database
 * @group medium
 *
 * @covers \MediaWiki\Extension\Collection\Api\ApiAddArticle
 */
class ApiAddArticleTest extends ApiTestCase {

	public function testApiAddArticle_Good() {
		// Add a namespace to use for collecting articles
		// Also, set the project namespace to use when testing
		$this->overrideConfigValues( [
			'CommunityCollectionNamespace' => NS_PROJECT,
			MainConfigNames::MetaNamespace => 'Collection',
		] );

		// Create the page to add to a collection so it's valid.
		$title = Title::makeTitle( NS_PROJECT, 'UTPage' );
		$page = $this->getExistingTestPage( $title );

		$apiResultAddArticle = $this->doApiRequest( [
			'action' => 'collection',
			'submodule' => 'addarticle',
			'namespace' => NS_PROJECT,
			'title' => $page->getDBkey()
		] )[0];

		$collectPage = $apiResultAddArticle['addarticle']['collection']['items'][0]['title'];
		$this->assertSame(
			'Collection:UTPage',
			$collectPage,
			"After the article has been added to the user's collection."
		);

		$apiResultList = $this->doApiRequest( [
			'action' => 'collection',
			'submodule' => 'getcollection'
		] )[0];

		$collectPage2 = $apiResultList['getcollection']['items'][0]['title'];
		$this->assertSame(
			'Collection:UTPage',
			$collectPage2,
			"Retrieve user's collection list and see if article is found."
		);
	}

	public function testApiAddArticleBad_Request() {
		$this->expectException( ApiUsageException::class );
		$title = $this->getNonexistingTestPage()->getTitle();

		$this->doApiRequest( [
			'action' => 'collection',
			'submodule' => 'addarticle',
			'namespace' => $title->getNamespace(),
			'title' => $title->getDBkey()
		] )[0];
	}
}
