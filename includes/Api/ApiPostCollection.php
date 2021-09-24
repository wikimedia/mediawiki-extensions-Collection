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
use FormatJson;
use MediaWiki\Session\SessionManager;
use SpecialPage;
use Wikimedia\ParamValidator\ParamValidator;

class ApiPostCollection extends ApiBase {
	use CollectionTrait;

	/**
	 * execute the API request
	 */
	public function execute() {
		$session = SessionManager::getGlobalSession();
		$session->persist();

		$params = $this->extractRequestParams();
		$collection = FormatJson::decode( $params['collection'], true );
		$apiResults = $this->getResult();

		if ( $collection === null ) {
			$this->dieWithError( 'coll-api-invalid-collection' );
		}

		$collection['enabled'] = true;
		$session['wsCollection'] = $collection;

		$title = SpecialPage::getTitleFor( 'Book' );
		$redirecturl = wfExpandUrl( $title->getFullURL(), PROTO_CURRENT );
		$apiResults->addValue(
			null,
			'response message',
			FormatJson::encode( [ 'redirect_url' => $redirecturl ] )
		);
	}

	public function getAllowedParams() {
		return [
			'collection' => [
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
			'action=collection&submodule=postcollection&collection={"title":"BookTitle","items":[{"type":"article",
			"content_type":"text/x-wiki","title":"Test","revision":"1","latest":"1","timestamp":"1631548781",
			"url":"http://t.wiki/Test","currentVersion":1}]}'
				=> 'apihelp-collection+postcollection-example',
		];
	}
}
