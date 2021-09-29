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

namespace MediaWiki\Extensions\Collection\Api;

use ApiBase;
use SpecialCollection;
use Wikimedia\ParamValidator\ParamValidator;

class ApiAddArticle extends ApiBase {
	use GetCollectionItemListTrait;

	/**
	 * execute the API request
	 */
	public function execute() {
		$params = $this->extractRequestParams();
		$articleAdded = SpecialCollection::addArticleFromName(
			$params['namespace'],
			$params['title'],
			$params['oldid']
		);

		if ( !$articleAdded ) {
			$this->dieWithError( 'coll-api-addarticle-article-not-added' );
		}

		$this->getCollectionItemListAfterAction();
	}

	public function getAllowedParams() {
		return [
			'namespace' => [
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_REQUIRED => true,
			],
			'title' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
			'oldid' => [
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_DEFAULT => 0,
			],
		];
	}

	/**
	 * @return array examples of the use of this API module
	 */
	protected function getExamplesMessages(): array {
		return [
			'action=collection-addarticle&namespace=0&title=Main_Page&oldid=0'
				=> 'apihelp-collection-addarticle-example',
		];
	}
}
