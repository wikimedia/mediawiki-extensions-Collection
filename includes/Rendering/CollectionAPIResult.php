<?php

namespace MediaWiki\Extension\Collection\Rendering;

use MediaWiki\Json\FormatJson;

/**
 * A wrapper for data returned by the API
 */
class CollectionAPIResult {
	/** @var array|null Decoded JSON returned by server */
	public $response = [];

	/**
	 * @param string|null $data Data returned by HTTP request
	 */
	public function __construct( $data ) {
		if ( $data ) {
			$this->response = FormatJson::decode( $data, true );
			if ( $this->response === null ) {
				wfDebugLog( 'collection', "Server returned bogus data: $data" );
			}
			if ( $this->isError() ) {
				wfDebugLog( 'collection', "Server returned error: {$this->getError()}" );
			}
		}
	}

	/**
	 * Returns data for specified key(s)
	 * @param string $key
	 * @param string ...$keys
	 * @return mixed
	 */
	public function get( $key, ...$keys ) {
		$val = $this->response[$key] ?? '';
		foreach ( $keys as $subkey ) {
			if ( !is_array( $val ) ) {
				return '';
			}
			$val = $val[$subkey] ?? '';
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
		return $this->response['error'] ?? '(error unknown)';
	}
}
