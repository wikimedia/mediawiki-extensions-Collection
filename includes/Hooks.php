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

namespace MediaWiki\Extension\Collection;

use MediaWiki\Config\Config;
use MediaWiki\Context\IContextSource;
use MediaWiki\Hook\SidebarBeforeOutputHook;
use MediaWiki\Hook\SiteNoticeAfterHook;
use MediaWiki\Html\Html;
use MediaWiki\Html\TemplateParser;
use MediaWiki\MainConfigNames;
use MediaWiki\MediaWikiServices;
use MediaWiki\Output\Hook\OutputPageCheckLastModifiedHook;
use MediaWiki\Output\OutputPage;
use MediaWiki\Session\SessionManager;
use MediaWiki\Skin\Skin;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\Title;
use Wikimedia\HtmlArmor\HtmlArmor;

class Hooks implements
	SidebarBeforeOutputHook,
	SiteNoticeAfterHook,
	OutputPageCheckLastModifiedHook
{
	private Config $config;

	public function __construct(
		Config $config
	) {
		$this->config = $config;
	}

	/**
	 * Callback for SidebarBeforeOutput hook
	 *
	 * @param Skin $skin
	 * @param array &$sidebar
	 */
	public function onSidebarBeforeOutput( $skin, &$sidebar ): void {
		$portletForLoggedInUsersOnly = $this->config->get( 'CollectionPortletForLoggedInUsersOnly' );

		if ( !$portletForLoggedInUsersOnly || $skin->getUser()->isNamed() ) {

			$portlet = $this->getPortlet( $skin );

			if ( $portlet ) {
				// Unset 'print' item. We have moved it to our own section.
				unset( $sidebar['TOOLBOX']['print'] );

				// Add our section
				$sidebar[ 'coll-print_export' ] = $portlet;
			}
		}
	}

	/**
	 * Return HTML-code to be inserted as portlet
	 *
	 * @param Skin $sk
	 *
	 * @return array[]|false
	 */
	private function getPortlet( $sk ) {
		$title = $sk->getTitle();

		if ( $title === null || !$title->exists() ) {
			return false;
		}

		$namespace = $title->getNamespace();

		if ( !in_array( $namespace, $this->config->get( 'CollectionArticleNamespaces' ) )
			&& $namespace != NS_CATEGORY ) {
			return false;
		}

		$action = $sk->getRequest()->getRawVal( 'action' ) ?? 'view';
		if ( $action !== 'view' && $action !== 'purge' ) {
			return false;
		}

		$out = [];

		$booktitle = SpecialPage::getTitleFor( 'Book' );
		if ( !Session::isEnabled() ) {
			if ( !$sk->getConfig()->get( 'CollectionDisableSidebarLink' ) ) {
				$out[] = [
					'text' => $sk->msg( 'coll-create_a_book' )->text(),
					'id' => 'coll-create_a_book',
					'href' => $booktitle->getLocalURL(
						[ 'bookcmd' => 'book_creator', 'referer' => $title->getPrefixedText() ]
						),
				];
			}
		} else {
			$out[] = [
				'text' => $sk->msg( 'coll-book_creator_disable' )->escaped(),
				'id' => 'coll-book_creator_disable',
				'href' => $booktitle->getLocalURL(
					[
						'bookcmd' => 'stop_book_creator',
						'referer' => $title->getPrefixedText(),
					]
				),
			];
		}

		$params = [
			'bookcmd' => 'render_article',
			'arttitle' => $title->getPrefixedText(),
			'returnto' => $title->getPrefixedText(),
		];

		$oldid = $sk->getRequest()->getVal( 'oldid' );
		if ( $oldid ) {
			$params['oldid'] = $oldid;
		} else {
			$params['oldid'] = $title->getLatestRevID();
		}

		$formats = $this->config->get( 'CollectionFormats' );
		foreach ( $this->config->get( 'CollectionPortletFormats' ) as $writer ) {
			$params['writer'] = $writer;
			$out[] = [
				'text' => $sk->msg( 'coll-download_as', $formats[$writer] )->text(),
				'id' => 'coll-download-as-' . $writer,
				'href' => $booktitle->getLocalURL( $params ),
			];
		}

		// Move the 'printable' link into our section for consistency
		if ( $action === 'view' || $action === 'purge' ) {
			if ( !$sk->getOutput()->isPrintable() ) {
				$out[] = [ 'text' => $sk->msg( 'printableversion' )->text(),
					'id' => 't-print',
					'href' => $title->getLocalURL( [ 'printable' => 'yes' ] )
				];
			}
		}

		return $out;
	}

	/**
	 * Callback for hook SiteNoticeAfter
	 * @param string &$siteNotice
	 * @param Skin $skin
	 */
	public function onSiteNoticeAfter( &$siteNotice, $skin ) {
		$request = $skin->getRequest();

		$action = $request->getRawVal( 'action' ) ?? 'view';
		if ( $action !== 'view' && $action !== 'purge' ) {
			return;
		}

		$session = SessionManager::getGlobalSession();

		if (
			!isset( $session['wsCollection'] ) ||
			!isset( $session['wsCollection']['enabled'] ) ||
			!$session['wsCollection']['enabled']
		) {
			return;
		}

		$title = $skin->getTitle();
		if ( $title->isSpecial( 'Book' ) ) {
			$cmd = $request->getVal( 'bookcmd', '' );
			if ( $cmd == 'suggest' ) {
				$siteNotice .= $this->renderBookCreatorBox( $skin, 'suggest' );
			} elseif ( $cmd == '' ) {
				$siteNotice .= $this->renderBookCreatorBox( $skin, 'showbook' );
			}
			return;
		}

		if ( !$title->exists() ) {
			return;
		}

		$namespace = $title->getNamespace();
		if ( !in_array( $namespace, $this->config->get( 'CollectionArticleNamespaces' ) )
			&& $namespace != NS_CATEGORY ) {
			return;
		}

		$siteNotice .= $this->renderBookCreatorBox( $skin );
	}

	/**
	 * @param IContextSource $context
	 * @param string $mode
	 * @return string
	 */
	private function renderBookCreatorBox( IContextSource $context, $mode = '' ) {
		$templateParser = new TemplateParser( dirname( __DIR__ ) . '/templates' );

		$imagePath = $context->getConfig()->get( MainConfigNames::ExtensionAssetsPath ) . '/Collection/images';
		$title = $context->getTitle();
		$ptext = $title->getPrefixedText();
		$oldid = $context->getRequest()->getInt( 'oldid', 0 );
		if ( $oldid == $title->getLatestRevID() ) {
			$oldid = 0;
		}

		$out = $context->getOutput();
		$out->addModules( 'ext.collection.bookcreator' );
		$out->addModuleStyles( 'ext.collection.bookcreator.styles' );

		$addRemoveState = $mode;
		$helpPage = Title::newFromText( $context->msg( 'coll-helppage' )->text() );

		return $templateParser->processTemplate( 'create-book', [
			"actionsHtml" => self::getBookCreatorBoxContent( $title, $addRemoveState, $oldid ),
			"imagePath" => $imagePath,
			"title" => $context->msg( 'coll-book_creator' )->text(),
			"disable" => [
				"url" => SpecialPage::getTitleFor( 'Book' )->getLocalUrl(
					[ 'bookcmd' => 'stop_book_creator', 'referer' => $ptext ]
				),
				"title" => $context->msg( 'coll-book_creator_disable_tooltip' )->text(),
				"label" => $context->msg( 'coll-disable' )->escaped(),
			],
			"help" => [
				"url" => $helpPage ? $helpPage->getLocalUrl() : '',
				"label" => $context->msg( 'coll-help' )->escaped(),
				"title" => $context->msg( 'coll-help_tooltip' )->text(),
				"icon" => $imagePath . '/silk-help.png',
			]
		] );
	}

	/**
	 * @param Title $title
	 * @param string|null $hint Defaults to null
	 * @param null|int $oldid
	 * @return string
	 */
	public static function getBookCreatorBoxContent( Title $title, $hint = null, $oldid = null ) {
		$path = MediaWikiServices::getInstance()->getMainConfig()->get( MainConfigNames::ExtensionAssetsPath );

		$imagePath = "$path/Collection/images";

		return self::getBookCreatorBoxAddRemoveLink( $imagePath, $hint, $title, $oldid )
			. self::getBookCreatorBoxShowBookLink( $imagePath, $hint )
			. self::getBookCreatorBoxSuggestLink( $imagePath, $hint );
	}

	/**
	 * @param string $imagePath
	 * @param string $hint
	 * @param Title $title
	 * @param int $oldid
	 * @return string
	 */
	public static function getBookCreatorBoxAddRemoveLink(
		$imagePath,
		$hint,
		Title $title,
		$oldid
	) {
		$namespace = $title->getNamespace();
		$ptext = $title->getPrefixedText();

		if ( $hint == 'suggest' || $hint == 'showbook' ) {
			return Html::rawElement( 'span',
				[ 'style' => 'color: #777;' ],
				Html::element( 'img',
					[
						'src' => "$imagePath/disabled.png",
						'alt' => '',
						'width' => '16',
						'height' => '16',
						'style' => 'vertical-align: text-bottom',
					]
				)
				. '&#160;' . wfMessage( 'coll-not_addable' )->escaped()
			);
		}

		if ( $hint == 'addcategory' || $namespace == NS_CATEGORY ) {
			$id = 'coll-add_category';
			$icon = 'silk-add.png';
			$captionMsg = 'coll-add_category';
			$tooltipMsg = 'coll-add_category_tooltip';
			$query = [ 'bookcmd' => 'add_category', 'cattitle' => $title->getText() ];
			$onclick = "collectionCall('addcategory', mw.config.get('wgNamespaceNumber')," .
				"mw.config.get('wgTitle')]); return false;";
		} else {
			$collectionArgsJs = "mw.config.get('wgNamespaceNumber'), mw.config.get('wgTitle'), " .
				Html::encodeJsVar( $oldid );
			if ( $hint == 'addarticle'
				|| ( $hint == '' && Session::findArticle( $ptext, $oldid ) == -1 ) ) {
				$id = 'coll-add_article';
				$icon = 'silk-add.png';
				$captionMsg = 'coll-add_this_page';
				$tooltipMsg = 'coll-add_page_tooltip';
				$query = [ 'bookcmd' => 'add_article', 'arttitle' => $ptext, 'oldid' => $oldid ];
				$onclick = "collectionCall('addarticle', " . $collectionArgsJs . "); return false;";
			} else {
				$id = 'coll-remove_article';
				$icon = 'silk-remove.png';
				$captionMsg = 'coll-remove_this_page';
				$tooltipMsg = 'coll-remove_page_tooltip';
				$query = [ 'bookcmd' => 'remove_article', 'arttitle' => $ptext, 'oldid' => $oldid ];
				$onclick = "collectionCall('removearticle', " . $collectionArgsJs . "); return false;";
			}
		}
		$linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();
		return $linkRenderer->makeKnownLink(
			SpecialPage::getTitleFor( 'Book' ),
			new HtmlArmor( Html::element( 'img',
				[
					'src' => "$imagePath/$icon",
					'alt' => '',
					'width' => '16',
					'height' => '16',
				]
			)
			. '&#160;' . wfMessage( $captionMsg )->escaped() ),
			[
				'id' => $id,
				'rel' => 'nofollow',
				'title' => wfMessage( $tooltipMsg )->text(),
				'onclick' => $onclick,
			],
			$query
		);
	}

	/**
	 * @param string $imagePath
	 * @param string $hint
	 * @return string
	 */
	public static function getBookCreatorBoxShowBookLink( $imagePath, $hint ) {
		$numArticles = Session::countArticles();

		if ( $hint == 'showbook' ) {
			return Html::rawElement( 'strong',
				[
					'class' => 'collection-creatorbox-iconlink',
				],
				Html::element( 'img',
					[
						'src' => "$imagePath/silk-book_open.png",
						'alt' => '',
						'width' => '16',
						'height' => '16',
					]
				)
				. '&#160;' . wfMessage( 'coll-show_collection' )->escaped()
				// @todo FIXME: Hard coded parentheses.
				. ' (' . wfMessage( 'coll-n_pages' )->numParams( $numArticles )->escaped() . ')'
			);
		} else {
			$linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();
			return $linkRenderer->makeLink(
				SpecialPage::getTitleFor( 'Book' ),
				new HtmlArmor( Html::element( 'img',
					[
						'src' => "$imagePath/silk-book_open.png",
						'alt' => '',
						'width' => '16',
						'height' => '16',
					]
				)
				. '&#160;' . wfMessage( 'coll-show_collection' )->escaped()
					// @todo FIXME: Hard coded parentheses.
					. ' (' . wfMessage( 'coll-n_pages' )->numParams( $numArticles )->escaped() . ')' ),
				[
					'rel' => 'nofollow',
					'title' => wfMessage( 'coll-show_collection_tooltip' )->text(),
					'class' => 'collection-creatorbox-iconlink',
				]
			);
		}
	}

	/**
	 * @param string $imagePath
	 * @param string $hint
	 * @return string
	 */
	public static function getBookCreatorBoxSuggestLink( $imagePath, $hint ) {
		if ( wfMessage( 'coll-suggest_enabled' )->escaped() != '1' ) {
			return '';
		}

		if ( $hint == 'suggest' ) {
			return Html::rawElement( 'strong',
				[
					'class' => 'collection-creatorbox-iconlink',
				],
				Html::element( 'img',
					[
						'src' => "$imagePath/silk-wand.png",
						'alt' => '',
						'width' => '16',
						'height' => '16',
						'style' => 'vertical-align: text-bottom',
					]
				)
				. '&#160;' . wfMessage( 'coll-make_suggestions' )->escaped()
			);
		} else {
			$linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();
			return $linkRenderer->makeLink(
				SpecialPage::getTitleFor( 'Book' ),
				new HtmlArmor( Html::element( 'img',
					[
						'src' => "$imagePath/silk-wand.png",
						'alt' => '',
						'width' => '16',
						'height' => '16',
						'style' => 'vertical-align: text-bottom',
					]
				)
				. '&#160;' . wfMessage( 'coll-make_suggestions' )->escaped() ),
				[
					'rel' => 'nofollow',
					'title' => wfMessage( 'coll-make_suggestions_tooltip' )->text(),
					'class' => 'collection-creatorbox-iconlink',
				],
				[ 'bookcmd' => 'suggest' ]
			);
		}
	}

	/**
	 * OutputPageCheckLastModified hook
	 * @param array &$modifiedTimes
	 * @param OutputPage $out
	 */
	public function onOutputPageCheckLastModified( &$modifiedTimes, $out ) {
		$session = SessionManager::getGlobalSession();
		if ( isset( $session['wsCollection']['timestamp'] ) ) {
			$modifiedTimes['collection'] = $session['wsCollection']['timestamp'];
		}
	}
}
