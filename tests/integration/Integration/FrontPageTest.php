<?php
/**
 * Front page render-mode coverage (#37).
 *
 * `front-page.php` has four render modes and, before this file, none of them
 * had a test: static front page with a featured image, static front page
 * without one, the posts front page, and a site with no WooCommerce at all.
 * Three of the four are SILENT failure modes — every section here
 * self-suppresses when its data source is missing, so a broken one does not
 * error, it just renders a slightly emptier page. The duplicate-<h1> defect
 * that shipped in s16 was caught by the Codex re-critic, and the missing
 * sidebar wrapper only by e2e; nothing at this level was watching either.
 *
 * Renders the theme's OWN resolved template — `get_front_page_template()`,
 * the same core function TemplateHierarchyTest pins for other views — rather
 * than a hand-picked path, so a future hierarchy regression (e.g. a stray
 * page-front-page.php) would also be visible here.
 *
 * @package Woodev\Theme\Base\Tests\Integration
 */

declare(strict_types=1);

namespace Woodev\Theme\Base\Tests\Integration;

use DOMDocument;
use DOMXPath;
use WP_UnitTestCase;

final class FrontPageTest extends WP_UnitTestCase {

	public function tear_down(): void {
		// Belt-and-suspenders alongside the core suite's own per-test DB
		// rollback and cache flush (CustomizerTest/SchemeTest do the same for
		// theme_mods) — explicit restore is cheap and this project's e2e
		// helpers (theme-mod.mjs) hold the same rule for a reason: never rely
		// on "probably gets reset".
		update_option( 'show_on_front', 'posts' );
		delete_option( 'page_on_front' );
		remove_theme_mod( 'sidebar_position' );
		remove_filter( 'is_active_sidebar', '__return_true' );

		parent::tear_down();
	}

	/**
	 * Render whatever WordPress resolves as the front-page template for the
	 * CURRENT request (set up by $this->go_to() beforehand).
	 */
	private function render_front_page(): string {
		wp_set_template_globals();

		$template = get_front_page_template();
		self::assertNotSame( '', $template, 'WordPress did not resolve a front-page template for this request — is front-page.php missing?' );

		ob_start();
		require $template;

		return (string) ob_get_clean();
	}

	/**
	 * Parse captured markup for XPath queries, mirroring
	 * AssetsProductionTest::assert_font_preload_link() — DOMDocument over
	 * regex, since this codebase has already paid for that lesson three times
	 * (see that method's docblock).
	 */
	private static function xpath( string $html ): DOMXPath {
		self::assertNotSame( '', $html, 'The captured render is empty. Nothing was rendered at all.' );

		$dom      = new DOMDocument();
		$previous = libxml_use_internal_errors( true );

		try {
			$dom->loadHTML( $html, LIBXML_NOWARNING | LIBXML_NOERROR );
		} finally {
			libxml_clear_errors();
			libxml_use_internal_errors( $previous );
		}

		return new DOMXPath( $dom );
	}

	/**
	 * Count elements carrying an exact class TOKEN — not a substring match,
	 * which would conflate e.g. `wtb-cat-tile` with `wtb-cat-tiles`.
	 */
	private static function count_class( DOMXPath $xpath, string $class ): int {
		$query = sprintf(
			"//*[contains(concat(' ', normalize-space(@class), ' '), ' %s ')]",
			$class
		);

		return $xpath->query( $query )->length;
	}

	/**
	 * Static front page WITH a featured image.
	 *
	 * A `<!--nextpage-->` split is required content, not decoration: without
	 * a real second page, wp_link_pages() prints nothing at all and the
	 * assertion for it would be vacuous.
	 */
	public function test_static_front_page_with_featured_image(): void {
		$page_id = self::factory()->post->create(
			[
				'post_type'    => 'page',
				'post_title'   => 'Home',
				'post_content' => "First half.\n<!--nextpage-->\nSecond half.",
			]
		);

		$attachment_id = self::factory()->attachment->create_object(
			'canola.jpg',
			$page_id,
			[
				'post_mime_type' => 'image/jpeg',
				'post_type'      => 'attachment',
			]
		);
		set_post_thumbnail( $page_id, $attachment_id );

		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $page_id );

		$this->go_to( home_url( '/' ) );
		self::assertTrue( is_front_page() );
		self::assertFalse( is_home() );

		$html  = $this->render_front_page();
		$xpath = self::xpath( $html );

		self::assertSame(
			1,
			$xpath->query( '//h1' )->length,
			'Expected exactly one <h1> on a static front page with a featured image.'
		);
		self::assertSame(
			0,
			self::count_class( $xpath, 'wtb-entry-title' ),
			'content.php must suppress its own <h1> (hide_entry_head) — the hero already rendered one.'
		);
		self::assertSame(
			0,
			self::count_class( $xpath, 'wtb-entry-thumbnail' ),
			'the featured image must not render a second time inside content.php.'
		);
		self::assertSame(
			1,
			self::count_class( $xpath, 'wtb-hero__image' ),
			'expected the hero to render the featured image exactly once.'
		);

		// the_content() only ever renders the CURRENTLY REQUESTED page of a
		// multi-page post — "Second half." is reachable only via the
		// wp_link_pages() nav link this asserts next, not printed inline on
		// page 1. Asserting it verbatim here would be wrong, not stricter.
		self::assertStringContainsString( 'First half.', $html, 'the_content() output for page 1 is missing.' );
		self::assertSame(
			1,
			self::count_class( $xpath, 'wtb-page-links' ),
			'wp_link_pages() output is missing — expected nav.wtb-page-links from the <!--nextpage--> split.'
		);
		self::assertSame(
			1,
			$xpath->query( "//nav[contains(concat(' ', normalize-space(@class), ' '), ' wtb-page-links ')]//a[@href='" . home_url( '/?page=2' ) . "']" )->length,
			'expected wp_link_pages() to print a link to page 2.'
		);
	}

	/**
	 * Static front page WITHOUT a featured image: the hero's art slot has
	 * nothing to show, and the page must still render intact.
	 */
	public function test_static_front_page_without_featured_image(): void {
		$page_id = self::factory()->post->create(
			[
				'post_type'    => 'page',
				'post_title'   => 'Home',
				'post_content' => 'Just one page of content.',
			]
		);

		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $page_id );

		$this->go_to( home_url( '/' ) );
		self::assertTrue( is_front_page() );

		$html  = $this->render_front_page();
		$xpath = self::xpath( $html );

		self::assertSame(
			1,
			$xpath->query( '//h1' )->length,
			'Expected exactly one <h1> on a static front page with no featured image.'
		);
		self::assertSame(
			0,
			self::count_class( $xpath, 'wtb-hero__image' ),
			'no featured image is set on this page — the hero must not render an <img>.'
		);
		self::assertSame(
			0,
			self::count_class( $xpath, 'wtb-entry-thumbnail' )
		);
		self::assertStringContainsString( 'Just one page of content.', $html );
	}

	/**
	 * Posts front page: the loop renders, the sr-only site-name heading
	 * index.php prints for this exact case is gone (the hero's <h1> replaces
	 * it), and the sidebar wrapper class is present — the s16 regression that
	 * only e2e caught (front-page.php v1 rendered neither Layout wrapper).
	 */
	public function test_posts_front_page(): void {
		update_option( 'show_on_front', 'posts' );
		self::factory()->post->create_many( 3 );

		set_theme_mod( 'sidebar_position', 'right' );
		add_filter( 'is_active_sidebar', '__return_true' );

		$this->go_to( home_url( '/' ) );
		self::assertTrue( is_home() );
		self::assertTrue( is_front_page() );

		$html  = $this->render_front_page();
		$xpath = self::xpath( $html );

		self::assertSame(
			1,
			$xpath->query( '//h1' )->length,
			'Expected exactly one <h1> (the hero) on the posts front page.'
		);
		self::assertSame(
			0,
			$xpath->query( "//h1[contains(concat(' ', normalize-space(@class), ' '), ' sr-only ')]" )->length,
			"index.php's sr-only site-name heading (h1.sr-only) must not appear on the front page — the hero already renders a real <h1>. " .
			'(sr-only spans elsewhere — read-more links, pagination — are unrelated and expected.)'
		);
		self::assertSame(
			1,
			self::count_class( $xpath, 'wtb-post-grid' ),
			'expected the post loop (template-parts/content/loop) to render.'
		);
		self::assertSame(
			1,
			self::count_class( $xpath, 'wtb-layout--has-sidebar' ),
			'the s16 regression: Layout::has_sidebar() is true, so the wrapper must carry wtb-layout--has-sidebar.'
		);
	}

	/**
	 * No WooCommerce: `product_cat` does not exist, so category-tiles.php
	 * must render nothing at all and the front page degrades to what
	 * index.php would have given it.
	 */
	public function test_no_woocommerce_renders_no_category_tiles(): void {
		self::assertFalse(
			taxonomy_exists( 'product_cat' ),
			'This integration harness must not have WooCommerce active — otherwise this test proves nothing.'
		);

		update_option( 'show_on_front', 'posts' );
		self::factory()->post->create_many( 2 );

		$this->go_to( home_url( '/' ) );

		$html  = $this->render_front_page();
		$xpath = self::xpath( $html );

		self::assertSame( 0, self::count_class( $xpath, 'wtb-cat-tile' ) );
		self::assertSame( 0, self::count_class( $xpath, 'wtb-cat-tiles' ) );
		self::assertSame(
			1,
			$xpath->query( '//h1' )->length,
			'The page must still render — one <h1>, same as any posts front page.'
		);
	}
}
