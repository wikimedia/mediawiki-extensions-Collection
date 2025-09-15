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

use MediaWiki\MediaWikiServices;
use MediaWiki\Session\SessionManager;
use MediaWiki\Title\Title;

class Session {
	/**
	 * @return bool
	 */
	public static function hasSession() {
		$session = SessionManager::getGlobalSession();
		return isset( $session['wsCollection'] );
	}

	public static function startSession() {
		$session = SessionManager::getGlobalSession();
		$session->persist();

		self::clearCollection();
	}

	public static function touchSession() {
		$session = SessionManager::getGlobalSession();
		$collection = $session['wsCollection'];
		$collection['timestamp'] = wfTimestampNow();
		$session['wsCollection'] = $collection;
	}

	public static function clearCollection() {
		$session = SessionManager::getGlobalSession();
		$session['wsCollection'] = [
			'enabled' => true,
			'title' => '',
			'subtitle' => '',
			'settings' => [],
			'items' => [],
		];
		Suggest::clear();
		self::touchSession();
	}

	public static function enable() {
		$session = SessionManager::getGlobalSession();
		$session->persist();

		$session['wsCollection']['enabled'] = true;
		self::touchSession();
	}

	public static function disable() {
		$session = SessionManager::getGlobalSession();

		if ( !isset( $session['wsCollection'] ) ) {
			return;
		}
		self::clearCollection();
		$session['wsCollection']['enabled'] = false;
		self::touchSession();
	}

	/**
	 * @return bool
	 */
	public static function isEnabled() {
		$session = SessionManager::getGlobalSession();

		return isset( $session['wsCollection'] ) &&
			isset( $session['wsCollection']['enabled'] ) &&
			$session['wsCollection']['enabled'];
	}

	/**
	 * @return bool
	 */
	public static function hasItems() {
		$session = SessionManager::getGlobalSession();

		return isset( $session['wsCollection'] ) &&
			isset( $session['wsCollection']['items'] );
	}

	/**
	 * @return int
	 */
	public static function countArticles() {
		if ( !self::hasItems() ) {
			return 0;
		}
		$session = SessionManager::getGlobalSession();
		$count = 0;
		foreach ( $session['wsCollection']['items'] as $item ) {
			if ( $item && $item['type'] == 'article' ) {
				$count++;
			}
		}
		return $count;
	}

	/**
	 * @param string $title
	 * @param int $oldid
	 * @return int
	 */
	public static function findArticle( $title, $oldid = 0 ) {
		if ( !self::hasItems() ) {
			return -1;
		}

		// FIXME: Some places use DB keys, other use prefixedtext, and this can lead to mismatches.
		// This class should just take Title (or a narrower interface) and be responsible for the stringification!
		$titleObj = Title::newFromText( $title );
		$titleStr = $titleObj ? $titleObj->getPrefixedDBkey() : $title;
		$session = SessionManager::getGlobalSession();

		foreach ( $session['wsCollection']['items'] as $index => $item ) {
			if ( !$item || $item['type'] !== 'article' ) {
				continue;
			}
			$curTitleObj = Title::newFromText( $item['title'] );
			$curTitleStr = $curTitleObj ? $curTitleObj->getPrefixedDBkey() : $item['title'];
			if ( $curTitleStr === $titleStr ) {
				if ( $oldid ) {
					if ( $item['revision'] == strval( $oldid ) ) {
						return $index;
					}
				} else {
					if ( $item['revision'] == $item['latest'] ) {
						return $index;
					}
				}
			}
		}
		return -1;
	}

	/**
	 * @return bool
	 */
	public static function purge() {
		$session = SessionManager::getGlobalSession();

		if ( !isset( $session['wsCollection'] ) ) {
			return false;
		}

		$coll = $session['wsCollection'];
		$coll['items'] ??= [];
		// Remove broken entries caused by old, bogus code paths
		$coll['items'] = array_filter( $coll['items'] );

		// Checking if articles exist is expensive, use a single batch to speed it up
		$services = MediaWikiServices::getInstance();
		$linkBatch = $services->getLinkBatchFactory()->newLinkBatch();
		$linkCache = $services->getLinkCache();
		foreach ( $coll['items'] as $item ) {
			if ( $item['type'] === 'article' ) {
				$title = Title::newFromText( $item['title'] );
				if ( $title ) {
					$linkBatch->addObj( $title );
				}
			}
		}
		$linkBatch->execute();

		$newitems = [];
		foreach ( $coll['items'] as $item ) {
			if ( $item['type'] === 'article' ) {
				$title = Title::newFromText( $item['title'] );
				// Remove articles that don't exist any more
				if ( !$title || $linkCache->isBadLink( $title->getPrefixedDBkey() ) ) {
					continue;
				}
			}
			$newitems[] = $item;
		}
		$coll['items'] = $newitems;
		$session['wsCollection'] = $coll;
		return true;
	}

	/**
	 * @return array
	 */
	public static function getCollection() {
		$session = SessionManager::getGlobalSession();
		$collection = self::purge() ? $session['wsCollection'] : [];
		return array_merge( [
			/* Make sure required properties are present.  */
			'title' => '',
			'subtitle' => '',
			'settings' => [],
			'items' => [],
		], $collection );
	}

	/**
	 * @param array $collection
	 */
	public static function setCollection( array $collection ) {
		$session = SessionManager::getGlobalSession();
		$session['wsCollection'] = $collection;
		self::touchSession();
	}
}
