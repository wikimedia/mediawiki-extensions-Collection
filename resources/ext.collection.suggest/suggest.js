/* global wfCollectionSave */
( function ( mw, $ ) {

	var script_url = mw.util.wikiScript( 'api' );

	function set_status( html ) {
		if ( html ) {
			$( '#collectionSuggestStatus' )
				.css( 'visibility', 'visible' )
				.html( html );
		} else {
			$( '#collectionSuggestStatus' )
				.css( 'visibility', 'hidden' )
				.html( '&nbsp;' );
		}
	}

	/**
	 * @param {Function} func
	 * @param {Object} args
	 */
	function collectionSuggestCall( func, args ) {
		var params;
		if ( func === 'AddArticle' ) {
			params = {
				action: 'collection',
				submodule: 'suggestarticleaction',
				suggestaction: 'add',
				title: args.title,
				format: 'json'
			};
		} else if ( func === 'RemoveArticle' ) {
			params = {
				action: 'collection',
				submodule: 'suggestarticleaction',
				suggestaction: 'remove',
				title: args.title,
				format: 'json'
			};
		} else if ( func === 'BanArticle' ) {
			params = {
				action: 'collection',
				submodule: 'suggestarticleaction',
				suggestaction: 'ban',
				title: args.title,
				format: 'json'
			};
		} else {
			// Last case is the Undo case
			params = {
				action: 'collection',
				submodule: 'suggestarticleaction',
				suggestaction: 'undo',
				title: args.title,
				format: 'json'
			};
		}
		set_status( '...' );
		$.post( script_url, params, function ( result ) {
			wfCollectionSave( result.suggestarticleaction.collection );
			if ( func === 'undo' ) {
				set_status( false );
			} else {
				set_status( result.last_action );
			}
			$( '#collectionSuggestions' ).html( result.suggestions_html );
			$( '#collectionMembers' ).html( result.members_html );
			$( '#coll-num_pages' ).text( result.num_pages );

			params = {
				action: 'collection',
				submodule: 'getbookcreatorboxcontent',
				hint: 'suggest',
				oldid: 0,
				pagename: mw.config.get( 'wgPageName' ),
				format: 'json'
			};

			$.getJSON( script_url, params, function ( boxCreatorResult ) {
				$( '#coll-book_creator_box' ).html( boxCreatorResult.getbookcreatorboxcontent.html );
			} );
		}, 'json' );
	}

	window.collectionSuggestCall = collectionSuggestCall;

}( mediaWiki, jQuery ) );
