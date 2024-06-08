<?php

use MediaWiki\Tests\Api\ApiTestCase;

/**
 * Tests for Collection api.php?action=collection&submodule=postcollection
 *
 * @group API
 * @group medium
 *
 * @covers \MediaWiki\Extension\Collection\Api\ApiPostCollection
 */
class ApiPostCollectionTest extends ApiTestCase {

	public function testApiPostCollection() {
		// Post an article to the user's collection
		$apiPostCollectionResult = $this->doApiRequest( [
			'action' => 'collection',
			'submodule' => 'postcollection',
			'collection' => '{"title":"Book Title","items":[{"type":"article","content_type":"text/x-wiki",
				"title":"Test","revision":"1","latest":"1","timestamp":"1631548781",
				"url":"http://t.wiki/Test","currentVersion":1}]}',
		] );

		$this->assertArrayHasKey( "response message", $apiPostCollectionResult[0] );

		// Get the collection and see
		$apiGetCollectionResult = $this->doApiRequest( [
			'action' => 'collection',
			'submodule' => 'getcollection',
		] )[0]['getcollection'];

		$bookTitle = $apiGetCollectionResult['title'];
		$collectionTitle = $apiGetCollectionResult['items'][0]['title'];

		$this->assertSame( 'Book Title', $bookTitle );
		$this->assertSame( 'Test', $collectionTitle );
	}

	public function testApiPostCollectionWithInvalidCollection() {
		$this->expectException( ApiUsageException::class );

		// Post an article to the user's collection
		$this->doApiRequest( [
			'action' => 'collection',
			'submodule' => 'postcollection',
			'collection' => '',
			'redirect' => '',
		] );
	}
}
