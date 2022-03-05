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
use CollectionListTemplate;
use CollectionSession;

trait CollectionTrait {
	/** @var ApiBase */
	private $parent;

	/**
	 * Get the parent module.
	 * @return ApiBase
	 */
	public function getParent() {
		return $this->parent;
	}

	/**
	 * @param ApiBase $parent
	 * @param string $moduleName
	 */
	public function __construct( ApiBase $parent, $moduleName ) {
		// @phan-suppress-next-line PhanTraitParentReference
		parent::__construct( $parent->getMain(), $moduleName );
		$this->parent = $parent;
	}

	/**
	 * Used to get the list of items from a user's collection after
	 * an article has been added to their collection.
	 */
	public function getCollectionItemListAfterAction() {
		$collection = CollectionSession::getCollection();
		$template = new CollectionListTemplate();
		$template->set( 'collection', $collection );

		$result = [
			'html' => $template->getHTML(),
			'collection' => $collection
		];

		// @phan-suppress-next-line PhanUndeclaredMethod
		$this->getResult()->addValue( null, $this->getModuleName(), $result );
	}
}
