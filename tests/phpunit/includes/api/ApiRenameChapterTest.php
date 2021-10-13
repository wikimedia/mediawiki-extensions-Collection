<?php

/**
 * Tests for Collection api.php?action=collection&submodule=renamechapter
 *
 * @group API
 * @group medium
 *
 * @covers \MediaWiki\Extensions\Collection\Api\ApiRenameChapter
 */
class ApiRenameChapterTest extends ApiTestCase {

	public function testApiRenameChapter() {
		// Add about 4 chapters to prep for renaming
		$this->doApiRequest( [
			'action' => 'collection',
			'submodule' => 'addchapter',
			'chaptername' => 'Chapter 1'
		] );
		$this->doApiRequest( [
			'action' => 'collection',
			'submodule' => 'addchapter',
			'chaptername' => 'Chapter 2'
		] );
		$this->doApiRequest( [
			'action' => 'collection',
			'submodule' => 'addchapter',
			'chaptername' => 'Chapter 3'
		] );
		$apiResultChapterAdded = $this->doApiRequest( [
			'action' => 'collection',
			'submodule' => 'addchapter',
			'chaptername' => 'Chapter 4'
		] )[0]['addchapter']['collection']['items'];

		// Assert to make sure the chapters where added
		$collection = $apiResultChapterAdded;
		$this->assertCount( 4, $collection );
		$chapterTitle = $apiResultChapterAdded[0]['title'];
		$chapterType = $apiResultChapterAdded[0]['type'];

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
			'action' => 'collection',
			'submodule' => 'renamechapter',
			'index' => 3,
			'chaptername' => 'Chapter 4 renamed'
		] )[0]['renamechapter']['collection']['items'];

		$collection = $apiResultChapterRenamed;
		$this->assertCount( 4, $collection );
		$chapterTitle = $apiResultChapterRenamed[3]['title'];
		$chapterType = $apiResultChapterRenamed[3]['type'];

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
			'action' => 'collection',
			'submodule' => 'getcollection',
		] )[0];
		$collection = $apiResult['getcollection']['items'];

		$this->assertCount( 4, $collection, '4 chapters in the collection' );
		$this->assertSame( 'Chapter 4 renamed', $collection[3]['title'] );
	}

	public function testApiRenameChapterWithoutIndex() {
		$this->expectException( ApiUsageException::class );

		$this->doApiRequest( [
			'action' => 'collection',
			'submodule' => 'renamechapter',
			'chaptername' => 'Test',
		] )[0];
	}

	public function testApiRenameChapterWithoutChaptername() {
		$this->expectException( ApiUsageException::class );

		$this->doApiRequest( [
			'action' => 'collection',
			'submodule' => 'renamechapter',
			'index' => 8,
		] )[0];
	}

	public function testApiRenameChapterWithIndexOutOfRange() {
		// Add about 2 chapters to prep for renaming
		$this->doApiRequest( [
			'action' => 'collection',
			'submodule' => 'addchapter',
			'chaptername' => 'Chapter 1'
		] );
		$apiResultChapterAdded = $this->doApiRequest( [
			'action' => 'collection',
			'submodule' => 'addchapter',
			'chaptername' => 'Chapter 2'
		] );

		// Let's rename chapter 4 and see if rename API works.
		$apiResultChapterRenamed = $this->doApiRequest( [
			'action' => 'collection',
			'submodule' => 'renamechapter',
			'index' => 5, // try to rename a chapter not in the book - index out of range
			'chaptername' => 'Chapter 5 renamed'
		] )[0]['renamechapter']['collection']['items'];

		// Nothing got renamed, still 2 chapters
		$collection = $apiResultChapterRenamed;
		$this->assertCount( 2, $collection );

		// Check the user's collection again
		$apiResult = $this->doApiRequest( [
			'action' => 'collection',
			'submodule' => 'getcollection',
		] )[0];
		$collection = $apiResult['getcollection']['items'];

		$this->assertCount( 2, $collection, '2 chapters in the collection' );
	}
}
