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
use MediaWiki\Extension\Collection\Session;
use MediaWiki\Extension\Collection\Suggest;
use SkinTemplate;
use Wikimedia\ParamValidator\ParamValidator;
use Xml;

class ApiSuggestArticleAction extends ApiBase {
	use CollectionTrait;

	public const VALID_ACTIONS = [ 'add', 'remove', 'ban' ];

	/**
	 * execute the API request
	 */
	public function execute() {
		[ 'suggestaction' => $action, 'title' => $title ] = $this->extractRequestParams();

		$result = Suggest::refresh( $action, $title );

		$undoLink = Xml::element( 'a',
			[
				'href' => SkinTemplate::makeSpecialUrl(
					'Book',
					[ 'bookcmd' => 'suggest', 'undo' => $action, 'arttitle' => $title ]
				),
				'onclick' => "collectionSuggestCall('UndoArticle'," .
					Xml::encodeJsVar( [ $action, $title ] ) . "); return false;",
				'title' => $this->msg( 'coll-suggest_undo_tooltip' ),
			],
			$this->msg( 'coll-suggest_undo' )
		);

		// Message keys used:
		// coll-suggest_article_ban
		// coll-suggest_article_add
		// coll-suggest_article_remove
		$result['last_action'] = $this->msg( "coll-suggest_article_$action", $title )
			->rawParams( $undoLink )->parse();
		$result['collection'] = Session::getCollection();

		$this->getResult()->addValue( null, $this->getModuleName(), $result );
	}

	/**
	 * @return array
	 */
	public function getAllowedParams() {
		return [
			'suggestaction' => [
				ParamValidator::PARAM_TYPE => self::VALID_ACTIONS,
				ParamValidator::PARAM_REQUIRED => true,
			],
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
			'action=collection&submodule=suggestarticleaction&suggestaction=add&title=Main_Page'
				=> 'apihelp-collection+suggestarticleaction-example',
		];
	}
}
