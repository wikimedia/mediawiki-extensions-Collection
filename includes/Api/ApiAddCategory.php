<?php

/*
 * Permission is hereby granted, free of charge, to any person obtaining a
 * copy of this software and associated documentation files (the "Software"),
 * to deal in the Software without restriction, including without limitation
 * the rights to use, copy, modify, merge, publish, distribute, sublicense,
 * and/or sell copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER
 * DEALINGS IN THE SOFTWARE.
 */

namespace MediaWiki\Extension\Collection\Api;

use ApiBase;
use SpecialCollection;
use Wikimedia\ParamValidator\ParamValidator;

class ApiAddCategory extends ApiBase {
	use CollectionTrait;

	/**
	 * execute the API request
	 */
	public function execute() {
		$params = $this->extractRequestParams();
		 /* $articlesAdded = */ SpecialCollection::addCategoryFromName( $params['title'] );

		// TODO: There is an issue with ::addCategoryFromName that makes successfully
		// adding articles within a category to the book to return false instead
		// of true. That is in the case where the number of articles in the category
		// is not greater than the limit. Limit exceeded will never be true hence
		// the check below for $articlesAdded will always pass hence needs fixing.
		/* if ( !$articlesAdded ) {
			$this->dieWithError( 'coll-api-addcategory-category-does-not-exist' );
		} */

		$this->getCollectionItemListAfterAction();
	}

	public function getAllowedParams() {
		return [
			// this really should be 'category' instead of 'title'
			'title' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_DEFAULT => '',
			],
		];
	}

	/**
	 * @return array examples of the use of this API module
	 */
	protected function getExamplesMessages(): array {
		return [
			'action=collection&submodule=addcategory&title=Main_Page'
				=> 'apihelp-collection+addcategory-example',
		];
	}
}
