<?php

/**
 * Tests for Collection api.php?action=collection&submodule=settitles
 *
 * @group API
 * @group medium
 *
 * @covers \MediaWiki\Extension\Collection\Api\ApiSetTitles
 */
class ApiSetTitlesTest extends ApiTestCase {

	/**
	 * @dataProvider provideTestApiSetTitles_Good
	 */
	public function testApiSetTitles_Good( array $expected, array $toInsert ) {
		$toInsert['action'] = 'collection';
		$toInsert['submodule'] = 'settitles';
		$this->doApiRequest( $toInsert );

		$collection = CollectionSession::getCollection();
		$this->assertArraySubmapSame( $expected, $collection );

		CollectionSession::clearCollection();
	}

	public function provideTestApiSetTitles_Good() {
		return [
			'Set title' => [
				[
					'title' => 'test-title',
					'subtitle' => '',
					'settings' => []
				],
				[ 'title' => 'test-title' ]
			],
			'Set title and subtitle' => [
				[
					'title' => 'test-title',
					'subtitle' => 'test-subtitle',
					'settings' => []
				],
				[
					'title' => 'test-title',
					'subtitle' => 'test-subtitle'
				]
			],
			'Set title, subtitle, and settings' => [
				[
					'title' => 'test-title',
					'subtitle' => 'test-subtitle',
					'settings' => [ 'test' => 'setting' ]
				],
				[
					'title' => 'test-title',
					'subtitle' => 'test-subtitle',
					'settings' => '{ "test": "setting" }'
				]
			]
		];
	}

	/**
	 * Tests that an error is returned if the title parameter is missing
	 */
	public function testApiSetTitles_BadRequest_MissingTitleParameter() {
		$this->expectException( ApiUsageException::class );

		$this->doApiRequest( [
			'action' => 'collection',
			'submodule' => 'settitles',
			'subtitle' => 'test-subtitle',
		] );
	}
}
