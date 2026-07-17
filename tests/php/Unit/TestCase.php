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
		Monkey\tearDown();
		parent::tearDown();
	}
}
