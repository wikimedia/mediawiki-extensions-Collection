<?php

/**
 * Tests for Collection api.php?action=collection-renamechapter
 *
 * @group API
 * @group medium
 *
 * @covers \MediaWiki\Extensions\Collection\Api\ApiRenameChapter
 */
class ApiRenameChapterTest extends ApiTestCase {

	public function testApiAddChapter() {
		// Add about 4 chapters to prep for renaming
		$this->doApiRequest( [
			'action' => 'collection-addchapter',
			'chaptername' => 'Chapter 1'
		] )[0];
		$this->doApiRequest( [
			'action' => 'collection-addchapter',
			'chaptername' => 'Chapter 2'
		] )[0];
		$this->doApiRequest( [
			'action' => 'collection-addchapter',
			'chaptername' => 'Chapter 3'
		] )[0];
		$apiResultChapterAdded = $this->doApiRequest( [
			'action' => 'collection-addchapter',
			'chaptername' => 'Chapter 4'
		] )[0];

		// Assert to make sure the chapters where added
		$collection = $apiResultChapterAdded['collection-addchapter']['collection']['items'];
		$this->assertCount( 4, $collection );
		$chapterTitle = $apiResultChapterAdded['collection-addchapter']['collection']['items'][0]['title'];
		$chapterType = $apiResultChapterAdded['collection-addchapter']['collection']['items'][0]['type'];

		$this->assertIsArray( $collection );
		$this->assertSame(
			'Chapter 1',
			$chapterTitle,
			"After the chapter has been added to the user's collection."
		);
		$this->assertSame(
			'chapter',
			$chapterType,
			"After the chapter has been added to the user's collection."
		);

		// Let's rename chapter 4 and see if rename API works.
		$apiResultChapterRenamed = $this->doApiRequest( [
			'action' => 'collection-renamechapter',
			'index' => 3,
			'chaptername' => 'Chapter 4 renamed'
		] )[0];

		$collection = $apiResultChapterRenamed['collection-renamechapter']['collection']['items'];
		$this->assertCount( 4, $collection );
		$chapterTitle = $apiResultChapterRenamed['collection-renamechapter']['collection']['items'][3]['title'];
		$chapterType = $apiResultChapterRenamed['collection-renamechapter']['collection']['items'][3]['type'];

		$this->assertIsArray( $collection );
		$this->assertSame(
			'Chapter 4 renamed',
			$chapterTitle,
			"After the chapter has been renamed to the user's collection."
		);
		$this->assertSame(
			'chapter',
			$chapterType,
			"After the chapter has been renamed to the user's collection."
		);

		// Check the user's collection again
		$apiResult = $this->doApiRequest( [
			'action' => 'collection-list'
		] )[0];
		$collection = $apiResult['collection-list']['items'];

		$this->assertCount( 4, $collection, '4 chapters in the collection' );
		$this->assertSame( 'Chapter 4 renamed', $collection[3]['title'] );
	}

	public function testApiRenameChapterWithoutIndex() {
		$this->expectException( ApiUsageException::class );

		$this->doApiRequest( [
			'action' => 'collection-renamechapter',
			'chaptername' => 'Test',
		] )[0];
	}

	public function testApiRenameChapterWithoutChaptername() {
		$this->expectException( ApiUsageException::class );

		$this->doApiRequest( [
			'action' => 'collection-renamechapter',
			'index' => 8,
		] )[0];
	}
}
