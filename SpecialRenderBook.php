<?php

use MediaWiki\Extensions\Collection\BookRenderer;
use MediaWiki\Extensions\Collection\BookRenderingMediator;
use MediaWiki\Extensions\Collection\DataProvider;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;

/**
 * Special page to display a book as a single HTML page.
 */
class SpecialRenderBook extends SpecialPage {

	public function __construct() {
		parent::__construct( 'RenderBook' );
		$this->setListed( false );
	}

	public function execute( $subPage ) {
		$key = null;
		if ( strpos( $subPage, '/' ) !== false ) {
			list( $subPage, $key ) = explode( '/', $subPage, 2 );
		}

		$services = MediaWikiServices::getInstance();
		$restClientLogger = LoggerFactory::getInstance( 'http' );
		$restClient = BookRenderingMediator::getRestServiceClient(
			$this->getConfig(), $restClientLogger );
		$templateParser = new TemplateParser( __DIR__ . '/templates' );
		$templateParser->enableRecursivePartials( true );
		$mediator = new BookRenderingMediator( $services->getMainWANObjectCache(),
			$restClient, $templateParser );
		$mediator->setLogger( LoggerFactory::getInstance( 'collection' ) );

		switch ( $subPage ) {
			case 'clear':
				$services->getMainWANObjectCache()->delete( $key );
				$this->getOutput()->redirect( $this->getPageTitle( 'test' )->getFullURL() );
				return;
			case 'raw':
			case 'skinned':
				$book = $mediator->getBookByCacheKey( $key );
				if ( !$book ) {
					throw new ErrorPageError( 'coll-rendererror-title', 'coll-rendererror-no-cache' );
				}
				$mediator->outputBook( $book, $this->getOutput(), $subPage === 'raw' );
				return;

			case 'electron':
				$bookUrl = $this->getPageTitle( "skinned/$key" )->getFullURL();
				$mediator->outputPdf( $bookUrl, $this->getOutput() );
				return;

			case 'test':
				$key = $mediator->getBookFromCache( $this->getCollection() )['key'];
				$options = [
					'clear' => 'Clear cache',
					'raw' => 'HTML, raw',
					'skinned' => 'HTML, as wiki page',
					'electron' => 'PDF',
				];
				$html = Html::openElement( 'ul', [] );
				foreach ( $options as $mode => $description ) {
					$linkUrl = $this->getPageTitle( "$mode/$key" )->getFullURL();
					$link = Html::element( 'a', [ 'href' => $linkUrl ], $description );
					$html .= Html::rawElement( 'li', [], $link );
				}
				$html .= Html::closeElement( 'ul' );
				$this->getOutput()->addHTML( $html );
				return;

			default:
				// Should not be linked from anywhere, but let's do something more useful than
				// showing an empty page, just in case.
				$this->getOutput()->redirect( SpecialPage::getTitleFor( 'Book' )->getFullURL() );
				return;
		}
	}

	/**
	 * Returns the current collection.
	 * @return array[] Collection, as returned by CollectionSession::getCollection().
	 * @throws ErrorPageError When there is no active connection.
	 */
	private function getCollection() {
		if ( !CollectionSession::hasSession() ) {
			CollectionSession::startSession();
		}
		$collection = CollectionSession::getCollection();
		if ( !$collection || !$collection['enabled'] || !$collection['items'] ) {
			throw new ErrorPageError( 'coll-rendererror-title', 'coll-rendererror-no-session' );
		}
		return $collection;
	}

}
