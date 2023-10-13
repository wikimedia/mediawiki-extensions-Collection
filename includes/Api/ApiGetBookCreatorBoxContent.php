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
use MediaWiki\Extension\Collection\Hooks;
use MediaWiki\Title\Title;
use Wikimedia\ParamValidator\ParamValidator;

class ApiGetBookCreatorBoxContent extends ApiBase {
	use CollectionTrait;

	/** @inheritDoc */
	public function execute(): void {
		$params = $this->extractRequestParams();

		$oldid = $params['oldid'] ? (int)$params['oldid'] : 0;
		$title = $params['pagename'] ? Title::newFromText( $params['pagename'] ) : Title::newMainPage();
		$title ??= Title::newMainPage();
		$html = Hooks::getBookCreatorBoxContent( $title, $params['hint'], $oldid );
		$result = [ 'html' => $html ];
		$this->getResult()->addValue( null, $this->getModuleName(), $result );
	}

	/** @inheritDoc */
	public function getAllowedParams(): array {
		return [
			'hint' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_DEFAULT => '',
			],
			'oldid' => [
				ParamValidator::PARAM_TYPE => 'integer',
			],
			'pagename' => [
				ParamValidator::PARAM_TYPE => 'string',
			],
		];
	}

	/** @inheritDoc */
	public function getExamplesMessages(): array {
		return [
			'action=collection&submodule=getbookcreatorboxcontent&hint=Test&oldid=0&pagename=Page'
				=> 'apihelp-collection+getbookcreatorboxcontent-example',
		];
	}
}
