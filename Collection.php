<?php

use MediaWiki\Session\SessionManager;

/**
 * Collection Extension for MediaWiki
 *
 * Copyright (C) PediaPress GmbH
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 */

# Not a valid entry point, skip unless MEDIAWIKI is defined
if ( !defined( 'MEDIAWIKI' ) ) {
	echo <<<EOT
To install the Collection extension, put the following line in LocalSettings.php:
require_once( "\$IP/extensions/Collection/Collection.php" );
EOT;
	exit( 1 );
}

// Load stuff already converted to extension registration.
wfLoadExtension( 'Collection', __DIR__ . '/extension-wip.json' );

# register global Ajax functions:

function wfAjaxGetCollection() {
	$session = SessionManager::getGlobalSession();
	if ( isset( $session['wsCollection'] ) ) {
		$collection = $session['wsCollection'];
	} else {
		$collection = [];
	}
	$r = new AjaxResponse( FormatJson::encode( [ 'collection' => $collection ] ) );
	$r->setContentType( 'application/json' );
	return $r;
}

$wgAjaxExportList[] = 'wfAjaxGetCollection';

function wfAjaxPostCollection( $collection = '', $redirect = '' ) {
	$session = SessionManager::getGlobalSession();
	$session->persist();

	$collection = FormatJson::decode( $collection, true );
	$collection['enabled'] = true;
	$session['wsCollection'] = $collection;
	$r = new AjaxResponse();
	if ( $redirect ) {
		$title = Title::newFromText( $redirect );
		$redirecturl = wfExpandUrl( $title->getFullURL(), PROTO_CURRENT );
		$r->setResponseCode( 302 );
		header( 'Location: ' . $redirecturl );
	} else {
		$title = SpecialPage::getTitleFor( 'Book' );
		$redirecturl = wfExpandUrl( $title->getFullURL(), PROTO_CURRENT );
		$r->setContentType( 'application/json' );
		$r->addText( FormatJson::encode( [ 'redirect_url' => $redirecturl ] ) );
	}
	return $r;
}

$wgAjaxExportList[] = 'wfAjaxPostCollection';

function wfAjaxGetMWServeStatus( $collection_id = '', $writer = 'rl' ) {
	$response = CollectionRenderingAPI::instance( $writer )
		->getRenderStatus( $collection_id );
	$result = $response->response;
	if ( isset( $result['status']['progress'] ) ) {
		$result['status']['progress'] = number_format( $result['status']['progress'], 2, '.', '' );
	}
	$r = new AjaxResponse( FormatJson::encode( $result ) );
	$r->setContentType( 'application/json' );
	return $r;
}

$wgAjaxExportList[] = 'wfAjaxGetMWServeStatus';

function wfAjaxCollectionAddArticle( $namespace = 0, $title = '', $oldid = '' ) {
	SpecialCollection::addArticleFromName( $namespace, $title, $oldid );
	return wfAjaxCollectionGetItemList();
}

$wgAjaxExportList[] = 'wfAjaxCollectionAddArticle';

function wfAjaxCollectionRemoveArticle( $namespace = 0, $title = '', $oldid = '' ) {
	SpecialCollection::removeArticleFromName( $namespace, $title, $oldid );
	return wfAjaxCollectionGetItemList();
}

$wgAjaxExportList[] = 'wfAjaxCollectionRemoveArticle';

function wfAjaxCollectionAddCategory( $title = '' ) {
	SpecialCollection::addCategoryFromName( $title );
	return wfAjaxCollectionGetItemList();
}

$wgAjaxExportList[] = 'wfAjaxCollectionAddCategory';

function wfAjaxCollectionGetBookCreatorBoxContent(
	$ajaxHint = '',
	$oldid = null,
	$pageName = null
) {
	if ( !is_null( $oldid ) ) {
		$oldid = intval( $oldid );
	}

	$title = null;
	if ( !is_null( $pageName ) ) {
		$title = Title::newFromText( $pageName );
	}
	if ( is_null( $title ) ) {
		$title = Title::newMainPage();
	}

	$html = CollectionHooks::getBookCreatorBoxContent( $title, $ajaxHint, $oldid );

	$result = [];
	$result['html'] = $html;
	$r = new AjaxResponse( FormatJson::encode( $result ) );
	$r->setContentType( 'application/json' );
	return $r;
}

$wgAjaxExportList[] = 'wfAjaxCollectionGetBookCreatorBoxContent';

function wfAjaxCollectionGetItemList() {
	$collection = CollectionSession::getCollection();

	$template = new CollectionListTemplate();
	$template->set( 'collection', $collection );
	$template->set( 'is_ajax', true );

	$result = [];
	$result['html'] = $template->getHTML();
	$result['collection'] = $collection;
	$r = new AjaxResponse( FormatJson::encode( $result ) );
	$r->setContentType( 'application/json' );
	return $r;
}

$wgAjaxExportList[] = 'wfAjaxCollectionGetItemList';

function wfAjaxCollectionRemoveItem( $index ) {
	SpecialCollection::removeItem( (int)$index );
	return wfAjaxCollectionGetItemList();
}

$wgAjaxExportList[] = 'wfAjaxCollectionRemoveItem';

function wfAjaxCollectionAddChapter( $name ) {
	SpecialCollection::addChapter( $name );
	return wfAjaxCollectionGetItemList();
}

$wgAjaxExportList[] = 'wfAjaxCollectionAddChapter';

function wfAjaxCollectionRenameChapter( $index, $name ) {
	SpecialCollection::renameChapter( (int)$index, $name );
	return wfAjaxCollectionGetItemList();
}

$wgAjaxExportList[] = 'wfAjaxCollectionRenameChapter';

function wfAjaxCollectionSetTitles( $title, $subtitle, $settings = '' ) {
	SpecialCollection::setTitles( $title, $subtitle );
	$settings = FormatJson::decode( $settings, true );
	if ( is_array( $settings ) ) {
		SpecialCollection::setSettings( $settings );
	}
	return wfAjaxCollectionGetItemList();
}

$wgAjaxExportList[] = 'wfAjaxCollectionSetTitles';

function wfAjaxCollectionSetSorting( $items_string ) {
	$parsed = [];
	parse_str( $items_string, $parsed );
	$items = [];
	foreach ( $parsed['item'] as $s ) {
		if ( is_numeric( $s ) ) {
			$items[] = intval( $s );
		}
	}
	SpecialCollection::setSorting( $items );
	return wfAjaxCollectionGetItemList();
}

$wgAjaxExportList[] = 'wfAjaxCollectionSetSorting';

function wfAjaxCollectionClear() {
	CollectionSession::clearCollection();
	CollectionSuggest::clear();
	return wfAjaxCollectionGetItemList();
}

$wgAjaxExportList[] = 'wfAjaxCollectionClear';

function wfAjaxCollectionGetPopupData( $title ) {
	global $wgExtensionAssetsPath;

	$result = [];
	$imagePath = "$wgExtensionAssetsPath/Collection/images";
	$t = Title::newFromText( $title );
	if ( $t && $t->isRedirect() ) {
		$wikiPage = WikiPage::factory( $t );
		$t = $wikiPage->followRedirect();
		if ( $t instanceof Title ) {
			$title = $t->getPrefixedText();
		}
	}
	if ( CollectionSession::findArticle( $title ) == - 1 ) {
		$result['action'] = 'add';
		$result['text'] = wfMessage( 'coll-add_linked_article' )->text();
		$result['img'] = "$imagePath/silk-add.png";
	} else {
		$result['action'] = 'remove';
		$result['text'] = wfMessage( 'coll-remove_linked_article' )->text();
		$result['img'] = "$imagePath/silk-remove.png";
	}
	$result['title'] = $title;
	$r = new AjaxResponse( FormatJson::encode( $result ) );
	$r->setContentType( 'application/json' );

	return $r;
}

$wgAjaxExportList[] = 'wfAjaxCollectionGetPopupData';

/**
 * Backend of several following SAJAX function handlers...
 * @param string $action provided by the specific handlers internally
 * @param string $article title passed in from client
 * @return AjaxResponse with JSON-encoded array including HTML fragment.
 */
function wfCollectionSuggestAction( $action, $article ) {
	$result = CollectionSuggest::refresh( $action, $article );
	$undoLink = Xml::element( 'a',
		[
			'href' => SkinTemplate::makeSpecialUrl(
				'Book',
				[ 'bookcmd' => 'suggest', 'undo' => $action, 'arttitle' => $article ]
			),
			'onclick' => "collectionSuggestCall('UndoArticle'," .
				Xml::encodeJsVar( [ $action, $article ] ) . "); return false;",
			'title' => wfMessage( 'coll-suggest_undo_tooltip' )->text(),
		],
		wfMessage( 'coll-suggest_undo' )->text()
	);
	// Message keys used:
	// coll-suggest_article_ban
	// coll-suggest_article_add
	// coll-suggest_article_remove
	$result['last_action'] = wfMessage( "coll-suggest_article_$action", $article )
		->rawParams( $undoLink )->parse();
	$result['collection'] = CollectionSession::getCollection();
	$r = new AjaxResponse( FormatJson::encode( $result ) );
	$r->setContentType( 'application/json' );
	return $r;
}

function wfAjaxCollectionSuggestBanArticle( $article ) {
	return wfCollectionSuggestAction( 'ban', $article );
}

$wgAjaxExportList[] = 'wfAjaxCollectionSuggestBanArticle';

function wfAjaxCollectionSuggestAddArticle( $article ) {
	return wfCollectionSuggestAction( 'add', $article );
}

$wgAjaxExportList[] = 'wfAjaxCollectionSuggestAddArticle';

function wfAjaxCollectionSuggestRemoveArticle( $article ) {
	return wfCollectionSuggestAction( 'remove', $article );
}

$wgAjaxExportList[] = 'wfAjaxCollectionSuggestRemoveArticle';

function wfAjaxCollectionSuggestUndoArticle( $lastAction, $article ) {
	$result = CollectionSuggest::undo( $lastAction, $article );
	$r = new AjaxResponse( FormatJson::encode( $result ) );
	$r->setContentType( 'application/json' );
	return $r;
}

$wgAjaxExportList[] = 'wfAjaxCollectionSuggestUndoArticle';

function wfAjaxCollectionSortItems() {
	SpecialCollection::sortItems();
	return wfAjaxCollectionGetItemList();
}

$wgAjaxExportList[] = 'wfAjaxCollectionSortItems';
