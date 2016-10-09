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

$wgExtensionCredits['specialpage'][] = array(
	'path' => __FILE__,
	'name' => 'Collection',
	'version' => '1.7.0',
	'author' => array( 'PediaPress GmbH', 'Siebrand Mazeland', 'Marcin Cieślak' ),
	'url' => 'https://www.mediawiki.org/wiki/Extension:Collection',
	'descriptionmsg' => 'coll-desc',
	'license-name' => 'GPL-2.0+',
);

# ==============================================================================

# Configuration:

/** URL of mw-serve render server */
$wgCollectionMWServeURL = 'http://tools.pediapress.com/mw-serve/';

/** Login credentials to this MediaWiki as 'USERNAME:PASSWORD' string */
$wgCollectionMWServeCredentials = null;

/** PEM-encoded SSL certificate for the mw-serve render server to pass to CURL */
$wgCollectionMWServeCert = null;

/** Array of namespaces that can be added to a collection */
$wgCollectionArticleNamespaces = array(
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
);

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
$wgCollectionFormats = array(
	'rl' => 'PDF',
);

/** Additional renderer options for collections. Format is as for
 * HTMLForm::loadInputFromParameters. Note that fieldnames may only contain
 * [a-zA-Z0-9_-], and values may not contain pipes or newlines. If the
 * 'options' field is an array, keys will be interpreted as messages.
 */
$wgCollectionRendererSettings = array(
	'papersize' => array(
		'type' => 'select',
		'label-message' => 'coll-setting-papersize',
		'default' => 'a4',
		'options' => array(
			'coll-setting-papersize-a4' => 'a4',
			'coll-setting-papersize-letter' => 'letter',
		),
	),
	'toc' => array(
		'type' => 'select',
		'label-message' => 'coll-setting-toc',
		'default' => 'auto',
		'options' => array(
			'coll-setting-toc-auto' => 'auto',
			'coll-setting-toc-yes' => 'yes',
			'coll-setting-toc-no' => 'no',
		)
	),
	'columns' => array(
		'type' => 'select',
		'label-message' => 'coll-setting-columns',
		'default' => '2',
		'options' => array(
			'coll-setting-columns-1' => '1',
			'coll-setting-columns-2' => '2',
		),
	),
);

/** Some commands require an external server
 */
$wgCollectionCommandToServeURL = array();

/** For formats which rendering depends on an external server
*/
$wgCollectionFormatToServeURL = array();

$wgCollectionContentTypeToFilename = array(
	'application/pdf' => 'collection.pdf',
	'application/vnd.oasis.opendocument.text' => 'collection.odt',
	'text/plain' => 'collection.txt',
);

$wgCollectionPortletFormats = array( 'rl' );

$wgCollectionPortletForLoggedInUsersOnly = false;

$wgCollectionMaxSuggestions = 10;

$wgCollectionSuggestCheapWeightThreshhold = 50;

$wgCollectionSuggestThreshhold = 100;

$wgCollectionPODPartners = array(
	'pediapress' => array(
		'name' => 'PediaPress',
		'url' => 'http://pediapress.com/',
		'posturl' => 'http://pediapress.com/api/collections/',
		'infopagetitle' => 'coll-order_info_article',
	),
);

# Optional notes that are displayed on the download screen for the rendered
# document. Each entry is a message key.
$wgCollectionShowRenderNotes = array(
	'coll-rendering_finished_note_not_satisfied',
);

# ==============================================================================

# register Special:Book:
$wgAutoloadClasses['SpecialCollection'] = __DIR__ . '/Collection.body.php';
$wgAutoloadClasses['CollectionSession'] = __DIR__ . '/Collection.session.php';
$wgAutoloadClasses['CollectionHooks'] = __DIR__ . '/Collection.hooks.php';
$wgAutoloadClasses['CollectionAjaxFunctions'] = __DIR__ . '/CollectionAjaxFunctions.php';
$wgAutoloadClasses['CollectionSuggest'] = __DIR__ . '/Collection.suggest.php';
$wgAutoloadClasses['CollectionProposals'] = __DIR__ . '/Collection.suggest.php';

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
$wgAutoloadClasses['CollectionFailedTemplate'] = __DIR__ . '/templates/CollectionFailedTemplate.php';
$wgAutoloadClasses['CollectionSuggestTemplate'] = __DIR__ . '/templates/CollectionSuggestTemplate.php';

$wgAutoloadClasses['CollectionRenderingAPI'] = __DIR__ . '/RenderingAPI.php';
$wgAutoloadClasses['MWServeRenderingAPI'] = __DIR__ . '/RenderingAPI.php';
$wgAutoloadClasses['NewRenderingAPI'] = __DIR__ . '/RenderingAPI.php';
$wgAutoloadClasses['CollectionAPIResult'] = __DIR__ . '/RenderingAPI.php';

$wgMessagesDirs['Collection'] = __DIR__ . '/i18n';
$wgExtensionMessagesFiles['Collection'] = __DIR__ . '/Collection.i18n.php';
$wgExtensionMessagesFiles['CollectionAlias'] = __DIR__ . '/Collection.alias.php';

$wgSpecialPages['Book'] = 'SpecialCollection';

$wgHooks['SkinTemplateBuildNavUrlsNav_urlsAfterPermalink'][] = 'CollectionHooks::buildNavUrls';
$wgHooks['SidebarBeforeOutput'][] = 'CollectionHooks::buildSidebar';
$wgHooks['SiteNoticeAfter'][] = 'CollectionHooks::siteNoticeAfter';
$wgHooks['OutputPageCheckLastModified'][] = 'CollectionHooks::checkLastModified';
$wgExtensionFunctions[] = 'CollectionHooks::onSetup';

$wgAvailableRights[] = 'collectionsaveasuserpage';
$wgAvailableRights[] = 'collectionsaveascommunitypage';

$collResourceTemplate = array(
	'localBasePath' => __DIR__ . '/modules',
	'remoteExtPath' => 'Collection/modules'
);

$wgResourceModules += array(
	'ext.collection' => $collResourceTemplate + array(
		'scripts' => 'collection.js',
		'dependencies' => array(
			'ext.collection.bookcreator',
			'jquery.ui.sortable',
			'mediawiki.language',
		),
	),
	'ext.collection.bookcreator' => $collResourceTemplate + array(
		'scripts' => 'bookcreator.js',
		'styles' => 'bookcreator.css',
		'dependencies' => 'jquery.jStorage'
	),
	'ext.collection.checkLoadFromLocalStorage' => $collResourceTemplate + array(
		'scripts' => 'check_load_from_localstorage.js',
		'styles' => 'bookcreator.css',
		'dependencies' => array(
			'ext.collection',
			'jquery.jStorage',
		),
		'messages' => array(
			'coll-load_local_book'
		)
	),
	'ext.collection.suggest' => $collResourceTemplate + array(
		'scripts' => 'suggest.js',
		'dependencies' => 'ext.collection.bookcreator'
	),
);

# register global Ajax functions:

$wgAjaxExportList[] = 'CollectionAjaxFunctions::onAjaxGetCollection';

$wgAjaxExportList[] = 'CollectionAjaxFunctions::onAjaxPostCollection';

$wgAjaxExportList[] = 'CollectionAjaxFunctions::onAjaxGetMWServeStatus';

$wgAjaxExportList[] = 'CollectionAjaxFunctions::onAjaxCollectionAddArticle';

$wgAjaxExportList[] = 'CollectionAjaxFunctions::onAjaxCollectionRemoveArticle';

$wgAjaxExportList[] = 'CollectionAjaxFunctions::onAjaxCollectionAddCategory';

$wgAjaxExportList[] = 'CollectionAjaxFunctions::onAjaxCollectionGetBookCreatorBoxContent';

$wgAjaxExportList[] = 'CollectionAjaxFunctions::onAjaxCollectionGetItemList';

$wgAjaxExportList[] = 'CollectionAjaxFunctions::onAjaxCollectionRemoveItem';

$wgAjaxExportList[] = 'CollectionAjaxFunctions::onAjaxCollectionAddChapter';

$wgAjaxExportList[] = 'CollectionAjaxFunctions::onAjaxCollectionRenameChapter';

$wgAjaxExportList[] = 'CollectionAjaxFunctions::onAjaxCollectionSetTitles';

$wgAjaxExportList[] = 'CollectionAjaxFunctions::onAjaxCollectionSetSorting';

$wgAjaxExportList[] = 'CollectionAjaxFunctions::onAjaxCollectionClear';

$wgAjaxExportList[] = 'CollectionAjaxFunctions::onAjaxCollectionGetPopupData';

$wgAjaxExportList[] = 'CollectionAjaxFunctions::onAjaxCollectionSuggestBanArticle';

$wgAjaxExportList[] = 'CollectionAjaxFunctions::onAjaxCollectionSuggestAddArticle';

$wgAjaxExportList[] = 'CollectionAjaxFunctions::onAjaxCollectionSuggestRemoveArticle';

$wgAjaxExportList[] = 'CollectionAjaxFunctions::onAjaxCollectionSuggestUndoArticle';

$wgAjaxExportList[] = 'CollectionAjaxFunctions::onAjaxCollectionSortItems';
