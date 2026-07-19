<?php
/**
 * Guards the vendored Lucide SVG files themselves.
 *
 * @package Woodev\Theme\Base\Tests
 */

declare(strict_types=1);

namespace Woodev\Theme\Base\Tests\Unit;

use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * The icon files are inlined into pages verbatim, so their contents are part of
 * the theme's output surface. This asserts the shape the helper assumes and the
 * absence of anything executable.
 */
final class IconAssetsTest extends BaseTestCase {

	private const ICON_DIR = __DIR__ . '/../../../woodev-base-theme/assets/static/icons';

	/**
	 * Every committed SVG, as [ path, contents ].
	 *
	 * @return array<string, array{string, string}>
	 */
	public static function provide_icons(): array {
		$cases = [];

		foreach ( \glob( self::ICON_DIR . '/*.svg' ) ?: [] as $path ) {
			$cases[ \basename( $path ) ] = [ $path, (string) \file_get_contents( $path ) ];
		}

		return $cases;
	}

	public function test_the_expected_icons_are_all_present(): void {
		$found = \array_map(
			static fn( string $path ): string => \basename( $path, '.svg' ),
			\glob( self::ICON_DIR . '/*.svg' ) ?: []
		);
		\sort( $found );

		self::assertSame(
			[ 'chevron-down', 'chevron-left', 'chevron-right', 'menu', 'moon', 'search', 'sun', 'x' ],
			$found,
			'Icon set drifted from scripts/copy-icons.mjs — re-run `npm run icons`.'
		);
	}

	/**
	 * @dataProvider provide_icons
	 *
	 * @param string $path Absolute path of the icon file.
	 * @param string $svg  Raw file contents.
	 */
	public function test_icons_contain_nothing_executable( string $path, string $svg ): void {
		// Entities are decoded first: `java&#x73;cript:` is a working URL in a
		// browser and an unremarkable string to strpos(), so scanning the raw
		// bytes for 'javascript:' would wave it straight through.
		$decoded = \html_entity_decode( $svg, ENT_QUOTES | ENT_HTML5 );

		foreach ( [ '<script', '<foreignObject', '<style', '<use', '<image', '<a ', 'href=', 'javascript:', 'data:' ] as $needle ) {
			self::assertStringNotContainsStringIgnoringCase( $needle, $decoded, "Active content '{$needle}' in {$path}" );
		}

		self::assertDoesNotMatchRegularExpression( '/\son[a-z]+\s*=/i', $decoded, "Event handler attribute in {$path}" );
	}

	/**
	 * An icon these files reference is an icon we do not control: <use> and
	 * <image> pull in external content, <style> can restyle the whole document
	 * once inlined, and a link makes the icon clickable. Lucide emits none of
	 * them, so the whole-element list above is an allowlist in disguise — this
	 * asserts it explicitly.
	 *
	 * @dataProvider provide_icons
	 *
	 * @param string $path Absolute path of the icon file.
	 * @param string $svg  Raw file contents.
	 */
	public function test_icons_use_only_plain_drawing_elements( string $path, string $svg ): void {
		\preg_match_all( '/<([a-z][a-z0-9:-]*)/i', $svg, $matches );

		$allowed = [ 'svg', 'path', 'circle', 'rect', 'line', 'polyline', 'polygon', 'ellipse', 'g' ];
		$found   = \array_unique( \array_map( 'strtolower', $matches[1] ) );

		self::assertSame( [], \array_diff( $found, $allowed ), "Unexpected element in {$path}" );
	}

	/**
	 * @dataProvider provide_icons
	 *
	 * @param string $path Absolute path of the icon file.
	 * @param string $svg  Raw file contents.
	 */
	public function test_icons_have_the_shape_the_helper_assumes( string $path, string $svg ): void {
		// Note the file does NOT start with <svg: lucide-static v1.25.0 emits a
		// license comment first. Icons::inner_markup() anchors on '<svg' for
		// exactly this reason, so assert the real shape rather than the tidy one.
		self::assertStringStartsWith( '<!-- @license', \trim( $svg ), "Missing upstream license header in {$path}" );
		self::assertSame( 1, \substr_count( $svg, '<svg' ), "More than one root element in {$path}" );
		self::assertStringContainsString( 'viewBox="0 0 24 24"', $svg, "Unexpected coordinate system in {$path}" );
		// Without this, a truncated file keeps its license header and its single
		// root, passes every other assertion here, and makes the helper return
		// '' — a blank icon on a green suite.
		self::assertStringContainsString( '</svg>', $svg, "Unterminated root element in {$path}" );
	}
}
