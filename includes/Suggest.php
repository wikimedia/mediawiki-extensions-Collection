<?php
/**
 * Collection Extension for MediaWiki
 *
 * Copyright (C) 2008-2009, PediaPress GmbH
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

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\Collection\Specials\SpecialCollection;
use MediaWiki\Extension\Collection\Templates\CollectionSuggestTemplate;
use MediaWiki\Session\SessionManager;

/**
 * This class contains only static methods, so there's no need for a constructor.
 * When the page Special:Book/suggest/ is loaded the method run() is called.
 * Ajax calls refresh().
 * When clearing a book the method clear() should be called.
 */
class Suggest {

	/**
	 * Main entrypoint
	 *
	 * @param string $mode
	 *        'add' => add one title to the book.
	 *        'addAll' => Add a list of titles to the book.
	 *        'ban' => Ban a title from the proposals.
	 *        'unban' => Undo a ban.
	 *        'remove' => Remove a title from the book, and ban it.
	 *        'removeOnly' => Remove a title without banning it.
	 * @param string|string[] $param Name of the article to be added, banned
	 *        or removed, or a list of article names to be added.
	 */
	public static function run( $mode = '', $param = '' ) {
		if ( !Session::hasSession() ) {
			Session::startSession();
		}

		$template = self::getCollectionSuggestTemplate( $mode, $param );
		$context = RequestContext::getMain();
		$out = $context->getOutput();
		$out->setPageTitleMsg( $context->msg( 'coll-suggest_title' ) );
		$out->addModules( 'ext.collection.suggest' );
		$out->addTemplate( $template );
	}

	/**
	 * Entrypoint for Ajax
	 *
	 * @param string $mode
	 *        'add' => add one title to the book.
	 *        'addAll' => Add a list of titles to the book.
	 *        'ban' => Ban a title from the proposals.
	 *        'unban' => Undo a ban.
	 *        'remove' => Remove a title from the book, and ban it.
	 *        'removeOnly' => Remove a title without banning it.
	 * @param string|string[] $param Name of the article to be added, banned
	 *        or removed, or a list of article names to be added.
	 * @return string[] HTML code for the proposallist and the memberlist
	 */
	public static function refresh( $mode, $param ) {
		$template = self::getCollectionSuggestTemplate( $mode, $param );
		return [
			'suggestions_html' => $template->getProposalList(),
			'members_html' => $template->getMemberList(),
			'num_pages' => wfMessage( 'coll-n_pages' )
				->numParams( Session::countArticles() )
				->escaped(),
		];
	}

	/**
	 * @param string $lastAction
	 * @param string|string[] $article
	 * @return string[] HTML
	 */
	public static function undo( $lastAction, $article ) {
		switch ( $lastAction ) {
			case 'add':
				$template = self::getCollectionSuggestTemplate( 'removeonly', $article );
				break;
			case 'ban':
				$template = self::getCollectionSuggestTemplate( 'unban', $article );
				break;
			case 'remove':
				$template = self::getCollectionSuggestTemplate( 'add', $article );
				break;
			default:
				// FIXME: Is this even possible? Shouldn't this fail with an exception?
				return [];
		}
		return [
			'suggestions_html' => $template->getProposalList(),
			'members_html' => $template->getMemberList(),
		];
	}

	/**
	 * Remove the suggestion data from the session
	 */
	public static function clear() {
		$session = SessionManager::getGlobalSession();

		if ( isset( $session['wsCollectionSuggestBan'] ) ) {
			unset( $session['wsCollectionSuggestBan'] );
		}
		if ( isset( $session['wsCollectionSuggestProp'] ) ) {
			unset( $session['wsCollectionSuggestProp'] );
		}
	}

	/**
	 * @param string $article
	 */
	private static function unban( $article ) {
		$session = SessionManager::getGlobalSession();

		if ( !isset( $session['wsCollectionSuggestBan'] ) ) {
			return;
		}
		$bans = $session['wsCollectionSuggestBan'];
		$newbans = [];
		foreach ( $bans as $ban ) {
			if ( $ban != $article ) {
				$newbans[] = $ban;
			}
		}
		$session['wsCollectionSuggestBan'] = $newbans;
	}

	/**
	 * Update the session and return the template
	 *
	 * @param string $mode
	 *        'add' => add one title to the book.
	 *        'addAll' => Add a list of titles to the book.
	 *        'ban' => Ban a title from the proposals.
	 *        'unban' => Undo a ban.
	 *        'remove' => Remove a title from the book, and ban it.
	 *        'removeOnly' => Remove a title without banning it.
	 * @param string|string[] $param Name of the article to be added, banned
	 *        or removed, or a list of article names to be added.
	 * @return CollectionSuggestTemplate the template for the wikipage
	 */
	private static function getCollectionSuggestTemplate( $mode, $param ) {
		global $wgCollectionMaxSuggestions;

		$session = SessionManager::getGlobalSession();

		if ( !isset( $session['wsCollectionSuggestBan'] ) || $mode == 'resetbans' ) {
			$session['wsCollectionSuggestBan'] = [];
		}
		if ( !isset( $session['wsCollectionSuggestProp'] ) ) {
			$session['wsCollectionSuggestProp'] = [];
		}

		switch ( $mode ) {
			case 'add':
				SpecialCollection::addArticleFromName( NS_MAIN, $param );
				self::unban( $param );
				break;
			case 'ban':
				$session['wsCollectionSuggestBan'][] = $param;
				break;
			case 'remove':
				SpecialCollection::removeArticleFromName( NS_MAIN, $param );
				$session['wsCollectionSuggestBan'][] = $param;
				break;
			case 'removeonly': // remove w/out banning (for undo)
				SpecialCollection::removeArticleFromName( NS_MAIN, $param );
				break;
			case 'unban': // for undo
				self::unban( $param );
				break;
		}

		$template = new CollectionSuggestTemplate();
		$proposals = new Proposals(
			$session['wsCollection'],
			$session['wsCollectionSuggestBan'],
			$session['wsCollectionSuggestProp']
		);

		if ( $mode == 'addAll' ) {
			self::addArticlesFromName( $param, $proposals );
		}

		$template->set( 'collection', $session['wsCollection'] );
		$template->set( 'proposals', $proposals->getProposals( $wgCollectionMaxSuggestions ) );
		$template->set( 'hasbans', $proposals->hasBans() );
		$template->set( 'num_pages', Session::countArticles() );

		$session['wsCollectionSuggestProp'] = $proposals->getLinkList();

		return $template;
	}

	/**
	 * Add some articles and update the book of the Proposal-Object
	 *
	 * @param array $articleList with the names of the articles to be added
	 * @param Proposals $prop
	 */
	private static function addArticlesFromName( $articleList, Proposals $prop ) {
		$session = SessionManager::getGlobalSession();
		foreach ( $articleList as $article ) {
			SpecialCollection::addArticleFromName( NS_MAIN, $article );
		}
		$prop->setCollection( $session['wsCollection'] );
	}
}
