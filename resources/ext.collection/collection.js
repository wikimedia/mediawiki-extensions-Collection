/*
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

/* global wfCollectionSave */
( function ( mw, $ ) {

	var media_path = mw.config.get( 'wgExtensionAssetsPath' ) + '/Collection/images/',
		collapseicon = media_path + '/collapse.png',
		expandicon = media_path + '/expand.png',
		chapter_max_len = 200;

	/**
	 * Return text of element with given selector. Optionally replace %PARAM% with value
	 * of param. This allows usage of localization features in PHP from JavaScript.
	 *
	 * @param {string} id Element ID of element containing text
	 * @param {string} [param] Text to replace %PARAM% with
	 * @return {string} text of element with ID id
	 */
	function gettext( id, param ) {
		var txt = $( id ).html();
		if ( param ) {
			txt = txt.replace( /%PARAM%/g, param );
		}
		return txt;
	}

	/**
	 * Get the book creator box content.
	 */
	function get_book_creator_box_content() {
		var params = {
			action: 'collection',
			submodule: 'getbookcreatorboxcontent',
			hint: 'showbook',
			oldid: null,
			pagename: mw.config.get( 'wgPageName' ),
			format: 'json'
		};

		reqApiModule(
			params,
			function ( result ) {
				$( '#coll-book_creator_box' ).html( result.getbookcreatorboxcontent.html );
			}
		);
	}

	/**
	 * Require API module for processing the request.
	 *
	 * @param {Object} params
	 * @param {Function} callback
	 */
	function reqApiModule( params, callback ) {
		var script_url = mw.util.wikiScript( 'api' );

		$.post( script_url, params, callback, 'json' );
	}

	/**
	 * Clear a user's collection via the UI.
	 *
	 * @return {boolean}
	 */
	function clear_collection() {
		var params = {
			action: 'collection',
			submodule: 'clearcollection',
			format: 'json'
		};

		if ( confirm( gettext( '#clearCollectionConfirmText' ) ) ) {
			reqApiModule( params,
				function ( result ) {
					$( '#titleInput, #subtitleInput' ).val( '' );
					refresh_list( result.clearcollection );
					get_book_creator_box_content();
				}
			);
		}

		return false;
	}

	/**
	 * Create a new chapter in the book.
	 *
	 * @return {boolean}
	 */
	function create_chapter() {
		var name = prompt( gettext( '#newChapterText' ) ),
			params;

		if ( name ) {
			name = name.slice( 0, Math.max( 0, chapter_max_len ) );
			params = {
				action: 'collection',
				submodule: 'addchapter',
				chaptername: name,
				format: 'json'
			};

			reqApiModule( params, function ( result ) {
				refresh_list( result.addchapter );
			} );
			update_buttons();
		}

		return false;
	}

	/**
	 * Rename a chapter in the book of a user's collection.
	 *
	 * @param {number} index Index for renaming
	 * @param {string} old_name Old chapter name
	 * @return {boolean}
	 */
	function rename_chapter( index, old_name ) {
		var new_name = prompt( gettext( '#renameChapterText' ), old_name ),
			params;

		if ( new_name ) {
			new_name = new_name.slice( 0, Math.max( 0, chapter_max_len ) );
			params = {
				action: 'collection',
				submodule: 'renamechapter',
				chaptername: new_name,
				index: index,
				format: 'json'
			};

			reqApiModule( params, function ( result ) {
				refresh_list( result.renamechapter );
			} );
		}

		return false;
	}

	/**
	 * Remove an item from a user's collection index-based.
	 *
	 * @param {number} index The index of the item.
	 * @return {boolean}
	 */
	function remove_item( index ) {
		var params = {
			action: 'collection',
			submodule: 'removeitem',
			index: index,
			format: 'json'
		};

		reqApiModule(
			params,
			function ( result ) {
				refresh_list( result.removeitem );
				get_book_creator_box_content();
			}
		);

		return false;
	}

	/**
	 * Set the title & subtitle of the book in a user's collection.
	 *
	 * @return {boolean}
	 */
	function set_titles() {
		var settings = {}, params;
		$( '[id^="coll-input-setting-"]' ).each( function ( i, e ) {
			if ( $( e ).is( ':checkbox' ) ) {
				settings[ e.name ] = $( e ).is( ':checked' );
			} else {
				settings[ e.name ] = $( e ).val();
			}
		} );

		params = {
			action: 'collection',
			submodule: 'settitles',
			title: $( '#titleInput' ).val(),
			subtitle: $( '#subtitleInput' ).val(),
			settings: JSON.stringify( settings ),
			format: 'json'
		};

		reqApiModule(
			params,
			function ( result ) {
				wfCollectionSave( result.settitles.collection );
			}
		);
		update_buttons();

		return false;
	}

	/**
	 * Sort items in the user's collection.
	 *
	 * @param {string} items_string List of items as text
	 * @return {boolean}
	 */
	function set_sorting( items_string ) {
		var params = {
			action: 'collection',
			submodule: 'setsorting',
			items: items_string,
			format: 'json'
		};

		reqApiModule( params, function ( result ) {
			refresh_list( result.setsorting );
		} );

		return false;
	}

	function update_buttons() {
		if ( $( '#collectionList .article' ).length === 0 ) {
			$( '#saveButton, #downloadButton, input.order' ).prop( 'disabled', true );
			return;
		} else {
			$( 'input.order' ).prop( 'disabled', false );
			$( '#downloadButton' ).prop( 'disabled', mw.config.get( 'wgCollectionDisableDownloadSection' ) );
		}

		if ( !$( '#saveButton' ).length ) {
			return;
		}
		if ( !$( '#communityCollTitle' ).length || $( '#personalCollType:checked' ).val() ) {
			$( '#personalCollTitle' ).prop( 'disabled', false );
			$( '#communityCollTitle' ).prop( 'disabled', true );
			if ( !$.trim( $( '#personalCollTitle' ).val() ) ) {
				$( '#saveButton' ).prop( 'disabled', true );
				return;
			}
		} else if ( !$( '#personalCollTitle' ).length || $( '#communityCollType:checked' ).val() ) {
			$( '#communityCollTitle' ).prop( 'disabled', false );
			$( '#personalCollTitle' ).prop( 'disabled', true );
			if ( !$.trim( $( '#communityCollTitle' ).val() ) ) {
				$( '#saveButton' ).prop( 'disabled', true );
				return;
			}
		}
		$( '#saveButton' ).prop( 'disabled', false );
	}

	function serialize() {
		set_sorting(
			Array.from(
				document.querySelectorAll( '#collectionList li' )
			).map( ( node ) => node.id.split( '-' )[ 1 ] ).join( '|' )
		);
	}

	function upClick( ev ) {
		const cur = ev.target.parentNode;
		const last = cur.previousElementSibling;
		if ( last ) {
			cur.parentNode.insertBefore( cur, last );
			serialize();
		}
	}

	function downClick( ev ) {
		const cur = ev.target.parentNode;
		const next = cur.nextElementSibling;
		if ( next ) {
			cur.parentNode.insertBefore( cur, next.nextElementSibling );
			serialize();
		}
	}

	/**
	 * Puts up and down arrows in each item to allow you to reorder items.
	 *
	 * @param {HTMLElement} list
	 */
	function sortable( list ) {
		list.querySelectorAll( 'li' ).forEach( ( node ) => {
			const up = document.createElement( 'button' );
			up.setAttribute( 'class', 'collection-up' );
			up.textContent = '↑';
			const down = document.createElement( 'button' );
			down.setAttribute( 'class', 'collection-down' );
			down.textContent = '↓';
			up.addEventListener( 'click', upClick );
			down.addEventListener( 'click', downClick );
			node.prepend( down );
			node.prepend( up );
		} );
	}

	function make_sortable() {
		sortable( $( '#collectionList' )[ 0 ] );
	}

	/**
	 * Refresh a user's collection list of items.
	 *
	 * @param {Object} data
	 */
	function refresh_list( data ) {
		wfCollectionSave( data.collection );
		$( '#collectionListContainer' ).html( data.html );
		$( '.makeVisible' ).css( 'display', 'inline' );
		make_sortable();
		update_buttons();
	}

	/**
	 * Set items in a user's collection
	 *
	 * @return {boolean}
	 */
	function sort_items() {
		var params = {
			action: 'collection',
			submodule: 'sortitems',
			format: 'json'
		};

		reqApiModule( params, function ( result ) {
			refresh_list( result.sortitems );
		} );

		return false;
	}

	/**
	 * Prepare the special page commands and attach to the UI elements.
	 */
	$( function () {
		if ( $( '#collectionList' ).length ) {
			$( '.makeVisible' ).css( 'display', 'inline' );
			window.coll_create_chapter = create_chapter;
			window.coll_remove_item = remove_item;
			window.coll_rename_chapter = rename_chapter;
			window.coll_clear_collection = clear_collection;
			window.coll_sort_items = sort_items;

			update_buttons();
			make_sortable();
			$( '#coll-orderbox li.collection-partner.coll-more_info.collapsed' ).css(
				'list-style',
				'url("' + collapseicon + '")'
			);
			$( '#coll-orderbox' ).on(
				'click',
				'li.collection-partner.coll-more_info a.coll-partnerlink',
				function ( event ) {
					event.preventDefault();
					event.stopPropagation();
					var p = $( this ).parents( 'li.collection-partner' );
					if ( p.hasClass( 'collapsed' ) ) {
						p.css( 'list-style', 'url("' + expandicon + '")' );
						p.find( '.coll-order_info' ).css( 'display', 'block' );
						p.removeClass( 'collapsed' );
					} else {
						p.css( 'list-style', 'url("' + collapseicon + '")' );
						p.find( '.coll-order_info' ).css( 'display', 'none' );
						p.addClass( 'collapsed' );
					}
				} );
			$( '#personalCollTitle, #communityCollTitle' )
				.val( $( '#titleInput' ).val() )
				.change( update_buttons );
			$( '#personalCollType, #communityCollType' )
				.change( update_buttons );
			$( '#titleInput, #subtitleInput, [id^="coll-input-setting-"]' )
				.change( set_titles );
		}

		$( '.collection-chapter-create' ).on( 'click', function () {
			create_chapter();
		} );
		$( '.collection-sort' ).on( 'click', function () {
			sort_items();
		} );
		$( '.collection-clear' ).on( 'click', function () {
			clear_collection();
		} );
	} );

}( mediaWiki, jQuery ) );
