<?php
/**
 * Icon helper against a real WordPress: the path resolution that unit tests mock.
 *
 * @package Woodev\Theme\Base\Tests\Integration
 */

declare(strict_types=1);

namespace Woodev\Theme\Base\Tests\Integration;

use WP_UnitTestCase;

final class IconsTest extends WP_UnitTestCase {

	public function test_the_template_tag_outputs_an_icon_for_the_active_theme(): void {
		\ob_start();
		woodev_base_icon( 'sun' );
		$output = (string) \ob_get_clean();

		self::assertStringContainsString( '<svg', $output );
		self::assertStringContainsString( 'aria-hidden="true"', $output );
		self::assertStringContainsString( '<circle', $output );
	}

	public function test_a_labelled_icon_carries_its_accessible_name(): void {
		\ob_start();
		woodev_base_icon(
			'search',
			[
				'label' => 'Search',
				'class' => 'wtb-icon',
			]
		);
		$output = (string) \ob_get_clean();

		self::assertStringContainsString( 'role="img"', $output );
		self::assertStringContainsString( 'aria-label="Search"', $output );
		self::assertStringContainsString( 'class="wtb-icon"', $output );
	}

	public function test_an_unknown_icon_renders_nothing_and_raises_no_error(): void {
		\ob_start();
		woodev_base_icon( 'no-such-icon' );

		// The suite runs with failOnWarning/failOnNotice, so a PHP notice from a
		// missing file would fail this test on its own — the empty-string
		// assertion and the silence are two separate guarantees.
		self::assertSame( '', (string) \ob_get_clean() );
	}
}
