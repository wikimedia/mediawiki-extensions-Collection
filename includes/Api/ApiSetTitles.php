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
use FormatJson;
use MediaWiki\Extension\Collection\Specials\SpecialCollection;
use Wikimedia\ParamValidator\ParamValidator;

class ApiSetTitles extends ApiBase {
	use CollectionTrait;

	/**
	 * execute the API request
	 */
	public function execute() {
		[ 'title' => $title, 'subtitle' => $subtitle, 'settings' => $settings ] = $this->extractRequestParams();

		SpecialCollection::setTitles( $title, $subtitle );
		$settings = FormatJson::decode( $settings, true );
		if ( is_array( $settings ) ) {
			SpecialCollection::setSettings( $settings );
		}
		$this->getCollectionItemListAfterAction();
	}

	/** @inheritDoc */
	public function getAllowedParams() {
		return [
			'title' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true
			],
			'subtitle' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_DEFAULT => ''
			],
			'settings' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_DEFAULT => ''
			],
		];
	}

	/**
	 * @return array examples of the use of this API module
	 */
	protected function getExamplesMessages(): array {
		return [
			'action=collection&submodule=settitles&title=foo&subtitle=bar'
				=> 'apihelp-collection+settitles-example',
			'action=collection&submodule=settitles&title=foo&subtitle=bar&' .
			'settings={"papersize":"a4","toc":"auto","columns":"2"}'
				=> 'apihelp-collection+settitles-settings-example'
		];
	}
}
