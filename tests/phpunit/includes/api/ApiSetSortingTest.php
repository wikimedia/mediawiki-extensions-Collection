<?php

use MediaWiki\Extension\Collection\Session as CollectionSession;

/**
 * Tests for Collection api.php?action=collection&submodule=setsorting.
 *
 * @group API
 * @group medium
 *
 * @covers \MediaWiki\Extension\Collection\Api\ApiAddArticle
 */
class ApiSetSortingTest extends ApiTestCase {

	public function testApiSetSorting_Good() {
		CollectionSession::startSession();
		CollectionSession::setCollection( [
				'items' => [
					[ 'type' => 'a' ],
					[ 'type' => 'b' ],
					[ 'type' => 'c' ]
				]
			] );

		$result = $this->doApiRequest( [
			'action' => 'collection',
			'submodule' => 'setsorting',
			'items' => '1|0|2',
		] )[0]['setsorting']['collection']['items'];

		$this->assertArraySubmapSame(
			[
				[ 'type' => 'b' ],
				[ 'type' => 'a' ],
				[ 'type' => 'c' ]
			],
			$result
		);

		CollectionSession::clearCollection();
	}

	public function testApiSetSorting_Deletion() {
		CollectionSession::startSession();
		CollectionSession::setCollection( [
			'items' => [
				[ 'type' => 'a' ],
				[ 'type' => 'b' ],
				[ 'type' => 'c' ]
			]
		] );

		$result = $this->doApiRequest( [
			'action' => 'collection',
			'submodule' => 'setsorting',
			'items' => '2|0',
		] )[0]['setsorting']['collection']['items'];

		$this->assertArraySubmapSame(
			[
				[ 'type' => 'c' ],
				[ 'type' => 'a' ]
			],
			$result
		);

		CollectionSession::clearCollection();
	}

	public function testApiSetSorting_UninitializedSession() {
		$result = $this->doApiRequest( [
			'action' => 'collection',
			'submodule' => 'setsorting',
			'items' => '2|0',
		] )[0]['setsorting']['collection']['items'];

		$this->assertCount( 0, $result );
	}

}
