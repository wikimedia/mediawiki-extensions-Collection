<?php

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

$wgExtensionCredits['specialpage'][] = [
	'path' => __FILE__,
	'name' => 'Collection',
	'version' => '1.7.0',
	'author' => [ 'PediaPress GmbH', 'Siebrand Mazeland', 'Marcin Cieślak' ],
	'url' => 'https://www.mediawiki.org/wiki/Extension:Collection',
	'descriptionmsg' => 'coll-desc',
	'license-name' => 'GPL-2.0+',
];

# ==============================================================================

# Configuration:

/** URL of mw-serve render server */
$wgCollectionMWServeURL = 'https://tools.pediapress.com/mw-serve/';

/** Login credentials to this MediaWiki as 'USERNAME:PASSWORD' string */
$wgCollectionMWServeCredentials = null;

/** PEM-encoded SSL certificate for the mw-serve render server to pass to CURL */
$wgCollectionMWServeCert = null;

/** Array of namespaces that can be added to a collection */
$wgCollectionArticleNamespaces = [
	NS_MAIN,
	NS_TALK,
	NS_USER,
	NS_USER_TALK,
	NS_PROJECT,
	NS_PROJECT_TALK,
	NS_MEDIAWIKI,
	NS_MEDIAWIKI_TALK,
	100,
	101,
	102,
	103,
	104,
	105,
	106,
	107,
	108,
	109,
	110,
	111,
];

/** Namespace for "community books" */
$wgCommunityCollectionNamespace = NS_PROJECT;

/** Maximum no. of articles in a book */
$wgCollectionMaxArticles = 500;

/** Name of license */
$wgCollectionLicenseName = null;

/** HTTP(s) URL pointing to license in wikitext format: */
$wgCollectionLicenseURL = null;

/** List of available download formats,
		as mapping of mwlib writer to format name */
$wgCollectionFormats = [
	'rl' => 'PDF',
];

/** Additional renderer options for collections. Format is as for
 * HTMLForm::loadInputFromParameters. Note that fieldnames may only contain
 * [a-zA-Z0-9_-], and values may not contain pipes or newlines. If the
 * 'options' field is an array, keys will be interpreted as messages.
 */
$wgCollectionRendererSettings = [
	'papersize' => [
		'type' => 'select',
		'label-message' => 'coll-setting-papersize',
		'default' => 'a4',
		'options' => [
			'coll-setting-papersize-a4' => 'a4',
			'coll-setting-papersize-letter' => 'letter',
		],
	],
	'toc' => [
		'type' => 'select',
		'label-message' => 'coll-setting-toc',
		'default' => 'auto',
		'options' => [
			'coll-setting-toc-auto' => 'auto',
			'coll-setting-toc-yes' => 'yes',
			'coll-setting-toc-no' => 'no',
		]
	],
	'columns' => [
		'type' => 'select',
		'label-message' => 'coll-setting-columns',
		'default' => '2',
		'options' => [
			'coll-setting-columns-1' => '1',
			'coll-setting-columns-2' => '2',
		],
	],
];

/** Some commands require an external server
 */
$wgCollectionCommandToServeURL = [];

/** For formats which rendering depends on an external server
*/
$wgCollectionFormatToServeURL = [];

$wgCollectionContentTypeToFilename = [
	'application/pdf' => 'collection.pdf',
	'application/vnd.oasis.opendocument.text' => 'collection.odt',
	'text/plain' => 'collection.txt',
];

$wgCollectionPortletFormats = [ 'rl' ];

$wgCollectionPortletForLoggedInUsersOnly = false;

$wgCollectionMaxSuggestions = 10;

$wgCollectionSuggestCheapWeightThreshhold = 50;

$wgCollectionSuggestThreshhold = 100;

$wgCollectionPODPartners = [
	'pediapress' => [
		'name' => 'PediaPress',
		'url' => 'https://pediapress.com/',
		'posturl' => 'https://pediapress.com/api/collections/',
		'infopagetitle' => 'coll-order_info_article',
	],
];

# Optional notes that are displayed on the download screen for the rendered
# document. Each entry is a message key.
$wgCollectionShowRenderNotes = [
	'coll-rendering_finished_note_not_satisfied',
];

# ==============================================================================

# register Special:Book:
$wgAutoloadClasses['SpecialCollection'] = __DIR__ . '/Collection.body.php';
$wgAutoloadClasses['CollectionSession'] = __DIR__ . '/Collection.session.php';
$wgAutoloadClasses['CollectionHooks'] = __DIR__ . '/Collection.hooks.php';
$wgAutoloadClasses['CollectionSuggest'] = __DIR__ . '/Collection.suggest.php';
$wgAutoloadClasses['CollectionProposals'] = __DIR__ . '/Collection.suggest.php';

$wgAutoloadClasses['SpecialRenderBook'] = __DIR__ . '/SpecialRenderBook.php';
$wgAutoloadClasses[\MediaWiki\Extensions\Collection\DataProvider::class]
	= __DIR__ . '/includes/DataProvider.php';
$wgAutoloadClasses[\MediaWiki\Extensions\Collection\ElectronVirtualRestService::class]
	= __DIR__ . '/includes/ElectronVirtualRestService.php';
$wgAutoloadClasses[\MediaWiki\Extensions\Collection\BookRenderer::class]
	= __DIR__ . '/includes/BookRenderer.php';
$wgAutoloadClasses[MediaWiki\Extensions\Collection\RemexCollectionMunger::class]
	= __DIR__ . '/includes/RemexCollectionMunger.php';
$wgAutoloadClasses[\MediaWiki\Extensions\Collection\HeadingCounter::class]
	= __DIR__ . '/includes/HeadingCounter.php';
$wgAutoloadClasses[\MediaWiki\Extensions\Collection\BookRenderingMediator::class]
	= __DIR__ . '/includes/BookRenderingMediator.php';

$wgAutoloadClasses['CollectionPageTemplate'] = __DIR__ . '/templates/CollectionPageTemplate.php';
$wgAutoloadClasses['CollectionListTemplate'] = __DIR__ . '/templates/CollectionListTemplate.php';
$wgAutoloadClasses['CollectionLoadOverwriteTemplate'] =
	__DIR__ . '/templates/CollectionLoadOverwriteTemplate.php';
$wgAutoloadClasses['CollectionSaveOverwriteTemplate'] =
	__DIR__ . '/templates/CollectionSaveOverwriteTemplate.php';
$wgAutoloadClasses['CollectionRenderingTemplate'] =
	__DIR__ . '/templates/CollectionRenderingTemplate.php';
$wgAutoloadClasses['CollectionFinishedTemplate'] =
	__DIR__ . '/templates/CollectionFinishedTemplate.php';
$wgAutoloadClasses['CollectionFailedTemplate'] =
	__DIR__ . '/templates/CollectionFailedTemplate.php';
$wgAutoloadClasses['CollectionSuggestTemplate'] =
	__DIR__ . '/templates/CollectionSuggestTemplate.php';

$wgAutoloadClasses['CollectionRenderingAPI'] = __DIR__ . '/RenderingAPI.php';
$wgAutoloadClasses['MWServeRenderingAPI'] = __DIR__ . '/RenderingAPI.php';
$wgAutoloadClasses['CollectionAPIResult'] = __DIR__ . '/RenderingAPI.php';

$wgMessagesDirs['Collection'] = __DIR__ . '/i18n';
$wgExtensionMessagesFiles['CollectionAlias'] = __DIR__ . '/Collection.alias.php';

$wgSpecialPages['Book'] = 'SpecialCollection';
$wgSpecialPages['RenderBook'] = 'SpecialRenderBook';

$wgHooks['SkinTemplateBuildNavUrlsNav_urlsAfterPermalink'][] = 'CollectionHooks::buildNavUrls';
$wgHooks['SidebarBeforeOutput'][] = 'CollectionHooks::buildSidebar';
$wgHooks['SiteNoticeAfter'][] = 'CollectionHooks::siteNoticeAfter';
$wgHooks['OutputPageCheckLastModified'][] = 'CollectionHooks::checkLastModified';
$wgExtensionFunctions[] = 'CollectionHooks::onSetup';

$wgAvailableRights[] = 'collectionsaveasuserpage';
$wgAvailableRights[] = 'collectionsaveascommunitypage';

$collResourceTemplate = [
	'localBasePath' => __DIR__ . '/resources',
	'remoteExtPath' => 'Collection/resources'
];

$wgResourceModules += [
	'ext.collection' => $collResourceTemplate + [
		'scripts' => 'ext.collection/collection.js',
		'dependencies' => [
			'ext.collection.bookcreator',
			'jquery.ui.sortable',
			'mediawiki.language',
		],
	],
	'ext.collection.bookcreator.styles' => $collResourceTemplate + [
		'styles' => 'ext.collection.bookcreator.styles/bookcreator.css',
	],
	'ext.collection.bookcreator' => $collResourceTemplate + [
		'scripts' => 'ext.collection.bookcreator/bookcreator.js',
		'dependencies' => [ 'jquery.jStorage', 'ext.collection.bookcreator.styles' ],
	],
	'ext.collection.checkLoadFromLocalStorage' => $collResourceTemplate + [
		'scripts' => 'ext.collection.checkLoadFromLocalStorage/check_load_from_localstorage.js',
		'dependencies' => [
			'ext.collection',
			'ext.collection.bookcreator.styles',
			'jquery.jStorage',
		],
		'messages' => [
			'coll-load_local_book'
		]
	],
	'ext.collection.suggest' => $collResourceTemplate + [
		'scripts' => 'ext.collection.suggest/suggest.js',
		'dependencies' => 'ext.collection.bookcreator'
	],
	'ext.collection.offline' => $collResourceTemplate + [
		'styles' => 'ext.collection.offline/offline.less',
	],
];

# register global Ajax functions:

function wfAjaxGetCollection() {
	if ( isset( $_SESSION['wsCollection'] ) ) {
		$collection = $_SESSION['wsCollection'];
	} else {
		$collection = [];
	}
	$r = new AjaxResponse( FormatJson::encode( [ 'collection' => $collection ] ) );
	$r->setContentType( 'application/json' );
	return $r;
}

$wgAjaxExportList[] = 'wfAjaxGetCollection';

function wfAjaxPostCollection( $collection = '', $redirect = '' ) {
	if ( session_id() == '' ) {
		wfSetupSession();
	}
	$collection = FormatJson::decode( $collection, true );
	$collection['enabled'] = true;
	$_SESSION['wsCollection'] = $collection;
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
	ob_start();
	$template->execute();
	$html = ob_get_contents();
	ob_end_clean();

	$result = [];
	$result['html'] = $html;
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
