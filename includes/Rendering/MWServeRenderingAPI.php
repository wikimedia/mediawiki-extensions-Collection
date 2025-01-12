<?php

namespace MediaWiki\Extension\Collection\Rendering;

use MediaWiki\MediaWikiServices;

/**
 * API for PediaPress' mw-serve
 */
class MWServeRenderingAPI extends CollectionRenderingAPI {
	/** @inheritDoc */
	protected function makeRequest( $command, array $params ) {
		global $wgCollectionMWServeURL, $wgCollectionMWServeCredentials,
			$wgCollectionFormatToServeURL, $wgCollectionCommandToServeURL;

		$serveURL = $wgCollectionMWServeURL;
		if ( $this->writer ) {
			if ( isset( $wgCollectionFormatToServeURL[ $this->writer ] ) ) {
				$serveURL = $wgCollectionFormatToServeURL[ $this->writer ];
			}
			$params['writer'] = $this->writer;
		}

		$params['command'] = $command;
		if ( isset( $wgCollectionCommandToServeURL[ $command ] ) ) {
			$serveURL = $wgCollectionCommandToServeURL[ $command ];
		}
		if ( $wgCollectionMWServeCredentials ) {
			$params['login_credentials'] = $wgCollectionMWServeCredentials;
		}
		// If $serveURL has a | in it, we need to use a proxy.
		[ $proxy, $serveURL ] = array_pad( explode( '|', $serveURL, 2 ), -2, '' );

		if ( !$serveURL ) {
			wfDebugLog( 'collection', 'The mwlib/OCG render server URL isn\'t configured.' );

			return new CollectionAPIResult( null );
		}

		$response = MediaWikiServices::getInstance()->getHttpRequestFactory()->post(
			$serveURL,
			[ 'postData' => $params, 'proxy' => $proxy ],
			__METHOD__
		);

		if ( $response === null ) {
			wfDebugLog( 'collection', "Request to $serveURL resulted in error" );
		}

		return new CollectionAPIResult( $response );
	}
}
