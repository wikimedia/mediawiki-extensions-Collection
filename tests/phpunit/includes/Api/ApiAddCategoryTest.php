<?php

/**
 * Tests for Collection api.php?action=collection&submodule=addcategory
 *
 * @group API
 * @group Database
 * @group medium
 *
 * @covers \MediaWiki\Extension\Collection\Api\ApiAddCategory
 */
class ApiAddCategoryTest extends ApiTestCase {

	public function testApiAddCategory() {
		// Create a category first e.g. [[Category:TestCat]]
		$category = $this->insertPage( 'TestCat', 'pages', NS_CATEGORY );
		$catFullText = '[[' . $category['title']->getFullText() . ']]';

		$this->assertSame( '[[Category:TestCat]]', $catFullText );

		// Add titles/articles to the category created above
		$this->insertPage( 'Article1', $catFullText, NS_MAIN );
		$this->insertPage( 'Article2', $catFullText, NS_PROJECT );
		$this->insertPage( 'Article3', $catFullText, NS_USER );
		$this->insertPage( 'Article4', $catFullText, NS_USER_TALK );

		// Add category to the user's collection
		$apiResult = $this->doApiRequest( [
			'action' => 'collection',
			'submodule' => 'addcategory',
			'title' => $category['title']->getDBKey()
		] )[0];

		// Assert that articles from the category are in the
		// user's collection as expected.
		$collection = $apiResult['addcategory']['collection']['items'];
		$this->assertCount( 4, $collection );
	}
}
