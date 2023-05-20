<?php

namespace MediaWiki\Extension\Collection;

use LogicException;
use MediaWikiIntegrationTestCase;
use TemplateParser;
use Title;

/**
 * @covers \MediaWiki\Extension\Collection\BookRenderer
 */
class BookRendererTest extends MediaWikiIntegrationTestCase {
	private const TEMPLATE_DIR = '/../../../templates';

	/**
	 * @dataProvider provideGetBookTemplateDataOutlineGeneration
	 */
	public function testGetBookTemplateDataOutlineGeneration(
		$collection, $pages, $metadata, $expectedOutline
	) {
		$templateParser = new TemplateParser( __DIR__ . self::TEMPLATE_DIR );
		$renderer = new BookRenderer( $templateParser );
		$data = $renderer->getBookTemplateData( $collection, $pages, $metadata );
		$this->assertArraySame( $renderer->getNestedOutline( $expectedOutline ), $data['toc']['tocitems'],
			'Check table of contents generation' );
	}

	public function testfixTemplateData() {
		$templateParser = new TemplateParser( __DIR__ . self::TEMPLATE_DIR );
		$renderer = new BookRenderer( $templateParser );
		$fixedData = $renderer->fixTemplateData( [
			'a' => false,
			'b' => [],
			'c' => [
				'd' => [ 'a', 'b', 'c' ],
				'e' => false,
				'f' => [
					'g' => [ 'a', 'b', 'c' ],
				],
			],
			'd' => 'hello world',
			'e' => 0,
			'f' => 1,
		] );
		$this->assertArraySame( $fixedData, [
			'a' => false,
			'b?' => false,
			'b' => [],
			'c?' => true,
			'c' => [
				'd?' => true,
				'd' => [ 'a', 'b', 'c' ],
				'e' => false,
				'f?' => true,
				'f' => [
					'g?' => true,
					'g' => [ 'a', 'b', 'c' ],
				],
			],
			'd?' => true,
			'd' => 'hello world',
			'e?' => true,
			'e' => 0,
			'f?' => true,
			'f' => 1,
		] );
	}

	public function testGetBookTemplateDataImagesGeneration() {
		$templateParser = new TemplateParser( __DIR__ . self::TEMPLATE_DIR );
		$renderer = new BookRenderer( $templateParser );
		$collection = [ 'items' => [], 'title' => 'Empty book' ];
		$data = $renderer->getBookTemplateData( $collection, [], [] );
		$this->assertFalse( $data['license'], 'Template data for empty book has no license' );
		$this->assertFalse( $data['images'], 'Template data for empty book has no images' );
		$this->assertFalse( $data['contributors'],
			'Template data for empty book has no contributors' );
		$this->assertArraySame( $data['toc']['tocitems'], [],
			'Template data for empty book has empty outline' );
	}

	/**
	 * @dataProvider provideRenderBook
	 * @param array[] $collection Collection, as returned by CollectionSession::getCollection().
	 * @param string[] $pages Map of prefixed DB key => Parsoid HTML.
	 * @param array[] $metadata Map of prefixed DB key => metadata, as returned by fetchMetadata().
	 * @param string $expectedHtml Expected HTML of the rendered book
	 */
	public function testRenderBook(
		$collection, $pages, $metadata, $expectedHtml
	) {
		$templateParser = new TemplateParser( __DIR__ . self::TEMPLATE_DIR );
		$templateParser->enableRecursivePartials( true );
		$renderer = new BookRenderer( $templateParser );
		$html = $renderer->renderBook( $collection, $pages, $metadata );
		$this->assertSameExceptWhitespace( $expectedHtml, $html, 'HTML mismatch' );
	}

	public static function provideRenderBook() {
		return [
			'single page' => self::loadData( 'single_page' ),
			'two pages' => self::loadData( 'two_pages' ),
			'chapters' => self::loadData( 'chapters' ),
			'id conflict' => self::loadData( 'id_conflict' ),
			'header conflict' => self::loadData( 'header_conflict' ),
		];
	}

	public static function provideGetBookTemplateDataOutlineGeneration() {
		$cases = [];
		foreach ( [ 'single_page', 'two_pages', 'chapters', 'id_conflict', 'header_conflict' ] as $key ) {
			$eg = self::loadData( $key );
			$cases[] = [
				$eg['collection'], $eg['pages'], $eg['metadata'], $eg['expectedOutline'],
			];
		}
		return $cases;
	}

	/**
	 * @param string $title Book title
	 * @param string $subtitle Book subtitle
	 * @param array $elements Articles/chapters in [ [ name, type ], ... ] format.
	 *   type is either 'chapter' or 'article'.
	 * @return array
	 */
	private static function makeCollection( $title, $subtitle, array $elements ) {
		$collection = [
			'enabled' => true,
			'title' => $title,
			'subtitle' => $subtitle,
			'settings' => [
				'papersize' => 'a4',
				'toc' => 'auto',
				'columns' => '2',
			],
			'items' => [],
		];
		foreach ( $elements as $element ) {
			switch ( $element[1] ) {
				case 'chapter':
					$collection['items'][] = [
						'type' => 'chapter',
						'title' => $element[0],
					];
					break;
				case 'article':
					$collection['items'][] = [
						'type' => 'article',
						'content_type' => 'text/x-wiki',
						'title' => $element[0],
						'revision' => 1,
						'latest' => 1,
						'timestamp' => time(),
						'url' => Title::newFromText( $element[0] )->getFullURL(),
						'currentVersion' => 1,
					];
					break;
				default:
					throw new LogicException( __METHOD__ . ': invalid type ' . $element[1] );
			}
		}
		return $collection;
	}

	/**
	 * Compare two strings, ignoring whitespace.
	 * @param string $expectedHtml
	 * @param string $html
	 * @param string $message
	 */
	private function assertSameExceptWhitespace( $expectedHtml, $html, $message = '' ) {
		$this->assertSame( $this->reduceWhitespace( $expectedHtml ),
			$this->reduceWhitespace( $html ), $message );
	}

	private function reduceWhitespace( $str ) {
		$str = preg_replace( '/>\s+/', '>', $str );
		$str = preg_replace( '/\s+</', '<', $str );
		$str = preg_replace( '/\s+/', ' ', $str );
		$str = preg_replace( '/^\s+|\s+$/', '', $str );
		// for nicer diffs
		$str = preg_replace( '/</', "\n<", $str );
		return $str;
	}

	private static function loadData( $test ) {
		$dir = __DIR__ . '/../../data/BookRendererTest';
		$data = include "$dir/$test.php";
		$data['expectedHtml'] = file_get_contents( "$dir/$test.html" );
		return $data;
	}

	/**
	 * assertSame for arrays.
	 * Nether assertSame nor assertArrayEquals is very useful for visually comparing nested arrays.
	 * Comparing them as strings at least gives the reader a fighting chance.
	 * @param array $expected
	 * @param array $actual
	 * @param string $message
	 */
	private function assertArraySame( $expected, $actual, $message = '' ) {
		if ( $expected === $actual ) {
			$this->assertSame( $expected, $actual, $message );
		} else {
			$expected = var_export( $expected, true );
			$actual = var_export( $actual, true );
			$this->assertSame( $expected, $actual, $message );
			// If we got here, the two arrays are exported to the same string despite not being
			// actually the same. Shouldn't be possible, but just in case:
			if ( $message ) {
				$message .= ': ';
			}
			$this->fail( $message . '=== fails but var_export finds no difference' );
		}
	}

}
