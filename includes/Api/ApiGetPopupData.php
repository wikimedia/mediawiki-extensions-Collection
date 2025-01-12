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

use MediaWiki\Api\ApiBase;
use MediaWiki\Extension\Collection\Session;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use Wikimedia\ParamValidator\ParamValidator;

class ApiGetPopupData extends ApiBase {
	use CollectionTrait;

	private TitleFactory $titleFactory;
	private WikiPageFactory $wikiPageFactory;

	public function __construct(
		ApiBase $parent,
		string $action,
		TitleFactory $titleFactory,
		WikiPageFactory $wikiPageFactory
	) {
		parent::__construct( $parent->getMain(), $action );
		$this->parent = $parent;
		$this->titleFactory = $titleFactory;
		$this->wikiPageFactory = $wikiPageFactory;
	}

	/**
	 * execute the API request
	 */
	public function execute() {
		[ 'title' => $title ] = $this->extractRequestParams();

		$extensionAssetsPath = $this->getConfig()->get( 'ExtensionAssetsPath' );
		$result = [];
		$imagePath = "$extensionAssetsPath/Collection/images";
		$t = $this->titleFactory->newFromText( $title );
		if ( $t && $t->isRedirect() ) {
			$wikiPage = $this->wikiPageFactory->newFromTitle( $t );
			$t = $wikiPage->followRedirect();
			if ( $t instanceof Title ) {
				$title = $t->getPrefixedText();
			}
		}
		if ( Session::findArticle( $title ) == -1 ) {
			$result['action'] = 'add';
			$result['text'] = $this->msg( 'coll-add_linked_article' )->text();
			$result['img'] = "$imagePath/silk-add.png";
		} else {
			$result['action'] = 'remove';
			$result['text'] = $this->msg( 'coll-remove_linked_article' )->text();
			$result['img'] = "$imagePath/silk-remove.png";
		}
		$result['title'] = $title;

		$this->getResult()->addValue( null, $this->getModuleName(), $result );
	}

	/** @inheritDoc */
	public function getAllowedParams() {
		return [
			'title' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			]
		];
	}

	/**
	 * @return array examples of the use of this API module
	 */
	protected function getExamplesMessages(): array {
		return [
			'action=collection&submodule=getpopupdata&title=foobar'
				=> 'apihelp-collection+getpopupdata-example'
		];
	}
}
