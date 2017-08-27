<?php

/**
 * Base class for API that interacts with book rendering service
 */
abstract class CollectionRenderingAPI {
	/** @var CollectionRenderingAPI */
	private static $inst;

	/** @var string */
	protected $writer;

	public static function instance( $writer = false ) {
		if ( !self::$inst ) {
			self::$inst = new MWServeRenderingAPI( $writer );
		}
		return self::$inst;
	}

	/**
	 * @param string|bool $writer Writer or false if none specified/needed
	 */
	public function __construct( $writer ) {
		$this->writer = $writer;
	}

	/**
	 * When overridden in derived class, performs a request to the service
	 *
	 * @param string $command
	 * @param array $params
	 * @return CollectionAPIResult
	 */
	abstract protected function makeRequest( $command, array $params );

	/**
	 * @return String expanded $wgScriptPath to work around T39868
	 * @private
	 */
	function getBaseURL() {
		global $wgScriptPath;
		$scriptPath = $wgScriptPath ? $wgScriptPath : "/";
		return wfExpandUrl( $scriptPath, PROTO_CANONICAL );
	}

	/**
	 * Requests a collection to be rendered
	 * @param array $collection
	 *
	 * @return CollectionAPIResult
	 */
	public function render( $collection ) {
		return $this->doRender( [
				'metabook' => $this->buildJSONCollection( $collection ),
			]
		);
	}

	/**
	 * Requests a queued collection to be immediately rendered
	 *
	 * @param int $collectionId
	 * @return CollectionAPIResult
	 */
	public function forceRender( $collectionId ) {
		return $this->doRender( [
				'collection_id' => $collectionId,
				'force_render' => true
			]
		);
	}

	protected function doRender( array $params ) {
		global $wgContLang;

		$params['base_url'] = $this->getBaseURL();
		$params['script_extension'] = '.php';
		$params['language'] = $wgContLang->getCode();
		return $this->makeRequest( 'render', $params );
	}

	/**
	 * Requests the service to create a collection package and send it to an external server
	 * e.g. for printing
	 *
	 * @param array $collection
	 * @param string $url
	 *
	 * @return CollectionAPIResult
	 */
	public function postZip( $collection, $url ) {
		return $this->makeRequest( 'zip_post',
			[
				'metabook' => $this->buildJSONCollection( $collection ),
				'base_url' => $this->getBaseURL(),
				'script_extension' => '.php',
				'pod_api_url' => $url,
			]
		);
	}

	/**
	 * Returns information about a collection's rendering status
	 *
	 * @param int $collectionId
	 * @return CollectionAPIResult
	 */
	public function getRenderStatus( $collectionId ) {
		return $this->makeRequest(
			'render_status',
			[
				'collection_id' => $collectionId,
			]
		);
	}

	/**
	 * Requests a download of rendered collection
	 *
	 * @param int $collectionId
	 * @return CollectionAPIResult
	 */
	public function download( $collectionId ) {
		return $this->makeRequest( 'download',
			[
				'collection_id' => $collectionId,
			]
		);
	}

	/**
	 * @return array
	 */
	protected function getLicenseInfos() {
		global $wgCollectionLicenseName, $wgCollectionLicenseURL, $wgRightsIcon;
		global $wgRightsPage, $wgRightsText, $wgRightsUrl;

		$licenseInfo = [
			'type' => 'license',
		];

		$fromMsg = wfMessage( 'coll-license_url' )->inContentLanguage();
		if ( !$fromMsg->isDisabled() ) {
			$licenseInfo['mw_license_url'] = $fromMsg->text();
			return [ $licenseInfo ];
		}

		if ( $wgCollectionLicenseName ) {
			$licenseInfo['name'] = $wgCollectionLicenseName;
		} else {
			$licenseInfo['name'] = wfMessage( 'coll-license' )->inContentLanguage()->text();
		}

		if ( $wgCollectionLicenseURL ) {
			$licenseInfo['mw_license_url'] = $wgCollectionLicenseURL;
		} else {
			$licenseInfo['mw_rights_icon'] = $wgRightsIcon;
			$licenseInfo['mw_rights_page'] = $wgRightsPage;
			$licenseInfo['mw_rights_url'] = $wgRightsUrl;
			$licenseInfo['mw_rights_text'] = $wgRightsText;
		}

		return [ $licenseInfo ];
	}

	/**
	 * @param array $collection
	 * @return string
	 */
	protected function buildJSONCollection( $collection ) {
		$result = [
			'type' => 'collection',
			'licenses' => $this->getLicenseInfos()
		];

		if ( isset( $collection['title'] ) ) {
			$result['title'] = $collection['title'];
		}
		if ( isset( $collection['subtitle'] ) ) {
			$result['subtitle'] = $collection['subtitle'];
		}
		if ( isset( $collection['settings'] ) ) {
			foreach ( $collection['settings'] as $key => $val ) {
				$result[$key] = $val;
			}
			// compatibility with old mw-serve
			$result['settings'] = $collection['settings'];
		}

		$items = [];
		if ( isset( $collection['items'] ) ) {
			$currentChapter = null;
			foreach ( $collection['items'] as $item ) {
				if ( $item['type'] == 'article' ) {
					if ( is_null( $currentChapter ) ) {
						$items[] = $item;
					} else {
						$currentChapter['items'][] = $item;
					}
				} elseif ( $item['type'] == 'chapter' ) {
					if ( !is_null( $currentChapter ) ) {
						$items[] = $currentChapter;
					}
					$currentChapter = $item;
				}
			}
			if ( !is_null( $currentChapter ) ) {
				$items[] = $currentChapter;
			}
		}
		$result['items'] = $items;

		$result['wikis'] = [
			[
				'type' => 'wikiconf',
				'baseurl' => $this->getBaseURL(),
				'script_extension' => '.php',
				'format' => 'nuwiki',
			],
		];

		// Prefer VRS configuration if present.
		$context = RequestContext::getMain();
		$vrs = $context->getConfig()->get( 'VirtualRestConfig' );
		if ( isset( $vrs['modules'] ) && isset( $vrs['modules']['restbase'] ) ) {
			// if restbase is available, use it
			$params = $vrs['modules']['restbase'];
			$domain = preg_replace(
				'/^(https?:\/\/)?([^\/:]+?)(\/|:\d+\/?)?$/',
				'$2',
				$params['domain']
			);
			$url = preg_replace(
				'#/?$#',
				'/' . $domain . '/v1/',
				$params['url']
			);
			for ( $i = 0, $count = count( $result['wikis'] ); $i < $count; $i++ ) {
				$result['wikis'][$i]['restbase1'] = $url;
			}
		} elseif ( isset( $vrs['modules'] ) && isset( $vrs['modules']['parsoid'] ) ) {
			// there's a global parsoid config, use it next
			$params = $vrs['modules']['parsoid'];
			$domain = preg_replace(
				'/^(https?:\/\/)?([^\/:]+?)(\/|:\d+\/?)?$/',
				'$2',
				$params['domain']
			);
			for ( $i = 0, $count = count( $result['wikis'] ); $i < $count; $i++ ) {
				$result['wikis'][$i]['parsoid'] = $params['url'];
				$result['wikis'][$i]['prefix'] = $params['prefix'];
				$result['wikis'][$i]['domain'] = $domain;
			}
		} elseif ( class_exists( 'VisualEditorHooks' ) ) {
			// fall back to Visual Editor configuration globals
			global $wgVisualEditorParsoidURL, $wgVisualEditorParsoidPrefix,
				$wgVisualEditorParsoidDomain, $wgVisualEditorRestbaseURL;
			for ( $i = 0, $count = count( $result['wikis'] ); $i < $count; $i++ ) {
				// Parsoid connection information
				if ( $wgVisualEditorParsoidURL ) {
					$result['wikis'][$i]['parsoid'] = $wgVisualEditorParsoidURL;
					$result['wikis'][$i]['prefix'] = $wgVisualEditorParsoidPrefix;
					$result['wikis'][$i]['domain'] = $wgVisualEditorParsoidDomain;
				}
				// RESTbase connection information
				if ( $wgVisualEditorRestbaseURL ) {
					// Strip the trailing "/page/html".
					$restbase1 = preg_replace( '|/page/html/?$|', '/', $wgVisualEditorRestbaseURL );
					$result['wikis'][$i]['restbase1'] = $restbase1;
				}
			}
		}

		return FormatJson::encode( $result );
	}
}

/**
 * API for PediaPress' mw-serve
 */
class MWServeRenderingAPI extends CollectionRenderingAPI {
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
		list( $proxy, $serveURL ) = array_pad( explode( '|', $serveURL, 2 ), -2, '' );

		$response = Http::post(
			$serveURL,
			[ 'postData' => $params, 'proxy' => $proxy ],
			__METHOD__
		);
		if ( $response === false ) {
			wfDebugLog( 'collection', "Request to $serveURL resulted in error" );
		}
		return new CollectionAPIResult( $response );
	}
}

/**
 * A wrapper for data returned by the API
 */
class CollectionAPIResult {
	/** @var array: Decoded JSON returned by server */
	public $response = [];

	/**
	 * @param string|null $data Data returned by HTTP request
	 */
	public function __construct( $data ) {
		if ( $data ) {
			$this->response = FormatJson::decode( $data, true );
			if ( $this->response === null ) {
				wfDebugLog( 'collection', "Server returned bogus data: $data" );
				$this->response = null;
			}
			if ( $this->isError() ) {
				wfDebugLog( 'collection', "Server returned error: {$this->getError()}" );
			}
		}
	}

	/**
	 * Returns data for specified key(s)
	 * Has variable number of parameters, e.g. get( 'foo', 'bar', 'baz' )
	 * @param string $key
	 * @return mixed
	 */
	public function get( $key /*, ... */ ) {
		$args = func_get_args();
		$val = $this->response;
		foreach ( $args as $arg ) {
			if ( !isset( $val[$arg] ) ) {
				return '';
			}
			$val = $val[$arg];
		}
		return $val;
	}

	/**
	 * @return bool
	 */
	public function isError() {
		return !$this->response
			|| ( isset( $this->response['error'] ) && $this->response['error'] );
	}

	/**
	 * @return string Internal (not user-facing) error description
	 */
	protected function getError() {
		if ( isset( $this->response['error'] ) ) {
			return $this->response['error'];
		}
		return '(error unknown)';
	}
}
