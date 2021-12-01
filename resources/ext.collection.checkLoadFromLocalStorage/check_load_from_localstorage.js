( function ( mw, $ ) {

	var script_url = mw.util.wikiScript( 'api' );
	$( function () {
		var c = mw.storage.get( 'collection' ),
			num_pages = 0,
			shownTitle = '',
			message,
			params;
		if ( c ) {
			for ( var i = 0; i < c.items.length; i++ ) {
				if ( c.items[ i ].type === 'article' ) {
					num_pages++;
				}
			}
			if ( c.title ) {
				shownTitle = '("' + c.title + '")';
			}
			if ( num_pages ) {
				message = mw.msg( 'coll-load_local_book', shownTitle, num_pages );
				if ( confirm( message ) ) {
					params = {
						action: 'collection',
						submodule: 'postcollection',
						collection: [ JSON.stringify( c ) ],
						format: 'json'
					};
					$.post( script_url, params, function ( result ) {
						location.href = result.postcollection.redirect_url;
					}, 'json' );

				}
			}
		}
	} );

}( mediaWiki, jQuery ) );
