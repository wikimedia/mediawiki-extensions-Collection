<?php

namespace MediaWiki\Extensions\Collection;

use LogicException;

class HeadingCounterTest extends \PHPUnit_Framework_TestCase {

	/**
	 * @dataProvider provideIncrementAndGet
	 * @param int[] $calls List of integers to call incrementAndGet() with.
	 * @param string $expectedResult Result of last incrementAndGet() call.
	 */
	public function testIncrementAndGet( $calls, $expectedResult ) {
		$headingCounter = new HeadingCounter();
		foreach ( $calls as $level ) {
			$result = $headingCounter->incrementAndGet( $level );
		}
		$this->assertSame( $expectedResult, $result );
	}

	public function provideIncrementAndGet() {
		return [
			[ [ 1 ], '1' ],
			[ [ 1, 1 ], '2' ],
			[ [ 1, 2 ], '1.1' ],
			[ [ 1, 2, 2 ], '1.2' ],
			[ [ 1, 2, 1 ], '2' ],
			[ [ 2, 1 ], '2' ],
			[ [ 1, 3 ], '1.1' ],
			[ [ -1, 1 ], '1.1' ],
			[ [ 1, 3, 1, 2 ], '2.1' ],
			[ [ 1, 2, 1, 2, 3, 2, 3 ], '2.2.1' ],
		];
	}

	/**
	 * @dataProvider provideIncrementAndGetTopLevel
	 * @param int[] $calls List of integers to call incrementAndGet() with.
	 * @param string $expectedResult Result of following incrementAndGetTopLevel() call.
	 */
	public function testIncrementAndGetTopLevel( $calls, $expectedResult ) {
		$headingCounter = new HeadingCounter();
		foreach ( $calls as $level ) {
			$headingCounter->incrementAndGet( $level );
		}
		$result = $headingCounter->incrementAndGetTopLevel();
		$this->assertSame( $expectedResult, $result );
	}

	public function provideIncrementAndGetTopLevel() {
		return [
			[ [ 1 ], '2' ],
			[ [ 1, 2, 3 ], '2' ],
			[ [ -1, 1 ], '2' ],
		];
	}

	/**
	 * @expectedException LogicException
	 */
	public function testIncrementAndGetTopLevel_error() {
		$headingCounter = new HeadingCounter();
		$headingCounter->incrementAndGetTopLevel();
	}

}
