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
	 * TODO: Migrate to use API modules once converted.
	 *
	 * @param {Function} func
	 * @param {Object} args
	 */
	function collectionSuggestCall( func, args ) {
		set_status( '...' );
		$.post( mw.util.wikiScript(), {
			action: 'ajax',
			rs: 'CollectionAjaxFunctions::onAjaxCollectionSuggest' + func,
			'rsargs[]': args
		}, function ( result ) {
			wfCollectionSave( result.collection );
			if ( func === 'undo' ) {
				set_status( false );
			} else {
				set_status( result.last_action );
			}
			$( '#collectionSuggestions' ).html( result.suggestions_html );
			$( '#collectionMembers' ).html( result.members_html );
			$( '#coll-num_pages' ).text( result.num_pages );

			var params = {
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
