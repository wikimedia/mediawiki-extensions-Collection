<?php

namespace MediaWiki\Extensions\Collection;

use ApiMain;
use DerivativeRequest;
use LinkBatch;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RequestContext;
use StatusValue;
use Title;
use VirtualRESTServiceClient;

/**
 * Given a set of titles, fetches article content and various metadata like authors.
 */
class DataProvider implements LoggerAwareInterface {

	/** @var VirtualRESTServiceClient */
	protected $client;

	/** @var LoggerAwareInterface */
	protected $logger;

	/**
	 * @param VirtualRESTServiceClient $client RESTBase client.
	 *   RESTBase should be mounted at /restbase/.
	 */
	public function __construct( VirtualRESTServiceClient $client ) {
		$this->client = $client;
		$this->logger = new NullLogger();
	}

	/**
	 * @inheritDoc
	 * @param LoggerInterface $logger
	 */
	public function setLogger( LoggerInterface $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Fetch HTML for the pages in a collection.
	 * @param array[] $collection Collection, as returned by CollectionSession::getCollection().
	 * @return StatusValue A status with a result of array[]: map of prefixed DB key => Parsoid HTML.
	 */
	public function fetchPages( $collection ) {
		$items = array_merge( array_filter( $collection['items'], function ( array $item ) {
			return $item['type'] === 'article';
		} ) );
		$linkBatch = new LinkBatch();
		$titles = array_map( function ( array $item ) use ( $linkBatch ) {
			$title = Title::newFromText( $item['title'] );
			$linkBatch->addObj( $title );
			return $title;
		}, $items );
		$linkBatch->execute();

		$requests = array_map( function ( array $item, Title $title ) {
			$url = '/restbase/local/v1/page/html/' . wfUrlencode( $title->getPrefixedDBkey() );
			if ( isset( $item['revision'] ) ) {
				$url .= '/' . $item['revision'];
			}
			return [
				'method' => 'GET',
				'url' => $url,
				'headers' => [
					'Accept' => 'text/html; charset=utf-8; profile="mediawiki.org/specs/html/1.2.0"',
				],
			];
		}, $items, $titles );

		$responses = $this->client->runMulti( $requests );

		$status = StatusValue::newGood();
		array_map( function ( $req, $resp ) use ( $status ) {
			if ( $resp['error'] !== '' ) {
				// curl error. Logging will happen in MultiHttpClient.
				$status->fatal( 'coll-rendererror-fetch', $req['url'], $resp['error'] );
			} elseif ( $resp['code'] !== 200 ) {
				// HTTP error, probably a RESTBase error (with more info in the JSON response).
				$errorMsg = $resp['code'];
				if ( $resp['reason'] !== '' ) {
					$errorMsg .= ' ' . $resp['reason'];
				}
				$error = json_decode( $resp['body'], true );
				if ( $error ) {
					$this->logger->warning( 'Could not fetch {url}: {error}',
						[ 'url' => $req['url'], 'error' => $errorMsg, 'errorDetails' => $error ] );
					$errorMsg = $errorMsg . ' - ' . $resp['body'];
				} else {
					$this->logger->warning( 'Could not fetch {url}: {error}',
						[ 'url' => $req['url'], 'error' => $errorMsg ] );
				}
				$status->fatal( 'coll-rendererror-fetch', $req['url'], $errorMsg );
			}
		}, $requests, $responses );
		if ( $status->isOK() ) {
			$status->setResult( true, array_combine(
				array_map( function ( Title $title ) {
					return $title->getPrefixedDBkey();
				}, $titles ),
				array_map( function ( $item ) {
					return $item['body'];
				}, $responses )
			) );
		}
		return $status;
	}

	/**
	 * Fetch metadata (sections, contributors and RL modules) for the pages in a collection.
	 * @param string[] $dbkeys DB keys of the articles contained in the book.
	 * @return StatusValue A status with a result array of:
	 *   - displaytitle: [ dbkey => title, ... ]
	 *   - sections: [ dbkey => [ [ title => ..., id => ..., level => ... ], ... ], ... ]
	 *   - contributors: [ name => userid, ... ]
	 *   - modules: [ module, ... ]
	 *   - modulescripts: [ module, ... ]
	 *   - modulestyles: [ module, ... ]
	 *   - jsconfigvars: [ var, ... ]
	 */
	public function fetchMetadata( $dbkeys ) {
		$metadata = [
			'displaytitle' => [],
			'sections' => [],
			'contributors' => [],
			'modules' => [],
			'modulescripts' => [],
			'modulestyles' => [],
			'jsconfigvars' => [],
		];

		// get contributors
		$params = [
			'format' => 'json',
			'action' => 'query',
			'prop' => 'contributors',
			'redirects' => 1,
			'pclimit' => 'max', // 500; more titles than that will probably blow up Electron anyway
			'titles' => implode( '|', $dbkeys ),
		];
		do {
			$data = $this->makeActionApiRequest( $params );
			$continue = isset( $data['continue'] ) ? $data['continue'] : [];
			$params = $continue + $params;
			foreach ( $data['query']['pages'] as $page ) {
				foreach ( $page['contributors'] as $key => $contrib ) {
					$metadata['contributors'][$contrib['name']] = $contrib['userid'];
				}
			}
		} while ( $continue );

		// get sections & modules
		foreach ( $dbkeys as $dbkey ) {
			$data = $this->makeActionApiRequest( [
				'format' => 'json',
				'action' => 'parse',
				'prop' => 'sections|displaytitle|modules|jsconfigvars',
				'page' => $dbkey,
			] );
			$metadata['displaytitle'][$dbkey] = $data['parse']['displaytitle'];
			$metadata['sections'][$dbkey] = array_map( function ( $sectionData ) {
				return [
					'title' => $sectionData['line'],
					'id' => $sectionData['anchor'],
					'level' => intval( $sectionData['level'] ),
				];
			}, $data['parse']['sections'] );
			foreach ( [ 'modules', 'modulescripts', 'modulestyles', 'jsconfigvars' ] as $field ) {
				// let's hope there is no conflict in jsconfigvars...
				$metadata[$field] = array_merge( $metadata[$field], $data['parse'][$field] );
			}
		}

		return StatusValue::newGood( $metadata );
	}

	/**
	 * Make a request to the local action API.
	 * @param array $params API parameters
	 * @return array
	 */
	protected function makeActionApiRequest( $params ) {
		$request = RequestContext::getMain()->getRequest();
		$api = new ApiMain( new DerivativeRequest( $request, $params ) );
		$api->execute();
		return $api->getResult()->getResultData( [], [ 'Strip' => 'all' ] );
	}

}
