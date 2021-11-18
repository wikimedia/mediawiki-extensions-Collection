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

( function ( mw, $ ) {

	$( function () {

		var script_url = mw.util.wikiScript( 'api' );

		/**
		 * Save a user's collection to their browser's local storage.
		 *
		 * @param {Object} collection
		 */
		function save_collection( collection ) {
			mw.storage.set( 'collection', collection );
		}

		window.wfCollectionSave = save_collection;

		/**
		 * Refresh the collection's book creator box.
		 *
		 * @param {string} hint
		 * @param {number} oldid
		 */
		function refreshBookCreatorBox( hint, oldid ) {
			var params = {
				action: 'collection',
				submodule: 'getbookcreatorboxcontent',
				hint: hint,
				oldid: oldid ? parseInt( oldid ) : 0,
				pagename: mw.config.get( 'wgPageName' ),
				format: 'json'
			};

			$.getJSON(
				script_url,
				params,
				function ( result ) {
					$( '#coll-book_creator_box' ).html( result.getbookcreatorboxcontent.html );
				}
			);
		}

		/**
		 * Method used to add and remove article to user's collection,
		 * and also add a category to a user's collection.
		 *
		 * @param {string} submodule Can either be "addarticle", "removearticle"
		 *   or "addcategory".
		 * @param {number} namespace
		 * @param {string} title
		 * @param {number} oldId
		 */
		function collectionCall( submodule, namespace, title, oldId ) {
			var params = {
				action: 'collection',
				submodule: submodule,
				namespace: namespace,
				title: title,
				oldid: oldId,
				format: 'json'
			};

			var hint = '';
			if ( submodule === 'addarticle' ) {
				hint = 'removearticle';
			} else if ( submodule === 'removearticle' ) {
				hint = 'addarticle';
			} else {
				hint = 'addcategory';
			}

			$.post(
				script_url,
				params,
				function ( result ) {
					var oldid = null;
					if ( oldId ) {
						oldid = oldId;
					}
					refreshBookCreatorBox( hint, oldid );
					if ( result.addarticle ) {
						save_collection( result.addarticle.collection );
					} else if ( result.removearticle ) {
						save_collection( result.removearticle.collection );
					} else {
						save_collection( result.addcategory.collection );
					}
				}, 'json' );
		}

		window.collectionCall = collectionCall; // public

		var mouse_pos = {},
			$popup_div = null,
			$addremove_link = null,
			visible = false,
			show_soon_timeout = null,
			get_data_xhr = null,
			current_link = null,
			title = null;

		function createDiv() {
			$addremove_link = $( '<a href="javascript:void(0)" />' );
			$popup_div = $( '<div id="collectionpopup" />' );
			$popup_div.append( $addremove_link );
			$( 'body' ).append( $popup_div );
			$popup_div.hide();
		}

		/**
		 * Add or Remove article from user's collection.
		 *
		 * @param {string} action "add" or "remove" actions
		 * @param {string} title
		 */
		function addremove_article( action, title ) {
			/* eslint no-shadow: 0 */
			var params, submodule;

			if ( action === 'add' ) {
				submodule = 'addarticle';
			} else {
				submodule = 'removearticle';
			}

			params = {
				action: 'collection',
				submodule: submodule,
				namespace: 0,
				title: title,
				oldid: 0,
				format: 'json'
			};
			$.post( script_url, params, function ( result ) {
				hide();
				refreshBookCreatorBox( null, null );
				if ( result.addarticle ) {
					save_collection( result.addarticle.collection );
				} else {
					save_collection( result.removearticle.collection );
				}
			}, 'json' );
		}

		function show( link ) {
			if ( visible ) {
				return;
			}
			current_link = link;
			title = link.attr( 'title' );
			if ( !title ) {
				return;
			}
			// Disable default browser tooltip
			link.attr( 'title', '' );
			show_soon_timeout = setTimeout( function () {
				var params = {
					action: 'collection',
					submodule: 'getpopupdata',
					title: title,
					format: 'json'
				};
				get_data_xhr = $.post( script_url, params, function ( result ) {
					visible = true;
					var img = $( '<img>' ).attr( {
						src: result.getpopupdata.img,
						alt: ''
					} );
					$addremove_link
						.text( '\u00a0' + result.getpopupdata.text )
						.prepend( img )
						.unbind( 'click' )
						.click( function () {
							addremove_article( result.getpopupdata.action, result.getpopupdata.title );
						} );
					$popup_div
						.css( {
							left: mouse_pos.x + 2 + 'px',
							top: mouse_pos.y + 2 + 'px'
						} )
						.show();
				}, 'json' );
			}, 300 );
		}

		function cancel() {
			if ( current_link && title ) {
				current_link.attr( 'title', title );
			}
			if ( show_soon_timeout ) {
				clearTimeout( show_soon_timeout );
				show_soon_timeout = null;
			}
			if ( get_data_xhr ) {
				get_data_xhr.abort();
				get_data_xhr = null;
			}
		}

		function hide() {
			cancel();
			if ( !visible ) {
				return;
			}
			visible = false;
			$popup_div.hide();
		}

		function is_inside( x, y, left, top, width, height ) {
			var fuzz = 5;
			return x + fuzz >= left && x - fuzz <= left + width &&
			y + fuzz >= top && y - fuzz <= top + height;
		}

		function check_popup_hide() {
			if ( !visible ) {
				return;
			}
			var pos = $popup_div.offset();
			if ( !is_inside(
				mouse_pos.x,
				mouse_pos.y,
				pos.left,
				pos.top,
				$popup_div.width(),
				$popup_div.height()
			) ) {
				hide();
			}
		}

		$( document ).mousemove( function ( e ) {
			mouse_pos.x = e.pageX;
			mouse_pos.y = e.pageY;
		} );
		setInterval( check_popup_hide, 300 );
		createDiv();
		var prefix = mw.config.get( 'wgArticlePath' ).replace( /\$1/, '' );
		$( '#bodyContent ' +
		'a[href^="' + prefix + '"]' + // URL starts with prefix of wgArticlePath
		':not(a[href~="index.php"])' + // URL doesn't contain index.php (simplification!)
		'[title!=""]' + // title attribute is not empty
		'[rel!=nofollow]' +
		':not(.external)' +
		':not(.internal)' +
		':not(.sortheader)' +
		':not([accesskey])' +
		':not(.nopopup)'
		).each( function () {
			if ( this.onmousedown ) {
				return;
			}
			var $this = $( this );
			// title doesn't contain ":" (simplification!)
			if ( !$this.attr( 'title' ) || $this.attr( 'title' ).indexOf( ':' ) !== -1 ) {
				return;
			}
			if ( $this.parents( '.nopopups' ).length ) {
				return;
			}
			$this.hover(
				function () {
					show( $this );
				},
				cancel
			);
		} );
	} );

}( mediaWiki, jQuery ) );
