<?php

/**
 * Tests for Collection api.php?action=collection-addchapter
 *
 * @group API
 * @group medium
 *
 * @covers \MediaWiki\Extensions\Collection\Api\ApiAddChapter
 */
class ApiAddChapterTest extends ApiTestCase {

	public function testApiAddChapter() {
		$apiResultChapterAdded = $this->doApiRequest( [
			'action' => 'collection-addchapter',
			'chaptername' => 'Test chapter'
		] )[0];

		$collection = $apiResultChapterAdded['collection-addchapter']['collection']['items' ];
		$chapterTitle = $apiResultChapterAdded['collection-addchapter']['collection']['items'][0]['title'];
		$chapterType = $apiResultChapterAdded['collection-addchapter']['collection']['items'][0]['type'];

		$this->assertIsArray( $collection );
		$this->assertSame(
			'Test chapter',
			$chapterTitle,
			"After the chapter has been added to the user's collection."
		);

		$this->assertSame(
			'chapter',
			$chapterType,
			"After the chapter has been added to the user's collection."
		);

		// Assert that the chapters were indeed added
		$apiResult = $this->doApiRequest( [
			'action' => 'collection-list',
		] )[0];

		$chapterTitle = $apiResult['collection-list']['items'][0]['title'];

		$this->assertCount( 1, $apiResult['collection-list']['items'] );
		$this->assertSame( 'Test chapter', $chapterTitle );
	}
}
