<?php

/**
 * Tests for Collection api.php?action=collection&submodule=addchapter
 *
 * @group API
 * @group medium
 *
 * @covers \MediaWiki\Extension\Collection\Api\ApiAddChapter
 */
class ApiAddChapterTest extends ApiTestCase {

	public function testApiAddChapter() {
		$apiResultChapterAdded = $this->doApiRequest( [
			'action' => 'collection',
			'submodule' => 'addchapter',
			'chaptername' => 'Test chapter'
		] )[0];

		$collection = $apiResultChapterAdded['addchapter']['collection']['items' ];
		$chapterTitle = $apiResultChapterAdded['addchapter']['collection']['items'][0]['title'];
		$chapterType = $apiResultChapterAdded['addchapter']['collection']['items'][0]['type'];

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
			'action' => 'collection',
			'submodule' => 'getcollection'
		] )[0];

		$chapterTitle = $apiResult['getcollection']['items'][0]['title'];

		$this->assertCount( 1, $apiResult['getcollection']['items'] );
		$this->assertSame( 'Test chapter', $chapterTitle );
	}
}
