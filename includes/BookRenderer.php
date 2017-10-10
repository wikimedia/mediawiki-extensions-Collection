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
	 * @param array[] $collection as returned by
	 *   CollectionSession::getCollection().
	 * @param string[] $pages Map of prefixed DB key => Parsoid HTML.
	 * @param array[] &$metadata Map of prefixed DB key => metadata, as returned by fetchMetadata().
	 *   Section data will be updated to account for heading level and id changes.
	 *   Also, an outline will be added (see renderCoverAndToc() for format).
	 * @return array with keys html representing the data needed to render the book
	 */
	private function getBookTemplateData( $collection, $pages, $metadata ) {
		$hasChapters = !empty( array_filter( $collection['items'], function ( $item ) {
			return $item['type'] === 'chapter';
		} ) );
		$articleCount = count( array_filter( $collection['items'], function ( $item ) {
			return $item['type'] === 'article';
		} ) );

		$headingCounter = new HeadingCounter();
		$bookBodyHtml = '';
		$items = $collection['items'];
		$tocHeadingCounter = new HeadingCounter();
		$outline = [];

		// First we need to render the articles as we can't know the TOC anchors for sure
		// until we have resolved id conflicts.
		// FastFormatter chokes on Parsoid HTML. HtmlFormatter is still plenty fast anyway.
		$formatter = new \RemexHtml\Serializer\HtmlFormatter();
		$serializer = new \RemexHtml\Serializer\Serializer( $formatter );
		$munger = new RemexCollectionMunger( $serializer, [
			'topHeadingLevel' => $hasChapters ? 3 : 2,
		] );
		foreach ( $items as $item ) {
			$titleText = $item['title'];
			$title = Title::newFromText( $titleText );
			if ( $item['type'] === 'chapter' ) {
				$outline[] = $this->getBookChapterData( $title, $tocHeadingCounter );
				$bookBodyHtml .= Html::element( 'h1', [
						'id' => 'mw-book-chapter-' . Sanitizer::escapeIdForAttribute( $titleText ),
						'class' => 'mw-book-chapter',
						'data-mw-sectionnumber' => $headingCounter->incrementAndGet( -2 ),
					], $titleText ) . "\n";
			} elseif ( $item['type'] === 'article' ) {
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
				$bookBodyHtml .= Html::rawElement( 'h2', $headingAttribs,
					$metadata['displaytitle'][$dbkey] ) . "\n";

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
				$outline = array_merge( $outline,
					$this->getArticleChaptersData( $title, $tocHeadingCounter,
						$metadata['displaytitle'], $metadata['sections'], $articleCount )
				);
				$bookBodyHtml .= Html::openElement( 'article' )
					. substr( $serializer->getResult(), 15 ) // strip "<!DOCTYPE html>"
					. Html::closeElement( 'article' );
			} else {
				throw new LogicException( 'Unknown collection item type: ' . $item['type'] );
			}
		}

		if ( $hasChapters ) {
			$metadataLevel = -2;
		} elseif ( $articleCount > 1 ) {
			$metadataLevel = -1;
		} else {
			$metadataLevel = 0;
		}
		$hasImages = isset( $metadata['images'] ) && $metadata['images'];
		$hasLicense = isset( $metadata['license'] ) && $metadata['license'];

		$outline = array_merge( $outline,
			$this->getAdditionalBookChapters( $tocHeadingCounter, $metadataLevel,
				$hasImages, $hasLicense )
		);

		$templateData = [
			'contributors' => [
				'data' => $metadata['contributors'],
				'level' => $headingCounter->incrementAndGetTopLevel(),
			],
			'outline' => $outline,
			'html' => $bookBodyHtml,
		];
		if ( $hasImages ) {
			$templateData['images'] = [
				'data' => $metadata['images'],
				'level' => $headingCounter->incrementAndGetTopLevel(),
			];
		} else {
			$templateData['images'] = false;
		}
		if ( $hasLicense ) {
			$templateData['license'] = [
				'data' => $metadata['license'],
				'level' => $headingCounter->incrementAndGetTopLevel(),
			];
		} else {
			$templateData['license'] = false;
		}
		return $templateData;
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
		$book = $this->getBookTemplateData( $collection, $pages, $metadata );

		$final = $this->renderCoverAndToc( $collection, $book['outline'] )
			. $book['html']
			. $this->renderContributors( $book['contributors']['data'], $book['contributors']['level'] );
		if ( $book['images'] ) {
			$final .= $this->renderImageInfos( $book['images']['data'], $book['images']['level'] );
		}
		if ( $book['license'] ) {
			$final .= $this->renderLicense( $book['license']['data'], $book['license']['level'] );
		}

		return $final;
	}

	/**
	 * Generate template data for outline chapter
	 * @param Title $title for book
	 * @param HeadingCounter $tocHeadingCounter
	 * @return array
	 */
	private function getBookChapterData( $title, $tocHeadingCounter ) {
		return [
			'text' => htmlspecialchars( $title, ENT_QUOTES ),
			'type' => 'chapter',
			'level' => -2,
			'anchor' => 'mw-book-chapter-' . Sanitizer::escapeIdForAttribute( $title ),
			'number' => $tocHeadingCounter->incrementAndGet( -2 ),
		];
	}

	/**
	 * Generate template data for the chapters in the given article
	 * @param Title $title to extract sections for
	 * @param HeadingCounter $tocHeadingCounter
	 * @param array[] $displayTitles mapping dbkeys to display titles for the book
	 * @param array[] $sections Section data; each section is a triple
	 *   [ title => ..., id => ..., level => ... ]. RemexCollectionMunger will update the id/level
	 *   to keep in sync with document changes.
	 * @param integer $articleCount number of articles in the book
	 * @return array
	 */
	private function getArticleChaptersData(
		$title, $tocHeadingCounter, $displayTitles, $sections, $articleCount
	) {
		$chapters = [];
		$dbkey = $title->getPrefixedDBkey();

		if ( $articleCount > 1 ) {
			$chapters[] = [
				'text' => $displayTitles[$dbkey],
				'type' => 'article',
				'level' => -1,
				'anchor' => 'mw-book-article-' . $dbkey,
				'number' => $tocHeadingCounter->incrementAndGet( -1 ),
			];
		}
		foreach ( $sections[$dbkey] as $section ) {
			$chapters[] = [
				'text' => $section['title'],
				'type' => 'section',
				'level' => $section['level'],
				'anchor' => $section['id'],
				'number' => $tocHeadingCounter->incrementAndGet( $section['level'] ),
			];
		}
		return $chapters;
	}

	/**
	 * Generate template data for any additional chapters in the given article
	 * @param HeadingCounter $tocHeadingCounter
	 * @param integer $metadataLevel the table of contents level for a given article
	 * @param boolean $hasImages whether the book contains images section
	 * @param boolean $hasLicense whether the book contains a license section
	 * @return array
	 */
	private function getAdditionalBookChapters(
		$tocHeadingCounter, $metadataLevel, $hasImages = false, $hasLicense = false
	) {
		$outline[] = [
			'text' => wfMessage( 'coll-contributors-title' )->text(),
			'type' => 'contributors',
			'level' => $metadataLevel,
			'anchor' => 'mw-book-contributors',
			'number' => $tocHeadingCounter->incrementAndGetTopLevel(),
		];
		if ( $hasImages ) {
			$outline[] = [
				'text' => wfMessage( 'coll-images-title' )->text(),
				'type' => 'images',
				'level' => $metadataLevel,
				'anchor' => 'mw-book-images',
				'number' => $tocHeadingCounter->incrementAndGetTopLevel(),
			];
		}
		if ( $hasLicense ) {
			$outline[] = [
				'text' => wfMessage( 'coll-license-title' )->text(),
				'type' => 'license',
				'level' => $metadataLevel,
				'anchor' => 'mw-book-license',
				'number' => $tocHeadingCounter->incrementAndGetTopLevel(),
			];
		}
		return $outline;
	}
	/**
	 * Generate HTML for book cover page and table of contents.
	 * @param array $collection Collection, as returned by CollectionSession::getCollection().
	 * @param array $outline of the book (chapters)
	 * @return string HTML to prepend to the book.
	 */
	private function renderCoverAndToc( $collection, $outline ) {
		return $this->templateParser->processTemplate( 'toc', $this->fixTemplateData( [
			'title' => $collection['title'],
			'subtitle' => $collection['subtitle'],
			'toctitle' => wfMessage( 'coll-toc-title' )->text(),
			'tocitems' => $this->getNestedOutline( $outline ),
		] ) );
	}

	/**
	 * Generate HTML for the list of contributors.
	 * @param array[] $contributors who edited the book
	 * @param string $sectionNumber The section number for the contributors section, if any.
	 * @return string HTML to append to the book.
	 */
	private function renderContributors( $contributors, $sectionNumber = null ) {
		$list = array_map( function ( $name ) {
			return Html::element( 'li', [], $name );
		}, array_keys( $contributors ) );

		$attribs = [ 'id' => 'mw-book-contributors' ];
		if ( $sectionNumber ) {
			$attribs['data-mw-sectionnumber'] = $sectionNumber;
		}
		return Html::element( 'h1', $attribs, 'Contributors' )
			   . Html::rawElement( 'div', [ 'class' => 'contributors' ],
				Html::rawElement( 'ul', [], implode( "\n", $list ) ) );
	}

	/**
	 * Generate HTML for the images used in the book
	 * @param array[] $imageList
	 * @param string $sectionNumber The section number for the images section, if any.
	 * @return string HTML to append to the book.
	 */
	private function renderImageInfos( $imageList, $sectionNumber = null ) {
		$messages = [
			'sourceMsg' => wfMessage( 'coll-images-source' )->text(),
			'licenseMsg' => wfMessage( 'coll-images-license' )->text(),
			'artistMsg' => wfMessage( 'coll-images-original-artist' )->text()
		];
		// Mustache templates in Lightncandy are not able to access template data in parent object
		// to circumvent that we have to repeat the common messages across all the items.
		$images = [];
		foreach ( $imageList as $image ) {
			$images[] = array_merge( $image, $messages );
		}
		return $this->templateParser->processTemplate( 'images', [
			'sectionNumber' => $sectionNumber,
			'images' => $images,
			'headingMsg' => wfMessage( 'coll-images-title' )->text()
		] );
	}

	/**
	 * Generate HTML for the content license of the book
	 * @param array $license with url and text fields
	 * @param string $sectionNumber The section number for the images section, if any.
	 * @return string HTML to append to the book.
	 */
	private function renderLicense( $license, $sectionNumber = null ) {
		return $this->templateParser->processTemplate( 'license', [
			'sectionNumber' => $sectionNumber,
			'license' => $license,
			'headingMsg' => wfMessage( 'coll-license-title' )->text()
		] );
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
