<?php

/**
 * Tests for Collection api.php?action=collection-sortitems.
 *
 * @group API
 * @group Database
 * @group medium
 *
 * @covers \MediaWiki\Extensions\Collection\Api\ApiSortItems
 */
class ApiSortItemsTest extends ApiTestCase {

	/**
	 * @dataProvider provideTestApiSortItems
	 */
	public function testApiSortItems( $expected, $items ) {
		// Collections purge articles that don't exist so make them first
		foreach ( $items as $item ) {
			if ( $item['type'] == 'article' ) {
				$this->getExistingTestPage( $item['title'] );
			}
		}

		CollectionSession::setCollection( [
			'items' => $items
		] );

		$result = $this->doApiRequest( [
			'action' => 'collection-sortitems'
		] )[0]['collection-sortitems']['collection']['items'];

		$this->assertArrayEquals( $expected, $result );
	}

	public function provideTestApiSortItems() {
		return [
			'Sorts articles alphabetically' => [
				[
					$this->populateArticleFields( 'a' ),
					$this->populateArticleFields( 'b' ),
					$this->populateArticleFields( 'c' ),
				],
				[
					$this->populateArticleFields( 'c' ),
					$this->populateArticleFields( 'b' ),
					$this->populateArticleFields( 'a' ),
				]
			],
			'Does not sort chapters' => [
				[
					[ 'type' => 'chapter', 'title' => 'cc' ],
					[ 'type' => 'chapter', 'title' => 'aa' ],
					[ 'type' => 'chapter', 'title' => 'bb' ],
				],
				[
					[ 'type' => 'chapter', 'title' => 'cc' ],
					[ 'type' => 'chapter', 'title' => 'aa' ],
					[ 'type' => 'chapter', 'title' => 'bb' ],
				]
			],
			'Sorts articles within chapters' => [
				[
					[ 'type' => 'chapter', 'title' => 'cc' ],
					$this->populateArticleFields( 'a' ),
					$this->populateArticleFields( 'b' ),
					[ 'type' => 'chapter', 'title' => 'aa' ],
					$this->populateArticleFields( 'c' ),
				],
				[
					[ 'type' => 'chapter', 'title' => 'cc' ],
					$this->populateArticleFields( 'b' ),
					$this->populateArticleFields( 'a' ),
					[ 'type' => 'chapter', 'title' => 'aa' ],
					$this->populateArticleFields( 'c' ),
				]
			],
			'Sorts articles outside chapters' => [
				[
					$this->populateArticleFields( 'a' ),
					$this->populateArticleFields( 'b' ),
					[ 'type' => 'chapter', 'title' => 'aa' ],
					$this->populateArticleFields( 'c' ),
					$this->populateArticleFields( 'd' ),
				],
				[
					$this->populateArticleFields( 'b' ),
					$this->populateArticleFields( 'a' ),
					[ 'type' => 'chapter', 'title' => 'aa' ],
					$this->populateArticleFields( 'd' ),
					$this->populateArticleFields( 'c' ),
				]
			],
		];
	}

	private function populateArticleFields( string $title, array $params = [] ) {
		return array_merge(
			[
				'type' => 'article',
				'title' => $title,
				"content_type" => "text/x-wiki",
				"revision" => "4",
				"latest" => "4",
				"timestamp" => 0,
				"url" => "/",
				"currentVersion" => 1,
			],
			$params
		);
	}
}
