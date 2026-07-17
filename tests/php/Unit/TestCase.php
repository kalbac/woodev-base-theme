<?php
/**
 * Base unit test case with Brain\Monkey lifecycle.
 *
 * @package Woodev\Theme\Base\Tests
 */

declare(strict_types=1);

namespace Woodev\Theme\Base\Tests\Unit;

use Brain\Monkey;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

abstract class TestCase extends PHPUnitTestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		// Mockery verifies Brain\Monkey's expectations itself, so PHPUnit counts
		// zero assertions and marks expectation-only tests "risky". Fold the
		// expectation count in so a test that only sets expectations still reports
		// honestly — and so a genuinely assertion-free test stays visible as risky.
		$container = \Mockery::getContainer();

		if ( null !== $container ) {
			$this->addToAssertionCount( $container->mockery_getExpectationCount() );
		}

		Monkey\tearDown();
		parent::tearDown();
	}
}
