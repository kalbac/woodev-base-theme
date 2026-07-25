<?php
declare(strict_types=1);

namespace Woodev\Theme\Base\Tests\Unit\Customizer;

use Brain\Monkey\Functions;
use Woodev\Theme\Base\Customizer\InlineStyles;
use Woodev\Theme\Base\Tests\Unit\TestCase;

final class InlineStylesTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();

		Functions\when( 'get_template_directory' )->justReturn( \dirname( __DIR__, 4 ) . '/woodev-base-theme' );
	}

	/**
	 * @param array<string, mixed> $mods Stored theme_mods.
	 */
	private function stub_mods( array $mods ): void {
		Functions\when( 'get_theme_mod' )->alias(
			static fn( string $key, $default = false ) => $mods[ $key ] ?? $default
		);
	}

	/**
	 * A site that has touched nothing must ship no inline <style> at all — the
	 * defaults already live in the stylesheet, and an empty block is noise on
	 * every page of every untouched install.
	 */
	public function test_untouched_defaults_emit_nothing(): void {
		$this->stub_mods( [] );

		self::assertSame( '', InlineStyles::build_css() );
	}

	public function test_a_non_default_container_width_emits_one_custom_property(): void {
		$this->stub_mods( [ 'container_width' => 1200 ] );

		self::assertSame( ":root{--wtb-container-max:1200px}\n", InlineStyles::build_css() );
	}

	public function test_a_non_default_radius_overrides_the_token(): void {
		$this->stub_mods( [ 'radius' => 16 ] );

		self::assertSame( ":root{--radius:16px}\n", InlineStyles::build_css() );
	}

	public function test_a_non_default_font_size_sets_the_root_size(): void {
		$this->stub_mods( [ 'base_font_size' => 18 ] );

		self::assertSame( "html{font-size:18px}\n", InlineStyles::build_css() );
	}

	/**
	 * ADR-008: `warm-clay` equals the :root defaults, so it is the one
	 * palette that must emit NOTHING — a stored 'warm-clay' theme_mod
	 * (explicit or default) is indistinguishable from an untouched site.
	 */
	public function test_the_default_palette_emits_no_override(): void {
		$this->stub_mods( [ 'palette' => 'warm-clay' ] );

		self::assertSame( '', InlineStyles::build_css() );
	}

	public function test_a_non_default_palette_emits_all_three_of_its_tokens(): void {
		$this->stub_mods( [ 'palette' => 'cold-petrol' ] );

		self::assertSame(
			":root{--n-h:264;--accent-h:214;--accent-c:0.105}\n",
			InlineStyles::build_css()
		);
	}

	/**
	 * The accent picker overrides ONLY the accent, never --n-h — a chosen
	 * palette's neutral temperature must survive an accent override.
	 */
	public function test_an_accent_override_replaces_only_the_accent_of_a_chosen_palette(): void {
		$this->stub_mods(
			[
				'palette' => 'cold-petrol',
				'accent'  => '#844833', // warm-clay's own accent hex — proves it is not palette-derived.
			]
		);

		self::assertSame(
			":root{--n-h:264;--accent-h:40;--accent-c:0.088}\n",
			InlineStyles::build_css()
		);
	}

	/**
	 * The accent override applies even against the default palette (no
	 * --n-h override, since warm-clay IS the default).
	 */
	public function test_an_accent_override_alone_emits_only_the_accent(): void {
		$this->stub_mods( [ 'accent' => '#844833' ] );

		self::assertSame( ":root{--accent-h:40;--accent-c:0.088}\n", InlineStyles::build_css() );
	}

	/**
	 * A stored accent value that no longer sanitizes to a real colour (e.g.
	 * hand-edited or filter-mangled) must not reach the stylesheet as
	 * garbage — sanitize_accent() already fails it closed to '' before
	 * ColorConverter is ever consulted, so this is a belt-and-suspenders
	 * proof at the InlineStyles boundary, not a new code path.
	 */
	public function test_an_unsanitizable_accent_emits_nothing_for_it(): void {
		$this->stub_mods( [ 'accent' => 'not-a-colour' ] );

		self::assertSame( '', InlineStyles::build_css() );
	}

	public function test_the_system_font_choice_emits_all_three_font_roles(): void {
		$this->stub_mods( [ 'font' => 'system' ] );

		self::assertSame(
			':root{'
				. '--font-display:system-ui, "Segoe UI", Roboto, sans-serif;'
				. '--font-body:system-ui, "Segoe UI", Roboto, sans-serif;'
				. '--font-mono:ui-monospace, "SF Mono", Menlo, monospace'
				. "}\n",
			InlineStyles::build_css()
		);
	}

	public function test_the_default_identity_font_emits_nothing(): void {
		$this->stub_mods( [ 'font' => 'identity' ] );

		self::assertSame( '', InlineStyles::build_css() );
	}

	/**
	 * The cta_reveal setting is a body attribute (T6/orchestrator), never a
	 * CSS declaration — InlineStyles must never emit anything for it, at any
	 * value, even 'always'.
	 */
	public function test_cta_reveal_never_reaches_the_stylesheet(): void {
		$this->stub_mods( [ 'cta_reveal' => 'always' ] );

		self::assertSame( '', InlineStyles::build_css() );
	}

	/**
	 * Re-critic finding on my own fix. Doubling the selector to `:root:root`
	 * made the settings win everywhere — including over a child theme or
	 * Additional CSS, which load after this block and are the documented way to
	 * restyle a theme token. Specificity must stay (0,1,0) so source order
	 * decides and the site owner still has the last word.
	 *
	 * Extended (T7) to cover every new setting alongside the original two:
	 * the cascade proof only holds if NOTHING this class emits ever raises
	 * the block's specificity above the generated `prefers-color-scheme`
	 * fallback's own (0,1,0) — see the class docblock's "CASCADE PROOF".
	 */
	public function test_the_overrides_stay_beatable_by_a_later_plain_root_rule(): void {
		$this->stub_mods(
			[
				'container_width' => 1000,
				'radius'          => 0,
				'palette'         => 'cold-petrol',
				'accent'          => '#4652a3',
				'font'            => 'system',
			]
		);

		$css = InlineStyles::build_css();

		self::assertStringContainsString( ':root{', $css );
		self::assertStringNotContainsString( ':root:root', $css );
		self::assertStringNotContainsString( '!important', $css );
		self::assertStringNotContainsString( ':not(', $css );
		self::assertStringNotContainsString( ':where(', $css );
	}

	public function test_everything_lands_in_one_root_block(): void {
		$this->stub_mods(
			[
				'container_width' => 1000,
				'radius'          => 0,
				'palette'         => 'cold-petrol',
				'accent'          => '#4652a3',
				'font'            => 'system',
			]
		);

		self::assertSame( 1, substr_count( InlineStyles::build_css(), ':root{' ) );
	}

	public function test_it_prints_after_the_stylesheets(): void {
		Functions\expect( 'add_action' )
			->once()
			->with( 'wp_head', \Mockery::type( 'array' ), 20 );

		( new InlineStyles() )->register();
	}
}
