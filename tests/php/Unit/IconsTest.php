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
			'traversal'               => [ '../../../wp-config' ],
			'traversal encoded'       => [ '..%2Fwp-config' ],
			'absolute path'           => [ '/etc/passwd' ],
			'nested path'             => [ 'sub/sun' ],
			'null byte'               => [ "sun\0.php" ],
			'leading dash'            => [ '-sun' ],
			'trailing dash'           => [ 'sun-' ],
			'double dash'             => [ 'sun--moon' ],
			'empty'                   => [ '' ],
			'unknown but valid'       => [ 'definitely-not-an-icon' ],

			// Rejected by the pattern alone — these resolve to a real file.
			'dot slash prefix'        => [ './sun' ],
			'traversal back into dir' => [ 'chevron-down/../sun' ],
			'sibling via parent'      => [ '../icons/sun' ],
			'case variant'            => [ 'Sun' ],
		];
	}

	/**
	 * Every shipped icon must survive the parser, not just the handful the other
	 * tests happen to name. Truncate chevron-left and the asset tests still see a
	 * plausible file while this helper quietly returns '' — a blank pagination
	 * arrow that nothing catches.
	 *
	 * @dataProvider provide_shipped_icons
	 */
	public function test_every_shipped_icon_renders( string $name ): void {
		$svg = Icons::get( $name );

		self::assertNotSame( '', $svg, "Icon '{$name}' produced no markup" );
		self::assertStringStartsWith( '<svg ', $svg );
		self::assertStringEndsWith( '</svg>', $svg );
		self::assertSame( 1, \substr_count( $svg, '<svg' ), "Nested root in '{$name}'" );
		self::assertSame( 1, \substr_count( $svg, '</svg>' ), "Stray closing tag in '{$name}'" );
		self::assertStringNotContainsString( '<!--', $svg, "Comment leaked into '{$name}'" );
		// Proof the icon has visible geometry: an <svg> with nothing inside is
		// still well-formed markup and still renders nothing at all.
		self::assertMatchesRegularExpression( '/<(path|circle|rect|line|polyline|polygon|ellipse)\b/', $svg );
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function provide_shipped_icons(): array {
		$cases = [];

		foreach ( \glob( \dirname( __DIR__, 3 ) . '/woodev-base-theme/assets/static/icons/*.svg' ) ?: [] as $path ) {
			$name           = \basename( $path, '.svg' );
			$cases[ $name ] = [ $name ];
		}

		return $cases;
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
		$svg = Icons::get(
			'moon',
			[
				'class' => 'wtb-nav__icon',
				'size'  => 16,
			]
		);

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
		// Anchored on the attribute boundary rather than the bare substring
		// 'class=', which any future attribute ending in "class" would satisfy
		// and break this test in a confusing way.
		self::assertDoesNotMatchRegularExpression( '/\sclass="/', $svg );
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

	/**
	 * An empty file is not "malformed XML" — DOMDocument::loadXML( '' ) throws a
	 * ValueError on PHP 8 rather than returning false, so the fail-closed
	 * contract the docblock promises ('' when the file is missing or malformed)
	 * only holds if the empty case is guarded before the parser sees it. A
	 * zero-byte icon must render nothing, never a fatal.
	 */
	public function test_an_empty_icon_file_returns_empty_not_a_fatal(): void {
		$dir = \sys_get_temp_dir() . '/wtb-icons-' . \uniqid();
		\mkdir( $dir . '/assets/static/icons', 0o777, true );
		\file_put_contents( $dir . '/assets/static/icons/blank.svg', '' );
		Functions\when( 'get_template_directory' )->justReturn( $dir );

		try {
			self::assertSame( '', Icons::get( 'blank' ) );
		} finally {
			\unlink( $dir . '/assets/static/icons/blank.svg' );
			\rmdir( $dir . '/assets/static/icons' );
			\rmdir( $dir . '/assets/static' );
			\rmdir( $dir . '/assets' );
			\rmdir( $dir );
		}
	}

	/**
	 * The emptiness guard trims, but the parser must see the untrimmed file: a
	 * document wrapped in NUL bytes is rejected by libxml and must stay rejected.
	 * Trimming before the parse would let such a file through, which is exactly
	 * the fail-open the guard is meant to avoid. Pins "parse $file, not trim($file)".
	 */
	public function test_a_file_wrapped_in_nul_bytes_is_rejected_not_trimmed_into_validity(): void {
		$dir = \sys_get_temp_dir() . '/wtb-icons-' . \uniqid();
		\mkdir( $dir . '/assets/static/icons', 0o777, true );
		\file_put_contents( $dir . '/assets/static/icons/hostile.svg', "\0<svg viewBox=\"0 0 24 24\"><path d=\"M1 1\"/></svg>\0" );
		Functions\when( 'get_template_directory' )->justReturn( $dir );

		try {
			self::assertSame( '', Icons::get( 'hostile' ) );
		} finally {
			\unlink( $dir . '/assets/static/icons/hostile.svg' );
			\rmdir( $dir . '/assets/static/icons' );
			\rmdir( $dir . '/assets/static' );
			\rmdir( $dir . '/assets' );
			\rmdir( $dir );
		}
	}

	/**
	 * The parser toggles libxml_use_internal_errors() and must restore it. This
	 * points at a fresh temp file with a unique path so the static cache is
	 * guaranteed cold — get('sun') could return a memoised result from an earlier
	 * test without ever invoking the parser, making the assertion vacuous.
	 */
	public function test_libxml_internal_error_state_is_restored(): void {
		$dir = \sys_get_temp_dir() . '/wtb-icons-' . \uniqid();
		\mkdir( $dir . '/assets/static/icons', 0o777, true );
		\file_put_contents( $dir . '/assets/static/icons/probe.svg', '<svg viewBox="0 0 24 24"><path d="M1 1"/></svg>' );
		Functions\when( 'get_template_directory' )->justReturn( $dir );

		$before = \libxml_use_internal_errors( false );
		\libxml_use_internal_errors( $before );

		try {
			self::assertNotSame( '', Icons::get( 'probe' ), 'Fixture should render, proving the parser ran' );
			self::assertSame( $before, \libxml_use_internal_errors( $before ), 'libxml_use_internal_errors() was not restored' );
		} finally {
			\unlink( $dir . '/assets/static/icons/probe.svg' );
			\rmdir( $dir . '/assets/static/icons' );
			\rmdir( $dir . '/assets/static' );
			\rmdir( $dir . '/assets' );
			\rmdir( $dir );
		}
	}
}
