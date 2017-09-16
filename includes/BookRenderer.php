<?php

namespace MediaWiki\Extensions\Collection;

use Html;
use LogicException;
use Sanitizer;
use TemplateParser;
use Title;

/**
 * Renders HTML view of a book by concatenating and transforming HTML and generating some
 * leading/trailing pages.
 */
class BookRenderer {

	/** @var TemplateParser */
	private $templateParser;

	/**
	 * @param TemplateParser $templateParser
	 */
	public function __construct( TemplateParser $templateParser ) {
		$this->templateParser = $templateParser;
	}

	/**
	 * Generate the concatenated page.
	 * @param array[] $collection Collection, as returned by CollectionSession::getCollection().
	 * @param string[] $pages Map of prefixed DB key => Parsoid HTML.
	 * @param array[] &$metadata Map of prefixed DB key => metadata, as returned by fetchMetadata().
	 *   Section data will be updated to account for heading level and id changes.
	 *   Also, an outline will be added (see renderCoverAndToc() for format).
	 * @return string HTML of the rendered book (without body/head).
	 */
	public function renderBook( $collection, $pages, &$metadata ) {
		$hasChapters = (bool)array_filter( $collection['items'], function ( $item ) {
			return $item['type'] === 'chapter';
		} );
		$articleCount = count( array_filter( $collection['items'], function ( $item ) {
			return $item['type'] === 'article';
		} ) );

		$final = '';
		$headingCounter = new HeadingCounter();

		// First we need to render the articles as we can't know the TOC anchors for sure
		// until we have resolved id conflicts.
		// FastFormatter chokes on Parsoid HTML. HtmlFormatter is still plenty fast anyway.
		$formatter = new \RemexHtml\Serializer\HtmlFormatter();
		$serializer = new \RemexHtml\Serializer\Serializer( $formatter );
		$munger = new RemexCollectionMunger( $serializer, [
			'topHeadingLevel' => $hasChapters ? 3 : 2,
		] );
		foreach ( $collection['items'] as $item ) {
			if ( $item['type'] === 'chapter' ) {
				$final .= Html::element( 'h1', [
						'id' => 'mw-book-chapter-' . Sanitizer::escapeIdForAttribute( $item['title'] ),
						'class' => 'mw-book-chapter',
						'data-mw-sectionnumber' => $headingCounter->incrementAndGet( -2 ),
					], $item['title'] ) . "\n";
			} elseif ( $item['type'] === 'article' ) {
				$title = Title::newFromText( $item['title'] );
				$dbkey = $title->getPrefixedDBkey();
				$html = $this->getBodyContents( $pages[$dbkey] );

				$headingAttribs = [
					'id' => 'mw-book-article-' . $dbkey,
					'class' => 'mw-book-article',
				];
				$mungerOptions = [];
				if ( $articleCount > 1 ) {
					$mungerOptions['sectionNumberPrefix'] = $headingAttribs['data-mw-sectionnumber']
						= $headingCounter->incrementAndGet( -1 );
				}
				$final .= Html::rawElement( 'h2', $headingAttribs, $metadata['displaytitle'][$dbkey] ) . "\n";

				$munger->startCollectionSection( './' . $dbkey, $metadata['sections'][$dbkey],
					$headingCounter );
				$treeBuilder = new \RemexHtml\TreeBuilder\TreeBuilder( $munger, [] );
				$dispatcher = new \RemexHtml\TreeBuilder\Dispatcher( $treeBuilder );
				$tokenizer = new \RemexHtml\Tokenizer\Tokenizer( $dispatcher, $html, [
					// HTML comes from Parsoid so we can skip validation
					'ignoreErrors' => true,
					'ignoreCharRefs' => true,
					'ignoreNulls' => true,
					'skipPreprocess' => true,
				] );
				$tokenizer->execute( [
					'fragmentNamespace' => \RemexHtml\HTMLData::NS_HTML,
					'fragmentName' => 'body',
				] );
				$final .= Html::openElement( 'article' )
					. substr( $serializer->getResult(), 15 ) // strip "<!DOCTYPE html>"
					. Html::closeElement( 'article' );
			}
		}

		$final = $this->renderCoverAndToc( $collection, $metadata )
				 . $final
				 . $this->renderContributors( $metadata, $headingCounter->incrementAndGetTopLevel() );
		return $final;
	}

	/**
	 * Generate HTML for book cover page and table of contents.
	 * @param array $collection Collection, as returned by CollectionSession::getCollection().
	 * @param array[] $metadata Map of prefixed DB key => metadata, as returned by fetchMetadata().
	 *   An outline will be added which is similar to sections but flat and each item has the fields
	 *     - text: text of the outline item (article title, section title etc)
	 *     - type: 'chapter', 'article', 'section' or 'contributors'
	 *     - level: heading level or -2 for chapter, -1 for article
	 *     - anchor: id of the document node which the outline item refers to
	 *     - number: a hierarchical section number (something like "1.2.3")
	 * @return string HTML to prepend to the book.
	 */
	private function renderCoverAndToc( $collection, &$metadata ) {
		$hasChapters = (bool)array_filter( $collection['items'], function ( $item ) {
			return $item['type'] === 'chapter';
		} );
		$articleCount = count( array_filter( $collection['items'], function ( $item ) {
			return $item['type'] === 'article';
		} ) );
		$headingCounter = new HeadingCounter();
		$outline = [];
		foreach ( $collection['items'] as $item ) {
			if ( $item['type'] === 'chapter' ) {
				$outline[] = [
					'text' => htmlspecialchars( $item['title'], ENT_QUOTES ),
					'type' => 'chapter',
					'level' => -2,
					'anchor' => 'mw-book-chapter-' . Sanitizer::escapeIdForAttribute( $item['title'] ),
					'number' => $headingCounter->incrementAndGet( -2 ),
				];
			} elseif ( $item['type'] === 'article' ) {
				$title = Title::newFromText( $item['title'] );
				$dbkey = $title->getPrefixedDBkey();
				if ( $articleCount > 1 ) {
					$outline[] = [
						'text' => $metadata['displaytitle'][$dbkey],
						'type' => 'article',
						'level' => -1,
						'anchor' => 'mw-book-article-' . $dbkey,
						'number' => $headingCounter->incrementAndGet( -1 ),
					];
				}
				foreach ( $metadata['sections'][$dbkey] as $section ) {
					$outline[] = [
						'text' => $section['title'],
						'type' => 'section',
						'level' => $section['level'],
						'anchor' => $section['id'],
						'number' => $headingCounter->incrementAndGet( $section['level'] ),
					];
				}
			} else {
				throw new LogicException( 'Unknown collection item type: ' . $item['type'] );
			}
		}

		if ( $hasChapters ) {
			$contributorsLevel = -2;
		} elseif ( $articleCount > 1 ) {
			$contributorsLevel = -1;
		} else {
			$contributorsLevel = 0;
		}
		$outline[] = [
			'text' => wfMessage( 'coll-contributors-title' )->text(),
			'type' => 'contributors',
			'level' => $contributorsLevel,
			'anchor' => 'mw-book-contributors',
			'number' => $headingCounter->incrementAndGetTopLevel(),
		];
		$metadata['outline'] = $outline;

		return $this->templateParser->processTemplate( 'toc', $this->fixTemplateData( [
			'title' => $collection['title'],
			'subtitle' => $collection['subtitle'],
			'toctitle' => wfMessage( 'coll-toc-title' )->text(),
			'tocitems' => $this->getNestedOutline( $outline ),
		] ) );
	}

	/**
	 * Generate HTML for the list of contributors.
	 * @param array[] $metadata Map of prefixed DB key => metadata, as returned by fetchMetadata().
	 * @param string $sectionNumber The section number for the contributors section, if any.
	 * @return string HTML to append to the book.
	 */
	private function renderContributors( $metadata, $sectionNumber = null ) {
		$list = array_map( function ( $name ) {
			return Html::element( 'li', [], $name );
		}, array_keys( $metadata['contributors'] ) );

		$attribs = [ 'id' => 'mw-book-contributors' ];
		if ( $sectionNumber ) {
			$attribs['data-mw-sectionnumber'] = $sectionNumber;
		}
		return Html::element( 'h1', $attribs, 'Contributors' )
			   . Html::rawElement( 'div', [ 'class' => 'contributors' ],
				Html::rawElement( 'ul', [], implode( "\n", $list ) ) );
	}

	/**
	 * Get the part inside the <body> from an HTML file.
	 * Not very robust (a <body> tag in a comment or CDATA section could confuse it) but the
	 * <head> section has no user-controlled part so using it with Parsoid HTML should be fine.
	 * @param string $html
	 * @return string
	 */
	private function getBodyContents( $html ) {
		return preg_replace( '/(^.*?<body\b[^>]*>)|(<\/body>\s*<\/html>\s*$)/si', '', $html );
	}

	/**
	 * Turns a flat outline into a nested outline. Each outline item will contain
	 * a field called 'children' which as an array of child outline items.
	 * @param array[] $outline An outline, as returned by renderCoverAndToc().
	 * @return array[]
	 */
	private function getNestedOutline( array $outline ) {
		$nestedOutline = [];
		$lastItems = []; // level => last (currently open) item on that level
		foreach ( $outline as &$item ) {
			$item['children'] = [];

			$level = $item['level'];
			$lastItems = wfArrayFilterByKey( $lastItems, function ( $key ) use ( $level ) {
				return $key < $level;
			} );
			if ( $lastItems ) {
				end( $lastItems );
				$key = key( $lastItems );
				$lastItems[$key]['children'][] = &$item;
			} else {
				$nestedOutline[] = &$item;
			}
			$lastItems[$level] = &$item;
		}
		return $nestedOutline;
	}

	/**
	 * Fix a data array for Mustache.
	 * Mustache is too stupid to be able to handle conditional pre/postfixes for
	 * arrays (e.g. do not wrap into <ul> when the array of list items is empty).
	 * The lightncandy implementation is too stupid to even do that for non-arrays.)
	 * Add a 'foo?' field for every 'foo', which casts it to boolean.
	 * @param array $data
	 * @return array
	 */
	private function fixTemplateData( $data ) {
		$fixedData = [];
		foreach ( $data as $field => $value ) {
			// treat 0/'0' as truthy
			$fixedData[$field . '?'] = !in_array( $value, [ false, [], '' ], true );
			if ( is_array( $value ) ) {
				if ( array_keys( $value ) === array_keys( array_values( $value ) ) ) {
					// consecutive numeric keys - treat as an array
					$fixedData[$field] = array_map( [ $this, 'fixTemplateData' ], $value );
				} else {
					// treat as a hash
					$fixedData[$field] = $this->fixTemplateData( $value );
				}
			} else {
				$fixedData[$field] = $value;
			}
		}
		return $fixedData;
	}

}
