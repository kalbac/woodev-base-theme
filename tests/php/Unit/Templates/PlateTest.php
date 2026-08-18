<?php
/**
 * Guards Templates\Plate: the inline SVG "plate" art ported verbatim from
 * docs/design/v2-mockup/woodev-base-identity.html.
 *
 * @package Woodev\Theme\Base\Tests
 */

declare(strict_types=1);

namespace Woodev\Theme\Base\Tests\Unit\Templates;

use Woodev\Theme\Base\Templates\Plate;
use Woodev\Theme\Base\Tests\Unit\TestCase;

final class PlateTest extends TestCase {

	/**
	 * Shape element counts ported verbatim from the mockup's `<symbol>`
	 * definitions (id="p-{variant}"), counted by hand against the source
	 * markup — includes the background `<rect>` every symbol opens with.
	 *
	 * @return array<string, array{string, int, int, int}>
	 */
	public static function provide_variants(): array {
		return [
			'hero'   => [ 'hero', 480, 400, 6 ],
			'promo'  => [ 'promo', 480, 400, 5 ],
			'mug'    => [ 'mug', 400, 400, 5 ],
			'lamp'   => [ 'lamp', 400, 400, 6 ],
			'box'    => [ 'box', 400, 400, 5 ],
			'plaid'  => [ 'plaid', 400, 400, 4 ],
			'vase'   => [ 'vase', 400, 400, 5 ],
			'towel'  => [ 'towel', 400, 400, 5 ],
			'post-a' => [ 'post-a', 480, 300, 5 ],
			'post-b' => [ 'post-b', 480, 300, 4 ],
			'post-c' => [ 'post-c', 480, 300, 4 ],
		];
	}

	/**
	 * @dataProvider provide_variants
	 */
	public function test_every_variant_renders_its_shapes( string $variant, int $width, int $height, int $shape_count ): void {
		$svg = Plate::render( $variant );

		self::assertStringStartsWith( '<svg ', $svg );
		self::assertStringEndsWith( '</svg>', $svg );
		self::assertStringContainsString( "class=\"wtb-plate wtb-plate--{$variant}\"", $svg );
		self::assertStringContainsString( "viewBox=\"0 0 {$width} {$height}\"", $svg );
		self::assertStringContainsString( 'aria-hidden="true"', $svg );
		self::assertStringContainsString( 'focusable="false"', $svg );

		$found = \substr_count( $svg, '<rect' ) + \substr_count( $svg, '<circle' ) + \substr_count( $svg, '<path' );
		self::assertSame( $shape_count, $found, "Unexpected shape count for variant '{$variant}'" );
	}

	/**
	 * @dataProvider provide_variants
	 */
	public function test_no_variant_uses_a_sprite_reference( string $variant ): void {
		$svg = Plate::render( $variant );

		// The exact lesson ProductPlaceholder::render() records: a <use> against a
		// <symbol> only resolves in a document that also received the sprite's own
		// print, which is not guaranteed for every path a plate can be rendered on.
		self::assertStringNotContainsString( '<use', $svg, "Sprite reference leaked into '{$variant}'" );
		self::assertStringNotContainsString( '<symbol', $svg, "Sprite definition leaked into '{$variant}'" );
	}

	/**
	 * The two panel plates must COVER their box; the tile plates must not.
	 *
	 * This is the one attribute that does NOT come from the `<symbol>` the
	 * shapes were ported from — a symbol carries no preserveAspectRatio, the
	 * use site does — so porting the symbols alone dropped it silently, and
	 * the default (`meet`) letterboxed the promo plate inside a 623x280
	 * column: the artwork drew 336px wide and centred, and the plate's own
	 * background rect stopped short of the column edges. That is a visual
	 * defect no markup assertion in this file would have noticed, which is
	 * why it is pinned by name rather than left to a browser check.
	 */
	public function test_only_the_panel_plates_cover_their_box(): void {
		foreach ( [ 'hero', 'promo', 'post-a', 'post-b', 'post-c' ] as $variant ) {
			self::assertStringContainsString(
				'preserveAspectRatio="xMidYMid slice"',
				Plate::render( $variant ),
				"{$variant} must cover its box — the mockup's own use site says slice."
			);
		}

		foreach ( [ 'mug', 'lamp', 'box', 'plaid', 'vase', 'towel' ] as $variant ) {
			self::assertStringNotContainsString(
				'preserveAspectRatio',
				Plate::render( $variant ),
				"{$variant} is a tile object plate — the mockup leaves it at the default."
			);
		}
	}

	public function test_an_unknown_variant_returns_empty_string_not_a_fatal(): void {
		self::assertSame( '', Plate::render( 'does-not-exist' ) );
	}

	public function test_an_empty_variant_returns_empty_string(): void {
		self::assertSame( '', Plate::render( '' ) );
	}

	/**
	 * The six tile object plates, in the order F1 names them, indexed by
	 * term_id % 6 — but PHP's % keeps the dividend's sign, so a naive
	 * `term_id % 6` used as an array index is out of range for any negative
	 * term_id. tile_variant() must be total over the whole int range.
	 *
	 * @return array<string, array{int, string}>
	 */
	public static function provide_term_ids(): array {
		return [
			'term 0'                => [ 0, 'mug' ],
			'term 1'                => [ 1, 'lamp' ],
			'term 2'                => [ 2, 'box' ],
			'term 3'                => [ 3, 'plaid' ],
			'term 4'                => [ 4, 'vase' ],
			'term 5'                => [ 5, 'towel' ],
			'term 6 wraps to mug'   => [ 6, 'mug' ],
			'term 13 wraps to lamp' => [ 13, 'lamp' ],
			'negative -1'           => [ -1, 'towel' ],
			'negative -6'           => [ -6, 'mug' ],
			'negative -7'           => [ -7, 'towel' ],
			'negative -13'          => [ -13, 'towel' ],
		];
	}

	/**
	 * PHP_INT_MIN % 6 is well-defined and does not overflow (modulo of the
	 * smallest int by a small divisor stays in range), so the expected value
	 * is computed the same way a correct implementation would, rather than
	 * hand-picked — this pins totality at the actual boundary instead of
	 * assuming behaviour near it.
	 */
	private static function tile_for( int $term_id ): string {
		$variants = [ 'mug', 'lamp', 'box', 'plaid', 'vase', 'towel' ];
		$index    = ( ( $term_id % 6 ) + 6 ) % 6;

		return $variants[ $index ];
	}

	/**
	 * @dataProvider provide_term_ids
	 */
	public function test_tile_variant_is_total_over_any_int( int $term_id, string $expected ): void {
		self::assertSame( $expected, Plate::tile_variant( $term_id ) );
	}

	public function test_tile_variant_at_php_int_min_never_throws(): void {
		// A dedicated assertion (not routed through the data provider, which
		// evaluates before the test runs) so a regression here reports as a
		// clean failure rather than an error at collection time.
		self::assertSame( self::tile_for( \PHP_INT_MIN ), Plate::tile_variant( \PHP_INT_MIN ) );
	}
}
