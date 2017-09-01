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

class CollectionHooks {
	public static function onSetup() {
		// This prevents this extension from being run in environments
		// that don't have the dependent code in core; this should be
		// updated as a part of when additional dependencies are
		// created and pushed into MediaWiki's core.  In particular
		// this tracks the use of OOjs UI in the Collection extension.
		wfUseMW( '1.25wmf19' );
	}

	/**
	 * Callback for hook SkinBuildSidebar
	 *
	 * @param Skin $skin
	 * @param array &$bar
	 *
	 * @return bool
	 */
	public static function buildSidebar( $skin, &$bar ) {
		global $wgCollectionPortletForLoggedInUsersOnly;

		if ( !$wgCollectionPortletForLoggedInUsersOnly || $skin->getUser()->isLoggedIn() ) {
			$portlet = self::getPortlet( $skin );
			if ( $portlet ) {
				$bar[ 'coll-print_export' ] = $portlet;
			}
		}
		return true;
	}

	/**
	 * @param Skin $skin
	 * @param array &$navUrls
	 * @return bool
	 */
	public static function buildNavUrls( $skin, &$navUrls ) {
		global $wgCollectionPortletForLoggedInUsersOnly;

		if ( !$wgCollectionPortletForLoggedInUsersOnly || $skin->getUser()->isLoggedIn() ) {
			// We move this guy out to our own box
			$navUrls['print'] = false;
		}
		return true;
	}

	/**
	 * Return HTML-code to be inserted as portlet
	 *
	 * @param Skin $sk
	 *
	 * @return string
	 */
	public static function getPortlet( $sk ) {
		global $wgCollectionArticleNamespaces;
		global $wgCollectionFormats;
		global $wgCollectionPortletFormats;

		$title = $sk->getTitle();

		if ( is_null( $title ) || !$title->exists() ) {
			return false;
		}

		$namespace = $title->getNamespace();

		if ( !in_array( $namespace, $wgCollectionArticleNamespaces )
			&& $namespace != NS_CATEGORY ) {
			return false;
		}

		$action = $sk->getRequest()->getVal( 'action', 'view' );
		if ( $action != 'view' && $action != 'purge' ) {
			return false;
		}

		$out = [];

		$booktitle = SpecialPage::getTitleFor( 'Book' );
		if ( !CollectionSession::isEnabled() ) {
			$out[] = [
				'text' => $sk->msg( 'coll-create_a_book' )->escaped(),
				'id' => 'coll-create_a_book',
				'href' => $booktitle->getLocalURL(
					[ 'bookcmd' => 'book_creator', 'referer' => $title->getPrefixedText() ]
				),
			];
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

		foreach ( $wgCollectionPortletFormats as $writer ) {
			$params['writer'] = $writer;
			$out[] = [
				'text' => $sk->msg( 'coll-download_as', $wgCollectionFormats[$writer] )->escaped(),
				'id' => 'coll-download-as-' . $writer,
				'href' => $booktitle->getLocalURL( $params ),
			];
		}

		// Move the 'printable' link into our section for consistency
		if ( $action == 'view' || $action == 'purge' ) {
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
	 * @return bool
	 */
	public static function siteNoticeAfter( &$siteNotice, $skin ) {
		global $wgCollectionArticleNamespaces;

		$request = $skin->getRequest();
		$title = $skin->getTitle();

		$action = $request->getVal( 'action' );
		if ( $action != '' && $action != 'view' && $action != 'purge' ) {
			return true;
		}

		if ( !CollectionSession::hasSession()
			|| !isset( $_SESSION['wsCollection']['enabled'] )
			|| !$_SESSION['wsCollection']['enabled'] ) {
			return true;
		}

		if ( $title->isSpecial( 'Book' ) ) {
			$cmd = $request->getVal( 'bookcmd', '' );
			if ( $cmd == 'suggest' ) {
				$siteNotice .= self::renderBookCreatorBox( $title, 'suggest' );
			} elseif ( $cmd == '' ) {
				$siteNotice .= self::renderBookCreatorBox( $title, 'showbook' );
			}
			return true;
		}

		if ( !$title->exists() ) {
			return true;
		}

		$namespace = $title->getNamespace();
		if ( !in_array( $namespace, $wgCollectionArticleNamespaces )
			&& $namespace != NS_CATEGORY ) {
			return true;
		}

		$siteNotice .= self::renderBookCreatorBox( $title );
		return true;
	}

	/**
	 * @param Title $title
	 * @param string $mode
	 * @return string
	 */
	public static function renderBookCreatorBox( $title, $mode = '' ) {
		global $wgOut, $wgExtensionAssetsPath, $wgRequest;
		$templateParser = new TemplateParser( __DIR__ . '/templates' );

		$imagePath = "$wgExtensionAssetsPath/Collection/images";
		$ptext = $title->getPrefixedText();
		$oldid = $wgRequest->getVal( 'oldid', 0 );
		if ( $oldid == $title->getLatestRevID() ) {
			$oldid = 0;
		}

		$wgOut->addModules( 'ext.collection.bookcreator' );
		$wgOut->addModuleStyles( 'ext.collection.bookcreator.styles' );

		$addRemoveState = $mode;

		return $templateParser->processTemplate( 'create-book', [
			"actionsHtml" => self::getBookCreatorBoxContent( $title, $addRemoveState, $oldid ),
			"imagePath" => $imagePath,
			"title" => wfMessage( 'coll-book_creator' )->text(),
			"disable" => [
				"url" => SpecialPage::getTitleFor( 'Book' )->getLocalUrl(
					[ 'bookcmd' => 'stop_book_creator', 'referer' => $ptext ]
				),
				"title" => wfMessage( 'coll-book_creator_disable_tooltip' )->text(),
				"label" => wfMessage( 'coll-disable' )->escaped(),
			],
			"help" => [
				"url" => Title::newFromText( wfMessage( 'coll-helppage' )->text() )->getLocalUrl(),
				"label" => wfMessage( 'coll-help' )->escaped(),
				"title" => wfMessage( 'coll-help_tooltip' )->text(),
				"icon" => $imagePath . '/silk-help.png',
			]
		] );
	}

	/**
	 * @param string $title
	 * @param string $ajaxHint Defaults to null
	 * @param null|int $oldid
	 * @return string
	 */
	public static function getBookCreatorBoxContent( $title, $ajaxHint = null, $oldid = null ) {
		global $wgExtensionAssetsPath;

		$imagePath = "$wgExtensionAssetsPath/Collection/images";

		return self::getBookCreatorBoxAddRemoveLink( $imagePath, $ajaxHint, $title, $oldid )
			. self::getBookCreatorBoxShowBookLink( $imagePath, $ajaxHint )
			. self::getBookCreatorBoxSuggestLink( $imagePath, $ajaxHint );
	}

	/**
	 * @param string $imagePath
	 * @param string $ajaxHint
	 * @param Title $title
	 * @param int $oldid
	 * @return string
	 */
	public static function getBookCreatorBoxAddRemoveLink( $imagePath, $ajaxHint, $title, $oldid ) {
		$namespace = $title->getNamespace();
		$ptext = $title->getPrefixedText();

		if ( $ajaxHint == 'suggest' || $ajaxHint == 'showbook' ) {
			return Xml::tags( 'span',
				[ 'style' => 'color: #777;' ],
				Xml::element( 'img',
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

		if ( $ajaxHint == 'addcategory' || $namespace == NS_CATEGORY ) {
			$id = 'coll-add_category';
			$icon = 'silk-add.png';
			$captionMsg = 'coll-add_category';
			$tooltipMsg = 'coll-add_category_tooltip';
			$query = [ 'bookcmd' => 'add_category', 'cattitle' => $title->getText() ];
			$onclick = "collectionCall('AddCategory', ['addcategory', wgTitle]); return false;";
		} else {
			if ( $ajaxHint == 'addarticle'
				|| ( $ajaxHint == '' && CollectionSession::findArticle( $ptext, $oldid ) == - 1 ) ) {
				$id = 'coll-add_article';
				$icon = 'silk-add.png';
				$captionMsg = 'coll-add_this_page';
				$tooltipMsg = 'coll-add_page_tooltip';
				$query = [ 'bookcmd' => 'add_article', 'arttitle' => $ptext, 'oldid' => $oldid ];
				$onclick = "collectionCall('AddArticle', ['removearticle', wgNamespaceNumber, wgTitle, " .
					Xml::encodeJsVar( $oldid ) . "]); return false;";
			} else {
				$id = 'coll-remove_article';
				$icon = 'silk-remove.png';
				$captionMsg = 'coll-remove_this_page';
				$tooltipMsg = 'coll-remove_page_tooltip';
				$query = [ 'bookcmd' => 'remove_article', 'arttitle' => $ptext, 'oldid' => $oldid ];
				$onclick = "collectionCall('RemoveArticle', ['addarticle', wgNamespaceNumber, wgTitle, " .
					Xml::encodeJsVar( $oldid ) . "]); return false;";
			}
		}

		return Linker::linkKnown(
			SpecialPage::getTitleFor( 'Book' ),
			Xml::element( 'img',
				[
					'src' => "$imagePath/$icon",
					'alt' => '',
					'width' => '16',
					'height' => '16',
				]
			)
			. '&#160;' . wfMessage( $captionMsg )->escaped(),
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
	 * @param string $ajaxHint
	 * @return string
	 */
	public static function getBookCreatorBoxShowBookLink( $imagePath, $ajaxHint ) {
		$numArticles = CollectionSession::countArticles();

		if ( $ajaxHint == 'showbook' ) {
			return Xml::tags( 'strong',
				[
					'class' => 'collection-creatorbox-iconlink',
				],
				Xml::element( 'img',
					[
						'src' => "$imagePath/silk-book_open.png",
						'alt' => '',
						'width' => '16',
						'height' => '16',
					]
				)
				. '&#160;' . wfMessage( 'coll-show_collection' )->escaped()
				. ' (' . wfMessage( 'coll-n_pages' )->numParams( $numArticles )->escaped() . ')'
			); // @todo FIXME: Hard coded parentheses.
		} else {
			return Linker::linkKnown(
				SpecialPage::getTitleFor( 'Book' ),
				Xml::element( 'img',
					[
						'src' => "$imagePath/silk-book_open.png",
						'alt' => '',
						'width' => '16',
						'height' => '16',
					]
				)
				. '&#160;' . wfMessage( 'coll-show_collection' )->escaped()
					. ' (' . wfMessage( 'coll-n_pages' )->numParams( $numArticles )->escaped() . ')',
				[
					'rel' => 'nofollow',
					'title' => wfMessage( 'coll-show_collection_tooltip' )->text(),
					'class' => 'collection-creatorbox-iconlink',
				]
			); // @todo FIXME: Hard coded parentheses.
		}
	}

	/**
	 * @param string $imagePath
	 * @param string $ajaxHint
	 * @return string
	 */
	public static function getBookCreatorBoxSuggestLink( $imagePath, $ajaxHint ) {
		if ( wfMessage( 'coll-suggest_enabled' )->escaped() != '1' ) {
			return '';
		}

		if ( $ajaxHint == 'suggest' ) {
			return Xml::tags( 'strong',
				[
					'class' => 'collection-creatorbox-iconlink',
				],
				Xml::element( 'img',
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
			return Linker::linkKnown(
				SpecialPage::getTitleFor( 'Book' ),
				Xml::element( 'img',
					[
						'src' => "$imagePath/silk-wand.png",
						'alt' => '',
						'width' => '16',
						'height' => '16',
						'style' => 'vertical-align: text-bottom',
					]
				)
				. '&#160;' . wfMessage( 'coll-make_suggestions' )->escaped(),
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
	 * @param array $modifiedTimes
	 * @return bool
	 */
	public static function checkLastModified( $modifiedTimes ) {
		if ( CollectionSession::hasSession() ) {
			$modifiedTimes['collection'] = $_SESSION['wsCollection']['timestamp'];
		}
		return true;
	}
}
