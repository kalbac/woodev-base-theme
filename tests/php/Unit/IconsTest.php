<?php
declare(strict_types=1);

namespace Woodev\Theme\Base\Tests\Unit;

use Brain\Monkey\Functions;
use Woodev\Theme\Base\Icons;

final class IconsTest extends TestCase {

	/**
	 * Points the helper at the real committed icons rather than a fixture: the
	 * files it must parse are the files that ship, and a fixture would let the
	 * two drift.
	 */
	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'get_template_directory' )->justReturn( \dirname( __DIR__, 3 ) . '/woodev-base-theme' );
		Functions\when( 'esc_attr' )->returnArg();
	}

	/**
	 * @dataProvider provide_rejected_names
	 */
	public function test_rejects_names_that_are_not_plain_icon_slugs( string $name ): void {
		self::assertSame( '', Icons::get( $name ) );
	}

	/**
	 * Two groups, and the difference between them is the whole point.
	 *
	 * Most rejected names do not resolve to a file, so `is_file()` would reject
	 * them even with the slug guard deleted — they document intent but pin
	 * nothing. The "resolves to a real file" group is what actually holds the
	 * guard in place: each one escapes the slug shape yet lands on a readable
	 * SVG, so it can only be stopped by the pattern. Deleting the guard turns
	 * exactly those red.
	 *
	 * Measured, not assumed — by deleting the guard and running the suite on
	 * both platforms. Only './sun' and '../icons/sun' fail everywhere; those two
	 * are what pin the guard on CI. 'chevron-down/../sun' resolves on Windows
	 * but not on Linux, where a non-directory path component is an error, and
	 * 'Sun' resolves only on case-insensitive filesystems. Both are kept as
	 * documentation of intent, but neither may be relied on as the pin.
	 */
	public static function provide_rejected_names(): array {
		return [
			// Rejected by the pattern, and would also miss the filesystem.
			'traversal'                => [ '../../../wp-config' ],
			'traversal encoded'        => [ '..%2Fwp-config' ],
			'absolute path'            => [ '/etc/passwd' ],
			'nested path'              => [ 'sub/sun' ],
			'null byte'                => [ "sun\0.php" ],
			'leading dash'             => [ '-sun' ],
			'trailing dash'            => [ 'sun-' ],
			'double dash'              => [ 'sun--moon' ],
			'empty'                    => [ '' ],
			'unknown but valid'        => [ 'definitely-not-an-icon' ],

			// Rejected by the pattern alone — these resolve to a real file.
			'dot slash prefix'         => [ './sun' ],
			'traversal back into dir'  => [ 'chevron-down/../sun' ],
			'sibling via parent'       => [ '../icons/sun' ],
			'case variant'             => [ 'Sun' ],
		];
	}

	public function test_emits_our_own_svg_wrapper_not_the_upstream_one(): void {
		$svg = Icons::get( 'sun' );

		self::assertStringStartsWith( '<svg ', $svg );
		self::assertStringEndsWith( '</svg>', $svg );
		self::assertStringContainsString( 'viewBox="0 0 24 24"', $svg );
		self::assertStringContainsString( 'stroke="currentColor"', $svg );
		// The upstream element carries its own classes; we discard the whole
		// opening tag, so none of them may survive into our output.
		self::assertStringNotContainsString( 'lucide', $svg );
		// Exactly one root element — proof the inner paths were extracted rather
		// than the whole upstream file being wrapped in a second <svg>.
		self::assertSame( 1, \substr_count( $svg, '<svg' ) );
	}

	public function test_keeps_the_inner_paths_untouched(): void {
		$svg = Icons::get( 'sun' );

		// Lucide's sun is a circle plus 8 rays; if extraction dropped children the
		// icon would render blank while every attribute assertion still passed.
		self::assertStringContainsString( '<circle', $svg );
		self::assertSame( 8, \substr_count( $svg, '<path' ) );
	}

	public function test_the_upstream_license_comment_does_not_leak_into_the_page(): void {
		$svg = Icons::get( 'sun' );

		// Every lucide-static file opens with an HTML comment before <svg>.
		// Extraction anchored to the first '>' in the file would swallow it plus
		// the real opening tag, producing markup that still renders and still
		// contains '<svg' — so assert the comment's absence directly.
		self::assertStringNotContainsString( '@license', $svg );
		self::assertStringNotContainsString( '<!--', $svg );
	}

	public function test_applies_a_custom_class_and_size(): void {
		$svg = Icons::get( 'moon', [ 'class' => 'wtb-nav__icon', 'size' => 16 ] );

		self::assertStringContainsString( 'class="wtb-nav__icon"', $svg );
		self::assertStringContainsString( 'width="16"', $svg );
		self::assertStringContainsString( 'height="16"', $svg );
		// The viewBox is the coordinate system, not the rendered size: it must
		// stay 24 regardless of the pixel size, or the icon crops.
		self::assertStringContainsString( 'viewBox="0 0 24 24"', $svg );
	}

	public function test_defaults_to_24_pixels_and_no_class_attribute(): void {
		$svg = Icons::get( 'x' );

		self::assertStringContainsString( 'width="24"', $svg );
		self::assertStringNotContainsString( 'class=', $svg );
	}

	public function test_is_decorative_by_default(): void {
		$svg = Icons::get( 'chevron-down' );

		self::assertStringContainsString( 'aria-hidden="true"', $svg );
		// Without this, IE-era and some current browsers put SVGs in the tab
		// order, so a decorative icon becomes a focus stop with no name.
		self::assertStringContainsString( 'focusable="false"', $svg );
		self::assertStringNotContainsString( 'role="img"', $svg );
	}

	public function test_a_label_makes_the_icon_meaningful(): void {
		$svg = Icons::get( 'search', [ 'label' => 'Search' ] );

		self::assertStringContainsString( 'role="img"', $svg );
		self::assertStringContainsString( 'aria-label="Search"', $svg );
		// A labelled icon carries the name itself; hiding it would erase it.
		self::assertStringNotContainsString( 'aria-hidden', $svg );
	}

	public function test_an_empty_label_is_treated_as_decorative(): void {
		// Guards the common call pattern woodev_base_icon( 'x', [ 'label' => $maybe_empty ] ):
		// an empty accessible name is worse than none, because role="img" with no
		// name is announced as an unlabelled image.
		$svg = Icons::get( 'x', [ 'label' => '' ] );

		self::assertStringContainsString( 'aria-hidden="true"', $svg );
		self::assertStringNotContainsString( 'role="img"', $svg );
	}

	public function test_the_label_is_escaped(): void {
		Functions\when( 'esc_attr' )->alias( static fn( $value ) => \htmlspecialchars( (string) $value, ENT_QUOTES ) );

		$svg = Icons::get( 'menu', [ 'label' => 'Close "menu" & go' ] );

		self::assertStringContainsString( 'aria-label="Close &quot;menu&quot; &amp; go"', $svg );
		self::assertStringNotContainsString( '"menu"', $svg );
	}
}
