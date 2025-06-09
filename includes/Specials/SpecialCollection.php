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

namespace MediaWiki\Extension\Collection\Specials;

use MediaWiki\Api\ApiMain;
use MediaWiki\Config\Config;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\Collection\MessageBoxHelper;
use MediaWiki\Extension\Collection\Rendering\CollectionAPIResult;
use MediaWiki\Extension\Collection\Rendering\CollectionRenderingAPI;
use MediaWiki\Extension\Collection\Session as CollectionSession;
use MediaWiki\Extension\Collection\Suggest;
use MediaWiki\Extension\Collection\Templates\CollectionFailedTemplate;
use MediaWiki\Extension\Collection\Templates\CollectionFinishedTemplate;
use MediaWiki\Extension\Collection\Templates\CollectionLoadOverwriteTemplate;
use MediaWiki\Extension\Collection\Templates\CollectionPageTemplate;
use MediaWiki\Extension\Collection\Templates\CollectionRenderingTemplate;
use MediaWiki\Extension\Collection\Templates\CollectionSaveOverwriteTemplate;
use MediaWiki\Html\Html;
use MediaWiki\MediaWikiServices;
use MediaWiki\Request\DerivativeRequest;
use MediaWiki\Skin\SkinComponentUtils;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\Title;
use OOUI\ButtonGroupWidget;
use OOUI\ButtonInputWidget;
use OOUI\ButtonWidget;
use OOUI\FormLayout;
use UnexpectedValueException;

class SpecialCollection extends SpecialPage {

	/** @var resource */
	private $tempfile;

	/** @var false|array[] */
	private $mPODPartners;

	/**
	 * @param false|array[] $PODPartners
	 */
	public function __construct( $PODPartners = false ) {
		parent::__construct( "Book" );
		if ( $PODPartners ) {
			$this->mPODPartners = $PODPartners;
		} else {
			$this->mPODPartners = $this->getConfig()->get( 'CollectionPODPartners' );
		}
	}

	/** @inheritDoc */
	public function doesWrites() {
		return true;
	}

	/**
	 * @return string
	 */
	public function getDescription() {
		return $this->msg( 'coll-collection' );
	}

	/**
	 * @param null|string $par
	 */
	public function execute( $par ) {
		$out = $this->getOutput();
		$request = $this->getRequest();

		// support previous URLs (e.g. used in templates) which used the "$par" part
		// (i.e. subpages of the Special page)
		if ( $par ) {
			// don't redirect POST reqs
			if ( $request->wasPosted() ) {
				// TODO
			}
			$out->redirect( wfAppendQuery(
				SkinComponentUtils::makeSpecialUrl( 'Book' ),
				$request->appendQueryArray( [ 'bookcmd' => rtrim( $par, '/' ) ] )
			) );
			return;
		}

		switch ( $request->getVal( 'bookcmd', '' ) ) {
			case 'book_creator':
				$this->renderBookCreatorPage( $request->getVal( 'referer', '' ), $par );
				return;

			case 'start_book_creator':
				$title = Title::newFromText( $request->getVal( 'referer', '' ) );
				if ( $title === null ) {
					$title = Title::newMainPage();
				}
				if ( $request->getVal( 'confirm' ) ) {
					CollectionSession::enable();
				}
				$out->redirect( $title->getFullURL() );
				return;

			case 'stop_book_creator':
				$title = Title::newFromText( $request->getVal( 'referer', '' ) );
				if ( $title === null || $title->equals( $this->getPageTitle( $par ) ) ) {
					$title = Title::newMainPage();
				}
				if ( $request->getVal( 'confirm' ) ) {
					CollectionSession::disable();
				} elseif ( !$request->getVal( 'continue' ) ) {
					$this->renderStopBookCreatorPage( $title );
					return;
				}
				$out->redirect( $title->getFullURL() );
				return;

			case 'add_article':
				if ( CollectionSession::countArticles() >= $this->getConfig()->get( 'CollectionMaxArticles' ) ) {
					self::limitExceeded();
					return;
				}
				$oldid = $request->getInt( 'oldid', 0 );
				$title = Title::newFromText( $request->getVal( 'arttitle', '' ) );
				if ( !$title ) {
					return;
				}
				if ( self::addArticle( $title, $oldid ) ) {
					if ( $oldid == 0 ) {
						$redirectURL = $title->getFullURL();
					} else {
						$redirectURL = $title->getFullURL( 'oldid=' . $oldid );
					}
					$out->redirect( $redirectURL );
				} else {
					$out->showErrorPage(
						'coll-couldnotaddarticle_title',
						'coll-couldnotaddarticle_msg'
					);
				}
				return;

			case 'remove_article':
				$oldid = $request->getInt( 'oldid', 0 );
				$title = Title::newFromText( $request->getVal( 'arttitle', '' ) );
				if ( !$title ) {
					return;
				}
				if ( self::removeArticle( $title, $oldid ) ) {
					if ( $oldid == 0 ) {
						$redirectURL = $title->getFullURL();
					} else {
						$redirectURL = $title->getFullURL( 'oldid=' . $oldid );
					}
					$out->redirect( $redirectURL );
				} else {
					$out->showErrorPage(
						'coll-couldnotremovearticle_title',
						'coll-couldnotremovearticle_msg'
					);
				}
				return;

			case 'clear_collection':
				CollectionSession::clearCollection();
				$redirect = $request->getVal( 'return_to', '' );
				$redirectURL = SkinComponentUtils::makeSpecialUrl( 'Book' );
				if ( $redirect !== '' ) {
					$title = Title::newFromText( $redirect );
					if ( $title ) {
						$redirectURL = $title->getFullURL();
					}
				}
				$out->redirect( $redirectURL );
				return;

			case 'set_titles':
				self::setTitles(
					$request->getText( 'collectionTitle', '' ),
					$request->getText( 'collectionSubtitle', '' )
				);
				$out->redirect( SkinComponentUtils::makeSpecialUrl( 'Book' ) );
				return;

			case 'sort_items':
				self::sortItems();
				$out->redirect( SkinComponentUtils::makeSpecialUrl( 'Book' ) );
				return;

			case 'add_category':
				$title = Title::makeTitleSafe( NS_CATEGORY, $request->getVal( 'cattitle', '' ) );
				if ( !$title ) {
					return;
				} elseif ( self::addCategory( $title, $this->getConfig() ) ) {
					self::limitExceeded();
					return;
				} else {
					$out->redirect( $request->getVal( 'return_to', $title->getFullURL() ) );
				}
				return;

			case 'remove_item':
				self::removeItem( $request->getInt( 'index', 0 ) );
				$out->redirect( SkinComponentUtils::makeSpecialUrl( 'Book' ) );
				return;

			case 'move_item':
				self::moveItem( $request->getInt( 'index', 0 ), $request->getInt( 'delta', 0 ) );
				$out->redirect( SkinComponentUtils::makeSpecialUrl( 'Book' ) );
				return;

			case 'load_collection':
				$title = Title::newFromText( $request->getVal( 'colltitle', '' ) );
				if ( !$title ) {
					return;
				}
				if ( $request->getVal( 'cancel' ) ) {
					$out->redirect( $title->getFullURL() );
					return;
				}
				if ( !CollectionSession::countArticles()
					|| $request->getVal( 'overwrite' )
					|| $request->getVal( 'append' )
				) {
					$collection = $this->loadCollection( $title, $request->getBool( 'append' ) );
					if ( $collection ) {
						CollectionSession::startSession();
						CollectionSession::setCollection( $collection );
						CollectionSession::enable();
						$out->redirect( SkinComponentUtils::makeSpecialUrl( 'Book' ) );
					}
					return;
				}
				$this->renderLoadOverwritePage( $title );
				return;

			case 'order_collection':
				$title = Title::newFromText( $request->getVal( 'colltitle', '' ) );
				if ( !$title ) {
					return;
				}
				$collection = $this->loadCollection( $title );
				if ( $collection ) {
					$partner = $request->getVal( 'partner', key( $this->mPODPartners ) );
					$this->postZip( $collection, $partner );
				}
				return;

			case 'save_collection':
				$this->processSaveCollectionCommand();
				return;

			case 'render':
				$this->renderCollection(
					CollectionSession::getCollection(),
					SpecialPage::getTitleFor( 'Book' ),
					$request->getVal( 'writer', '' )
				);
				return;

			case 'forcerender':
				$this->forceRenderCollection();
				return;

			case 'rendering':
				$this->renderRenderingPage();
				return;

			case 'download':
				$this->download();
				return;

			case 'post_zip':
				$partner = $request->getVal( 'partner', 'pediapress' );
				$this->postZip( CollectionSession::getCollection(), $partner );
				return;

			case 'suggest':
				$this->processSuggestCommand();
				return;

			case '':
				$this->renderSpecialPage();
				return;

			default:
				$out->showErrorPage( 'coll-unknown_subpage_title', 'coll-unknown_subpage_text' );
		}
	}

	/**
	 * Processes the suggest command
	 */
	private function processSuggestCommand() {
		$request = $this->getRequest();

		$add = $request->getVal( 'add' );
		$ban = $request->getVal( 'ban' );
		$remove = $request->getVal( 'remove' );
		$addselected = $request->getVal( 'addselected' );

		if ( $request->getVal( 'resetbans' ) ) {
			Suggest::run( 'resetbans' );
		} elseif ( $add !== null ) {
			Suggest::run( 'add', $add );
		} elseif ( $ban !== null ) {
			Suggest::run( 'ban', $ban );
		} elseif ( $remove !== null ) {
			Suggest::run( 'remove', $remove );
		} elseif ( $addselected !== null ) {
			$articleList = $request->getArray( 'articleList' );
			if ( $articleList !== null ) {
				Suggest::run( 'addAll', $articleList );
			} else {
				Suggest::run();
			}
		} else {
			Suggest::run();
		}
	}

	/**
	 * Processes the save book command
	 */
	private function processSaveCollectionCommand() {
		$out = $this->getOutput();
		$request = $this->getRequest();
		$user = $this->getUser();

		if ( $request->getVal( 'abort' ) ) {
			$out->redirect( SkinComponentUtils::makeSpecialUrl( 'Book' ) );
			return;
		}
		if ( !$user->matchEditToken( $request->getVal( 'token' ) ) ) {
			return;
		}

		$colltype = $request->getVal( 'colltype' );
		$prefixes = $this->getBookPagePrefixes();
		$title = null;
		if ( $colltype == 'personal' ) {
			$collname = $request->getVal( 'pcollname', '' );
			if ( !$user->isAllowed( 'collectionsaveasuserpage' ) || $collname === '' ) {
				return;
			}
			$title = Title::newFromText( $prefixes['user-prefix'] . $collname );
		} elseif ( $colltype == 'community' ) {
			$collname = $request->getVal( 'ccollname', '' );
			if ( !$user->isAllowed( 'collectionsaveascommunitypage' ) || $collname === '' ) {
				return;
			}
			$title = Title::newFromText( $prefixes['community-prefix'] . $collname );
		}
		if ( !$title || !$colltype ) {
			return;
		}

		if ( $this->saveCollection( $title, $request->getBool( 'overwrite' ) ) ) {
			$out->redirect( $title->getFullURL() );
		} else {
			$this->renderSaveOverwritePage(
				$colltype,
				$title,
				$request->getVal( 'pcollname' ) ?? '',
				$request->getVal( 'ccollname' ) ?? ''
			);
		}
	}

	/**
	 * @param string $referer
	 * @param string $par
	 */
	private function renderBookCreatorPage( $referer, $par ) {
		$out = $this->getOutput();
		$out->enableOOUI();

		$this->setHeaders();
		$out->setPageTitleMsg( $this->msg( 'coll-book_creator' ) );

		MessageBoxHelper::addModuleStyles( $out );
		$out->addHTML( MessageBoxHelper::renderWarningBoxes() );
		$out->addWikiMsg( 'coll-book_creator_intro' );

		$out->addModules( 'ext.collection.checkLoadFromLocalStorage' );

		$title = Title::newFromText( $referer );
		if ( $title === null || $title->equals( $this->getPageTitle( $par ) ) ) {
			$title = Title::newMainPage();
		}

		$form = new FormLayout( [
			'method' => 'POST',
			'action' => SkinComponentUtils::makeSpecialUrl(
				'Book',
				[
					'bookcmd' => 'start_book_creator',
					'referer' => $referer,
				]
			),
		] );
		$form->appendContent( new ButtonGroupWidget( [
			'items' => [
				new ButtonInputWidget( [
					'type' => 'submit',
					'name' => 'confirm',
					'value' => 'yes',
					'flags' => [ 'primary', 'progressive' ],
					'label' => $this->msg( 'coll-start_book_creator' )->text(),
				] ),
				new ButtonWidget( [
					'href' => $title->getLinkURL(),
					'title' => $title->getPrefixedText(),
					'label' => $this->msg( 'coll-cancel' )->text(),
					'noFollow' => true,
				] ),
			],
		] ) );

		$out->addHTML( $form );

		$title_string = $this->msg( 'coll-book_creator_text_article' )->inContentLanguage()->text();
		$t = Title::newFromText( $title_string );
		if ( $t !== null ) {
			if ( $t->exists() ) {
				$out->addWikiTextAsInterface( '{{:' . $title_string . '}}' );
				return;
			}
		}
		$out->addWikiMsg( 'coll-book_creator_help' );
	}

	/**
	 * @param string $referer
	 */
	private function renderStopBookCreatorPage( $referer ) {
		$out = $this->getOutput();
		$out->enableOOUI();

		$this->setHeaders();
		$out->setPageTitleMsg( $this->msg( 'coll-book_creator_disable' ) );
		$out->addWikiMsg( 'coll-book_creator_disable_text' );

		$form = new FormLayout( [
			'method' => 'POST',
			'action' => SkinComponentUtils::makeSpecialUrl(
				'Book',
				[
					'bookcmd' => 'stop_book_creator',
					'referer' => $referer,
				]
			),
		] );
		$form->appendContent( new ButtonGroupWidget( [
			'items' => [
				new ButtonInputWidget( [
					'type' => 'submit',
					'name' => 'continue',
					'value' => 'yes',
					'label' => $this->msg( 'coll-book_creator_continue' )->text(),
				] ),
				new ButtonInputWidget( [
					'type' => 'submit',
					'name' => 'confirm',
					'value' => 'yes',
					'label' => $this->msg( 'coll-book_creator_disable' )->text(),
					'flags' => [ 'primary', 'destructive' ],
				] ),
			],
		] ) );

		$out->addHTML( $form );
	}

	/**
	 * @return array
	 */
	private function getBookPagePrefixes() {
		$result = [];
		$user = $this->getUser();
		$communityCollectionNamespace = $this->getConfig()->get( 'CommunityCollectionNamespace' );

		$t = $this->msg( 'coll-user_book_prefix', $user->getName() )->inContentLanguage();
		if ( $t->isDisabled() ) {
			$userPageTitle = $user->getUserPage()->getPrefixedText();
			$result['user-prefix'] = $userPageTitle . '/'
				. $this->msg( 'coll-collections' )->inContentLanguage()->text() . '/';
		} else {
			$result['user-prefix'] = $t->text();
		}

		$comBookPrefix = $this->msg( 'coll-community_book_prefix' )->inContentLanguage();
		if ( $comBookPrefix->isDisabled() ) {
			$title = Title::makeTitle(
				$communityCollectionNamespace,
				$this->msg( 'coll-collections' )->inContentLanguage()->text()
			);
			$result['community-prefix'] = $title->getPrefixedText() . '/';
		} else {
			$result['community-prefix'] = $comBookPrefix->text();
		}
		return $result;
	}

	private function renderSpecialPage() {
		if ( !CollectionSession::hasSession() ) {
			CollectionSession::startSession();
		}

		$out = $this->getOutput();
		$config = $this->getConfig();
		MessageBoxHelper::addModuleStyles( $out );

		$this->setHeaders();
		$this->addHelpLink( 'Special:MyLanguage/Extension:Collection/Help' );
		$out->setPageTitleMsg( $this->msg( 'coll-manage_your_book' ) );
		$out->addModules( 'ext.collection' );
		$out->addModuleStyles( [ 'mediawiki.hlist', 'ext.collection.bookcreator.styles' ] );
		$out->addJsConfigVars( [
			'wgCollectionDisableDownloadSection' => $config->get( 'CollectionDisableDownloadSection' )
		] );

		$template = new CollectionPageTemplate();
		$template->set( 'context', $this->getContext() );
		$template->set( 'collection', CollectionSession::getCollection() );
		$template->set( 'podpartners', $this->mPODPartners );
		$template->set( 'settings', $config->get( 'CollectionRendererSettings' ) );
		$template->set( 'formats', $config->get( 'CollectionFormats' ) );
		$prefixes = $this->getBookPagePrefixes();
		$template->set( 'user-book-prefix', $prefixes['user-prefix'] );
		$template->set( 'community-book-prefix', $prefixes['community-prefix'] );
		$out->addTemplate( $template );
	}

	/**
	 * @param string $title
	 * @param string $subtitle
	 */
	public static function setTitles( $title, $subtitle ) {
		$collection = CollectionSession::getCollection();
		$collection['title'] = $title;
		$collection['subtitle'] = $subtitle;
		CollectionSession::setCollection( $collection );
	}

	/**
	 * @param array $settings
	 */
	public static function setSettings( array $settings ) {
		$collection = CollectionSession::getCollection();
		if ( !isset( $collection['settings'] ) ) {
			$collection['settings'] = [];
		}
		$collection['settings'] = $settings + $collection['settings'];
		CollectionSession::setCollection( $collection );
	}

	/**
	 * @param array &$items
	 */
	private static function sortByTitle( array &$items ) {
		usort( $items, static function ( $a, $b ) {
			return strcasecmp( $a['title'], $b['title'] );
		} );
	}

	public static function sortItems() {
		$collection = CollectionSession::getCollection();
		if ( !isset( $collection['items'] ) || !is_array( $collection['items'] ) ) {
			$collection['items'] = [];
			CollectionSession::setCollection( $collection );
			return;
		}

		$articles = [];
		$new_items = [];
		foreach ( $collection['items'] as $item ) {
			'@phan-var array $item';
			if ( $item['type'] == 'chapter' ) {
				self::sortByTitle( $articles );
				$new_items = array_merge( $new_items, $articles, [ $item ] );
				$articles = [];
			} elseif ( $item['type'] == 'article' ) {
				$articles[] = $item;
			}
		}
		self::sortByTitle( $articles );
		$collection['items'] = array_merge( $new_items, $articles );
		CollectionSession::setCollection( $collection );
	}

	/**
	 * @param string $name
	 */
	public static function addChapter( $name ) {
		$collection = CollectionSession::getCollection();
		if ( !isset( $collection['items'] ) || !is_array( $collection['items'] ) ) {
			$collection['items'] = [];
		}
		array_push( $collection['items'], [
			'type' => 'chapter',
			'title' => $name,
		] );
		CollectionSession::setCollection( $collection );
	}

	/**
	 * @param int $index
	 * @param string $name
	 */
	public static function renameChapter( $index, $name ) {
		if ( !is_int( $index ) ) {
			return;
		}
		$collection = CollectionSession::getCollection();

		// T293261: Make sure the index exist in the array
		if ( !array_key_exists( $index, $collection['items'] ) ||
			$collection['items'][$index]['type'] !== 'chapter'
		) {
			return;
		}

		$collection['items'][$index]['title'] = $name;
		CollectionSession::setCollection( $collection );
	}

	/**
	 * @param int $namespace
	 * @param string $name
	 * @param int $oldid
	 * @return bool
	 */
	public static function addArticleFromName( $namespace, $name, $oldid = 0 ) {
		$title = Title::makeTitleSafe( $namespace, $name );
		if ( !$title ) {
			return false;
		}
		return self::addArticle( $title, $oldid );
	}

	/**
	 * @param Title $title
	 * @param int $oldid
	 * @return bool
	 */
	private static function addArticle( $title, $oldid = 0 ) {
		$latest = $title->getLatestRevID();

		$currentVersion = 0;
		if ( $oldid == 0 ) {
			$currentVersion = 1;
			$oldid = $latest;
		}

		$prefixedText = $title->getPrefixedText();

		$index = CollectionSession::findArticle( $prefixedText, $oldid );
		if ( $index != -1 ) {
			return false;
		}

		if ( !CollectionSession::hasSession() ) {
			CollectionSession::startSession();
		}
		$collection = CollectionSession::getCollection();
		$revision = MediaWikiServices::getInstance()
			->getRevisionLookup()
			->getRevisionByTitle( $title, $oldid );
		if ( !$revision ) {
			return false;
		}

		$item = [
			'type' => 'article',
			'content_type' => 'text/x-wiki',
			'title' => $prefixedText,
			'revision' => strval( $oldid ),
			'latest' => strval( $latest ),
			'timestamp' => wfTimestamp( TS_UNIX, $revision->getTimestamp() ),
			'url' => $title->getCanonicalURL(),
			'currentVersion' => $currentVersion,
		];

		$collection['items'][] = $item;
		CollectionSession::setCollection( $collection );
		return true;
	}

	/**
	 * @param int $namespace
	 * @param string $name
	 * @param int $oldid
	 * @return bool
	 */
	public static function removeArticleFromName( $namespace, $name, $oldid = 0 ) {
		$title = Title::makeTitleSafe( $namespace, $name );
		return self::removeArticle( $title, $oldid );
	}

	/**
	 * @param Title $title
	 * @param int $oldid
	 * @return bool
	 */
	private static function removeArticle( $title, $oldid = 0 ) {
		if ( !CollectionSession::hasSession() || !$title ) {
			return false;
		}
		$collection = CollectionSession::getCollection();
		$index = CollectionSession::findArticle( $title->getPrefixedText(), $oldid );
		if ( $index != -1 ) {
			array_splice( $collection['items'], $index, 1 );
		}
		CollectionSession::setCollection( $collection );
		return true;
	}

	/**
	 * @param string $name
	 * @param Config $config
	 * @return bool
	 */
	public static function addCategoryFromName( $name, Config $config ) {
		$title = Title::makeTitleSafe( NS_CATEGORY, $name );
		return self::addCategory( $title, $config );
	}

	/**
	 * @param Title $title
	 * @param Config $config
	 * @return bool
	 */
	private static function addCategory( $title, Config $config ) {
		$limit = $config->get( 'CollectionMaxArticles' ) - CollectionSession::countArticles();
		if ( $limit <= 0 || !$title ) {
			self::limitExceeded();
			return false;
		}
		$dbr = MediaWikiServices::getInstance()->getConnectionProvider()->getReplicaDatabase();
		$res = $dbr->newSelectQueryBuilder()
			->select( [ 'page_namespace', 'page_title' ] )
			->from( 'page' )
			->join( 'categorylinks', null, 'cl_from=page_id' )
			->where( [ 'cl_to' => $title->getDBkey() ] )
			->orderBy( [ 'cl_type', 'cl_sortkey' ] )
			->limit( $limit + 1 )
			->caller( __METHOD__ )
			->fetchResultSet();

		$count = 0;
		$limitExceeded = false;
		$collectionArticleNamespaces = $config->get( 'CollectionArticleNamespaces' );
		foreach ( $res as $row ) {
			if ( ++$count > $limit ) {
				$limitExceeded = true;
				break;
			}
			if ( in_array( $row->page_namespace, $collectionArticleNamespaces ) ) {
				$articleTitle = Title::makeTitle( $row->page_namespace, $row->page_title );
				if ( CollectionSession::findArticle( $articleTitle->getPrefixedText() ) == -1 ) {
					self::addArticle( $articleTitle );
				}
			}
		}
		return $limitExceeded;
	}

	private static function limitExceeded() {
		$out = RequestContext::getMain()->getOutput();
		$out->showErrorPage( 'coll-limit_exceeded_title', 'coll-limit_exceeded_text' );
	}

	/**
	 * @param int $index
	 * @return bool
	 */
	public static function removeItem( $index ) {
		if ( !is_int( $index ) ) {
			return false;
		}
		if ( !CollectionSession::hasSession() ) {
			return false;
		}
		$collection = CollectionSession::getCollection();
		array_splice( $collection['items'], $index, 1 );
		CollectionSession::setCollection( $collection );
		return true;
	}

	/**
	 * @param int $index
	 * @param int $delta
	 * @return bool
	 */
	private static function moveItem( $index, $delta ) {
		if ( !CollectionSession::hasSession() ) {
			return false;
		}
		$collection = CollectionSession::getCollection();
		$collection = self::moveItemInCollection( $collection, $index, $delta );
		if ( $collection === false ) {
			return false;
		} else {
			CollectionSession::setCollection( $collection );
			return true;
		}
	}

	/**
	 * @param array $collection
	 * @param int $index
	 * @param int $delta
	 * @return array|false
	 */
	public static function moveItemInCollection( array $collection, $index, $delta ) {
		$swapIndex = $index + $delta;
		if ( !$collection || !isset( $collection['items'] ) ) {
			return false;
		}
		$items = $collection['items'];
		if ( isset( $items[$swapIndex] ) && isset( $items[$index] ) ) {
			$saved = $items[$swapIndex];
			$collection['items'][$swapIndex] = $items[$index];
			$collection['items'][$index] = $saved;
			return $collection;
		} else {
			return false;
		}
	}

	/**
	 * @param array<int,int> $items Mapping new to old positions, missing positions will be deleted
	 */
	public static function setSorting( array $items ) {
		if ( !CollectionSession::hasSession() ) {
			return;
		}
		$collection = CollectionSession::getCollection();
		$old_items = $collection['items'];
		$new_items = [];
		foreach ( $items as $new_index => $old_index ) {
			// Fail-safe when the "setsorting" API is hit multiple times, but an old item is already
			// deleted
			if ( isset( $old_items[$old_index] ) ) {
				$new_items[$new_index] = $old_items[$old_index];
			}
		}
		$collection['items'] = $new_items;
		CollectionSession::setCollection( $collection );
	}

	/**
	 * @param array &$collection
	 * @param string $line
	 * @param bool $append
	 * @return array|null
	 */
	private function parseCollectionLine( &$collection, $line, $append ) {
		$line = trim( $line );
		if ( !$append && preg_match( '/^===\s*(.*?)\s*===$/', $line, $match ) ) {
			$collection['subtitle'] = $match[ 1 ];
		} elseif ( !$append && preg_match( '/^==\s*(.*?)\s*==$/', $line, $match ) ) {
			$collection['title'] = $match[ 1 ];
		} elseif (
			!$append &&
			preg_match( '/^\s*\|\s*setting-([a-zA-Z0-9_-]+)\s*=\s*([^|]*)\s*$/', $line, $match )
		) {
			$collection['settings'][$match[ 1 ]] = $match[ 2 ];
		} elseif ( substr( $line, 0, 1 ) == ';' ) {
			// chapter
			return [
				'type' => 'chapter',
				'title' => trim( substr( $line, 1 ) ),
			];
		} elseif ( substr( $line, 0, 1 ) == ':' ) {
			// article
			$articleTitle = trim( substr( $line, 1 ) );
			if ( preg_match( '/^\[\[:?(.*?)(\|(.*?))?\]\]$/', $articleTitle, $match ) ) {
				$articleTitle = $match[1];
				if ( isset( $match[3] ) ) {
					$displayTitle = $match[3];
				} else {
					$displayTitle = null;
				}
				$oldid = 0;
				$currentVersion = 1;
			} elseif (
				preg_match( '/^\[\{\{fullurl:(.*?)\|oldid=(.*?)\}\}\s+(.*?)\]$/', $articleTitle, $match )
			) {
				$articleTitle = $match[1];
				if ( isset( $match[3] ) ) {
					$displayTitle = $match[3];
				} else {
					$displayTitle = null;
				}
				$oldid = (int)$match[2];
				$currentVersion = 0;
			} else {
				return null;
			}

			$articleTitle = Title::newFromText( $articleTitle );
			if ( !$articleTitle ) {
				return null;
			}

			if ( !$articleTitle->exists() ) {
				return null;
			}

			$revision = MediaWikiServices::getInstance()
				->getRevisionLookup()
				->getRevisionByTitle( $articleTitle, $oldid );
			if ( !$revision ) {
				return null;
			}
			$latest = $articleTitle->getLatestRevID();

			if ( !$oldid ) {
				$oldid = $latest;
			}

			$d = [
				'type' => 'article',
				'content_type' => 'text/x-wiki',
				'title' => $articleTitle->getPrefixedText(),
				'latest' => $latest,
				'revision' => $oldid,
				'timestamp' => wfTimestamp( TS_UNIX, $revision->getTimestamp() ),
				'url' => $articleTitle->getCanonicalURL(),
				'currentVersion' => $currentVersion,
			];
			if ( $displayTitle ) {
				$d['displaytitle'] = $displayTitle;
			}
			return $d;
		}
		return null;
	}

	/**
	 * @param Title $title
	 * @param bool $append
	 * @return array|false
	 */
	private function loadCollection( Title $title, $append = false ) {
		$out = $this->getOutput();

		if ( !$title->exists() ) {
			$out->showErrorPage( 'coll-notfound_title', 'coll-notfound_text' );
			return false;
		}

		if ( !$append || !CollectionSession::hasSession() ) {
			$collection = [
				'title' => '',
				'subtitle' => '',
				'settings' => [],
			];
			$items = [];
		} else {
			$collection = CollectionSession::getCollection();
			$items = $collection['items'];
		}

		$page = MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle( $title );
		$lines = preg_split(
			'/[\r\n]+/',
			$page->getContent()->getNativeData()
		);

		foreach ( $lines as $line ) {
			$item = $this->parseCollectionLine( $collection, $line, $append );
			if ( $item !== null ) {
				$items[] = $item;
			}
		}
		$collection['items'] = $items;
		return $collection;
	}

	/**
	 * @param Title $title
	 * @param bool $forceOverwrite
	 * @return bool
	 */
	private function saveCollection( Title $title, $forceOverwrite = false ) {
		if ( $title->exists() && !$forceOverwrite ) {
			return false;
		}

		$collection = CollectionSession::getCollection();
		$articleText = "{{" . $this->msg( 'coll-savedbook_template' )->inContentLanguage()->text();
		if ( !empty( $collection['settings'] ) ) {
			$articleText .= "\n";
			foreach ( $collection['settings'] as $key => $value ) {
				$articleText .= " | setting-$key = $value\n";
			}
		}
		$articleText .= "}}\n\n";
		if ( $collection['title'] ) {
			$articleText .= '== ' . $collection['title'] . " ==\n";
		}
		if ( $collection['subtitle'] ) {
			$articleText .= '=== ' . $collection['subtitle'] . " ===\n";
		}
		if ( !empty( $collection['items'] ) ) {
			foreach ( $collection['items'] as $item ) {
				if ( $item['type'] == 'chapter' ) {
					$articleText .= ';' . $item['title'] . "\n";
				} elseif ( $item['type'] == 'article' ) {
					if ( $item['currentVersion'] == 1 ) {
						$articleText .= ":[[" . $item['title'];
						if ( isset( $item['displaytitle'] ) && $item['displaytitle'] ) {
							$articleText .= "|" . $item['displaytitle'];
						}
						$articleText .= "]]\n";
					} else {
						$articleText .= ":[{{fullurl:" . $item['title'];
						$articleText .= "|oldid=" . $item['revision'] . "}} ";
						if ( isset( $item['displaytitle'] ) && $item['displaytitle'] ) {
							$articleText .= $item['displaytitle'];
						} else {
							$articleText .= $item['title'];
						}
						$articleText .= "]\n";
					}
				}
				// $articleText .= $item['revision'] . "/" . $item['latest']."\n";
			}
		}
		$t = $this->msg( 'coll-bookscategory' )->inContentLanguage();
		if ( !$t->isDisabled() ) {
			$catTitle = Title::makeTitle( NS_CATEGORY, $t->text() );
			if ( $catTitle !== null ) {
				$articleText .= "\n[[" . $catTitle->getPrefixedText() .
					"|" . wfEscapeWikiText( $title->getSubpageText() ) . "]]\n";
			}
		}

		$req = new DerivativeRequest(
			$this->getRequest(),
			[
				'action' => 'edit',
				'title' => $title->getPrefixedText(),
				'text' => $articleText,
				'token' => $this->getUser()->getEditToken(),
			],
			true
		);
		$api = new ApiMain( $req, true );
		$api->execute();
		return true;
	}

	/**
	 * Take an array of arrays, each containing information about one item to be
	 * assembled and exported, and appropriately feed the backend chosen ($writer).
	 * @param array $collection following the collection/Metabook dictionary formats
	 * https://www.mediawiki.org/wiki/Offline_content_generator/metabook.json
	 * https://mwlib.readthedocs.org/en/latest/internals.html#article
	 * @param Title $referrer Used only to provide a returnto parameter.
	 * @param string $writer A writer registered in the appropriate configuration.
	 */
	private function renderCollection( array $collection, Title $referrer, $writer ) {
		if ( !$writer ) {
			$writer = 'rl';
		}

		$api = CollectionRenderingAPI::instance( $writer );
		$response = $api->render( $collection );

		if ( !$this->handleResult( $response ) ) {
			return;
		}

		$query = 'bookcmd=rendering'
			. '&return_to=' . urlencode( $referrer->getPrefixedText() )
			. '&collection_id=' . urlencode( $response->get( 'collection_id' ) )
			. '&writer=' . urlencode( $writer );
		if ( $response->get( 'is_cached' ) ) {
			$query .= '&is_cached=1';
		}
		$redirect = SkinComponentUtils::makeSpecialUrl( 'Book', $query );
		$this->getOutput()->redirect( $redirect );
	}

	private function forceRenderCollection() {
		$request = $this->getRequest();

		$collectionID = $request->getVal( 'collection_id', '' );
		$writer = $request->getVal( 'writer', 'rl' );

		$api = CollectionRenderingAPI::instance( $writer );
		$response = $api->forceRender( $collectionID );

		if ( !$response || $response->isError() ) {
			return;
		}

		$query = 'bookcmd=rendering'
			. '&return_to=' . urlencode( $request->getVal( 'return_to', '' ) )
			. '&collection_id=' . urlencode( $response->get( 'collection_id' ) )
			. '&writer=' . urlencode( $response->get( 'writer' ) );
		if ( $response->get( 'is_cached' ) ) {
			$query .= '&is_cached=1';
		}
		$this->getOutput()->redirect( SkinComponentUtils::makeSpecialUrl( 'Book', $query ) );
	}

	private function renderRenderingPage() {
		$this->setHeaders();
		$request = $this->getRequest();
		$out = $this->getOutput();
		$stats = MediaWikiServices::getInstance()->getStatsFactory();

		$collectionId = $request->getVal( 'collection_id' );
		$writer = $request->getVal( 'writer' );
		$return_to = $request->getVal( 'return_to', '' );

		$result = CollectionRenderingAPI::instance( $writer )->getRenderStatus( $collectionId );
		if ( !$this->handleResult( $result ) ) {
			 // FIXME?
			return;
		}

		$query = 'collection_id=' . urlencode( $collectionId )
			. '&writer=' . urlencode( $writer )
			. '&return_to=' . urlencode( $return_to );

		switch ( $result->get( 'state' ) ) {
			case 'pending':
			case 'progress':
				$out->addHeadItem(
					'refresh-nojs',
					'<noscript><meta http-equiv="refresh" content="2" /></noscript>'
				);
				$out->addInlineScript( 'var collection_id = ' . Html::encodeJsVar( urlencode( $collectionId ) ) . ';' );
				$out->addInlineScript( 'var writer = ' . Html::encodeJsVar( urlencode( $writer ) ) . ';' );
				$out->addInlineScript( 'var collection_rendering = true;' );
				$out->addModules( 'ext.collection' );
				$out->setPageTitleMsg( $this->msg( 'coll-rendering_title' ) );

				$statusText = $result->get( 'status', 'status' );
				if ( $statusText ) {
					if ( $result->get( 'status', 'article' ) ) {
						$statusText .= ' ' . $this->msg(
								'coll-rendering_article',
								$result->get( 'status', 'article' )
							)->text();
					} elseif ( $result->get( 'status', 'page' ) ) {
						$statusText .= ' ';
						$statusText .= $this->msg( 'coll-rendering_page' )
							->numParams( $result->get( 'status', 'page' ) )->text();
					}
					$status = $this->msg( 'coll-rendering_status', $statusText )->text();
				} else {
					$status = '';
				}

				$template = new CollectionRenderingTemplate();
				$template->set( 'status', $status );
				$progress = $result->get( 'status', 'progress' );
				if ( !$progress ) {
					$progress = 0.00;
				}
				$template->set( 'progress', $progress );
				$out->addTemplate( $template );
				$stats->getCounter( 'collection_renderingpage_total' )
					->setLabel( 'status', 'pending' )
					->copyToStatsdAt( 'collection.renderingpage.pending' )
					->increment();
				break;

			case 'finished':
				$out->setPageTitleMsg( $this->msg( 'coll-rendering_finished_title' ) );

				$template = new CollectionFinishedTemplate();
				$template->set(
					'download_url',
					MediaWikiServices::getInstance()->getUrlUtils()->expand(
						SkinComponentUtils::makeSpecialUrl( 'Book', 'bookcmd=download&' . $query ),
						PROTO_CURRENT
					)
				);
				$template->set( 'is_cached', $request->getVal( 'is_cached' ) );
				$template->set( 'writer', $request->getVal( 'writer' ) );
				$template->set( 'query', $query );
				$template->set( 'return_to', $return_to );
				$out->addTemplate( $template );
				$stats->getCounter( 'collection_renderingpage_total' )
					->setLabel( 'status', 'finished' )
					->copyToStatsdAt( 'collection.renderingpage.finished' )
					->increment();
				break;

			case 'failed':
				$out->setPageTitleMsg( $this->msg( 'coll-rendering_failed_title' ) );
				$statusText = $result->get( 'status', 'status' );
				if ( $statusText ) {
					$status = $this->msg( 'coll-rendering_failed_status', $statusText )->text();
				} else {
					$status = '';
				}

				$template = new CollectionFailedTemplate();
				$template->set( 'status', $status );
				$template->set( 'query', $query );
				$template->set( 'return_to', $return_to );
				$out->addTemplate( $template );
				$stats->getCounter( 'collection_renderingpage_total' )
					->setLabel( 'status', 'failed' )
					->copyToStatsdAt( 'collection.renderingpage.failed' )
					->increment();
				break;

			default:
				$stats->getCounter( 'collection_renderingpage_total' )
					->setLabel( 'status', 'unknown' )
					->copyToStatsdAt( 'collection.renderingpage.unknown' )
					->increment();
				throw new UnexpectedValueException( __METHOD__ . "(): unknown state '{$result->get( 'state' )}'" );
		}
	}

	private function download() {
		$request = $this->getRequest();
		$collectionId = $request->getVal( 'collection_id' );
		$writer = $request->getVal( 'writer' );
		$api = CollectionRenderingAPI::instance( $writer );

		$this->tempfile = tmpfile();
		$r = $api->getRenderStatus( $collectionId );

		$info = false;
		$url = $r->get( 'url' );
		if ( $url ) {
			$req = MediaWikiServices::getInstance()->getHttpRequestFactory()->create( $url, [], __METHOD__ );
			$req->setCallback( function ( $fh, $content ) {
				return fwrite( $this->tempfile, $content );
			} );
			if ( $req->execute()->isOK() ) {
				$info = true;
			}
			$content_type = $r->get( 'content_type' );
			$content_length = $r->get( 'content_length' );
			$content_disposition = $r->get( 'content_disposition' );
		} else {
			$info = $api->download( $collectionId );
			$content_type = $info->get( 'content_type' );
			$content_length = $info->get( 'download_content_length' );
			$content_disposition = null;
			if ( $info->isError() ) {
				$info = false;
			}
		}
		if ( !$info ) {
			$this->getOutput()->showErrorPage(
				'coll-download_notfound_title',
				'coll-download_notfound_text'
			);
			return;
		}
		wfResetOutputBuffers();
		header( 'Content-Type: ' . $content_type );
		header( 'Content-Length: ' . $content_length );
		if ( $content_disposition ) {
			header( 'Content-Disposition: ' . $content_disposition );
		} else {
			$collectionContentTypeToFilename = $this->getConfig()->get( 'CollectionContentTypeToFilename' );
			$mimeType = explode( ';', $content_type )[0];
			if ( isset( $collectionContentTypeToFilename[$mimeType] ) ) {
				header(
					'Content-Disposition: ' .
					'inline; filename=' .
					$collectionContentTypeToFilename[$mimeType]
				);
			}
		}
		fseek( $this->tempfile, 0 );
		fpassthru( $this->tempfile );
		$this->getOutput()->disable();
	}

	/**
	 * Render a single page: fetch page name and revision information, then
	 * assemble and feed to renderCollection() a single-item $collection.
	 * @param Title $title Full page name aka prefixed title.
	 * @param int $oldid
	 * @return array|null
	 */
	private function makeCollection( $title, $oldid ) {
		if ( $title === null ) {
			$this->getOutput()->showErrorPage( 'coll-notitle_title', 'coll-notitle_msg' );
			return null;
		}
		$article = [
			'type' => 'article',
			'content_type' => 'text/x-wiki',
			'title' => $title->getPrefixedText()
		];
		if ( $oldid ) {
			$article['revision'] = (string)$oldid;
		}

		$revision = MediaWikiServices::getInstance()
			->getRevisionLookup()
			->getRevisionByTitle( $title, $oldid );
		if ( $revision ) {
			$article['timestamp'] = wfTimestamp( TS_UNIX, $revision->getTimestamp() );
		}
		return [ 'items' => [ $article ] ];
	}

	/**
	 * @param array $collection
	 * @param string $partner
	 */
	private function postZip( array $collection, $partner ) {
		$out = $this->getOutput();
		if ( !isset( $this->mPODPartners[$partner] ) ) {
			$out->showErrorPage( 'coll-invalid_podpartner_title', 'coll-invalid_podpartner_msg' );
			return;
		}

		$api = CollectionRenderingAPI::instance();
		$result = $api->postZip( $collection, $this->mPODPartners[$partner]['posturl'] );
		if ( !$this->handleResult( $result ) ) {
			return;
		}
		if ( $result->get( 'redirect_url' ) ) {
			$out->redirect( $result->get( 'redirect_url' ) );
		}
	}

	/**
	 * @param string $colltype
	 * @param string $title
	 * @param string $pcollname
	 * @param string $ccollname
	 */
	private function renderSaveOverwritePage( $colltype, $title, $pcollname, $ccollname ) {
		$this->setHeaders();
		$out = $this->getOutput();
		$out->setPageTitleMsg( $this->msg( 'coll-save_collection' ) );

		$template = new CollectionSaveOverwriteTemplate();
		$template->set( 'title', $title );
		$template->set( 'pcollname', $pcollname );
		$template->set( 'ccollname', $ccollname );
		$template->set( 'colltype', $colltype );
		$template->set( 'skin', $out->getSkin() );
		$this->getOutput()->addTemplate( $template );
	}

	/**
	 * @param string $title
	 */
	private function renderLoadOverwritePage( $title ) {
		$this->setHeaders();
		$this->getOutput()->setPageTitleMsg( $this->msg( 'coll-load_collection' ) );

		$template = new CollectionLoadOverwriteTemplate();
		$template->set( 'output', $this->getOutput() );
		$template->set( 'title', $title );
		$this->getOutput()->addTemplate( $template );
	}

	/**
	 * @param CollectionAPIResult $result
	 *
	 * @return bool Whether the result had errors
	 */
	private function handleResult( CollectionAPIResult $result ) {
		if ( !$result->isError() ) {
			return true;
		}

		$output = $this->getOutput();
		MessageBoxHelper::addModuleStyles( $output );
		$output->prepareErrorPage();
		$output->setPageTitleMsg( $output->msg( 'coll-request_failed_title' ) );
		$output->addHTML( MessageBoxHelper::renderWarningBoxes() );
		$output->addWikiMsgArray( 'coll-request_failed_msg', [] );
		$output->returnToMain();

		return false;
	}

	/** @inheritDoc */
	protected function getGroupName() {
		return 'pagetools';
	}
}
