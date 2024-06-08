<?php

use MediaWiki\Extension\Collection\Session as CollectionSession;
use MediaWiki\Tests\Api\ApiTestCase;

/**
 * Tests for Collection api.php?action=collection&submodule=sortitems.
 *
 * @group API
 * @group Database
 * @group medium
 *
 * @covers \MediaWiki\Extension\Collection\Api\ApiSortItems
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
			'action' => 'collection',
			'submodule' => 'sortitems',
		] )[0]['sortitems']['collection']['items'];

		$this->assertArrayEquals( $expected, $result );
	}

	public static function provideTestApiSortItems() {
		return [
			'Sorts articles alphabetically' => [
				[
					self::populateArticleFields( 'a' ),
					self::populateArticleFields( 'b' ),
					self::populateArticleFields( 'c' ),
				],
				[
					self::populateArticleFields( 'c' ),
					self::populateArticleFields( 'b' ),
					self::populateArticleFields( 'a' ),
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
					self::populateArticleFields( 'a' ),
					self::populateArticleFields( 'b' ),
					[ 'type' => 'chapter', 'title' => 'aa' ],
					self::populateArticleFields( 'c' ),
				],
				[
					[ 'type' => 'chapter', 'title' => 'cc' ],
					self::populateArticleFields( 'b' ),
					self::populateArticleFields( 'a' ),
					[ 'type' => 'chapter', 'title' => 'aa' ],
					self::populateArticleFields( 'c' ),
				]
			],
			'Sorts articles outside chapters' => [
				[
					self::populateArticleFields( 'a' ),
					self::populateArticleFields( 'b' ),
					[ 'type' => 'chapter', 'title' => 'aa' ],
					self::populateArticleFields( 'c' ),
					self::populateArticleFields( 'd' ),
				],
				[
					self::populateArticleFields( 'b' ),
					self::populateArticleFields( 'a' ),
					[ 'type' => 'chapter', 'title' => 'aa' ],
					self::populateArticleFields( 'd' ),
					self::populateArticleFields( 'c' ),
				]
			],
		];
	}

	private static function populateArticleFields( string $title, array $params = [] ) {
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
