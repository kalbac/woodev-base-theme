<?php
/**
 * Front-page merchandising sections, configured and unconfigured (#18, #37).
 *
 * FrontPageTest covers the four RENDER MODES of `front-page.php`. This file
 * covers the sections inside it whose content comes from Customizer settings:
 * the hero's eyebrow, lede, trust badges and art column, the value band, and
 * the promo strip.
 *
 * Every one of them self-suppresses when its setting is empty, and that is
 * exactly the failure shape `docs/gotchas/qa-gates-cover-less-than-they-claim.md`
 * describes: a section that stops rendering does not error, it just leaves a
 * slightly emptier page, and nobody notices for a release. So each section is
 * pinned twice — absent when unset, present when set — rather than once.
 *
 * @package Woodev\Theme\Base\Tests\Integration
 */

declare(strict_types=1);

namespace Woodev\Theme\Base\Tests\Integration;

use DOMDocument;
use DOMXPath;
use WP_UnitTestCase;

final class FrontPageSectionsTest extends WP_UnitTestCase {

	/**
	 * Every theme_mod this file writes, cleared after each test.
	 *
	 * Explicit rather than trusted to the core suite's DB rollback, for the
	 * same reason the e2e helpers restore what they touch: a setting that
	 * leaks into the next test turns an independent assertion into one that
	 * passes for a reason it does not state.
	 *
	 * @var array<int, string>
	 */
	private const TOUCHED_MODS = [
		'front_hero_eyebrow',
		'front_hero_lede',
		'front_hero_trust',
		'front_hero_art',
		'front_value_items',
		'front_promo_title',
		'front_promo_text',
		'front_promo_cta_label',
		'front_promo_cta_url',
		'front_promo_image',
		'front_newsletter_shortcode',
	];

	public function tear_down(): void {
		foreach ( self::TOUCHED_MODS as $mod ) {
			remove_theme_mod( $mod );
		}

		update_option( 'show_on_front', 'posts' );
		delete_option( 'page_on_front' );

		parent::tear_down();
	}

	/**
	 * Render the front page for the current request.
	 *
	 * Same shape as FrontPageTest::render_front_page() — the theme's OWN
	 * resolved template, not a hand-picked path.
	 */
	private function render(): string {
		$this->go_to( home_url( '/' ) );

		wp_set_template_globals();

		$template = get_front_page_template();
		self::assertNotSame( '', $template, 'WordPress did not resolve a front-page template.' );

		ob_start();
		require $template;

		return (string) ob_get_clean();
	}

	/**
	 * Parse captured markup for XPath queries. DOMDocument over regex — this
	 * codebase has paid for that lesson more than once (Icons::inner_markup()
	 * has the full account).
	 */
	private static function xpath( string $html ): DOMXPath {
		self::assertNotSame( '', $html, 'The captured render is empty.' );

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
	 * Count elements carrying an exact class TOKEN, so `wtb-cat-tile` cannot
	 * match `wtb-cat-tiles`.
	 */
	private static function count_class( DOMXPath $xpath, string $class ): int {
		return $xpath->query(
			sprintf( "//*[contains(concat(' ', normalize-space(@class), ' '), ' %s ')]", $class )
		)->length;
	}

	public function test_the_hero_eyebrow_is_absent_until_it_is_set(): void {
		$xpath = self::xpath( $this->render() );

		self::assertSame( 0, self::count_class( $xpath, 'wtb-hero__eyebrow' ) );
	}

	public function test_the_hero_eyebrow_renders_when_set(): void {
		set_theme_mod( 'front_hero_eyebrow', 'Spring delivery' );

		$html  = $this->render();
		$xpath = self::xpath( $html );

		self::assertSame( 1, self::count_class( $xpath, 'wtb-hero__eyebrow' ) );
		self::assertStringContainsString( 'Spring delivery', $html );
	}

	/**
	 * The tagline is the hero's lede until an admin writes one — and then the
	 * admin's copy REPLACES it rather than joining it. Both halves asserted:
	 * a fallback that silently prints both would look fine in a screenshot and
	 * wrong on a real site.
	 */
	public function test_the_hero_lede_falls_back_to_the_site_tagline(): void {
		update_option( 'blogdescription', 'A tagline from the site' );

		$html = $this->render();

		self::assertStringContainsString( 'A tagline from the site', $html );
	}

	public function test_the_hero_lede_setting_displaces_the_tagline(): void {
		update_option( 'blogdescription', 'A tagline from the site' );
		set_theme_mod( 'front_hero_lede', 'A lede the admin wrote' );

		$html = $this->render();

		self::assertStringContainsString( 'A lede the admin wrote', $html );
		self::assertStringNotContainsString( 'A tagline from the site', $html );
	}

	public function test_the_trust_badges_render_one_element_per_line(): void {
		set_theme_mod( 'front_hero_trust', "Delivery in a day | truck\nReturns within 30 days | refresh-cw" );

		$html  = $this->render();
		$xpath = self::xpath( $html );

		self::assertSame( 1, self::count_class( $xpath, 'wtb-hero__trust' ) );
		self::assertSame(
			2,
			$xpath->query( "//*[contains(concat(' ', normalize-space(@class), ' '), ' wtb-hero__trust ')]/div" )->length
		);
		self::assertStringContainsString( 'Delivery in a day', $html );
		self::assertStringContainsString( 'Returns within 30 days', $html );
	}

	/**
	 * The art column, all three outcomes. The plate is the one that matters:
	 * an empty art slot rendered as a bordered grey rectangle is what #18
	 * describes as reading like a broken image.
	 */
	public function test_the_hero_art_falls_back_to_the_plate_without_a_featured_image(): void {
		$xpath = self::xpath( $this->render() );

		self::assertSame( 1, self::count_class( $xpath, 'wtb-hero__art' ) );
		self::assertSame( 1, self::count_class( $xpath, 'wtb-plate--hero' ) );
		self::assertSame( 0, self::count_class( $xpath, 'wtb-hero__image' ) );
	}

	public function test_the_hero_art_prefers_a_featured_image_over_the_plate(): void {
		$page_id = self::factory()->post->create( [ 'post_type' => 'page' ] );

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

		$xpath = self::xpath( $this->render() );

		self::assertSame( 1, self::count_class( $xpath, 'wtb-hero__image' ) );
		self::assertSame( 0, self::count_class( $xpath, 'wtb-plate--hero' ) );
	}

	public function test_the_hero_art_column_disappears_when_it_is_switched_off(): void {
		set_theme_mod( 'front_hero_art', 'off' );

		$xpath = self::xpath( $this->render() );

		self::assertSame( 0, self::count_class( $xpath, 'wtb-hero__art' ) );
		self::assertSame( 0, self::count_class( $xpath, 'wtb-plate--hero' ) );
		self::assertSame(
			1,
			self::count_class( $xpath, 'wtb-hero__inner--single' ),
			'without the modifier the two-column grid keeps a third of the hero empty.'
		);
	}

	public function test_the_value_band_is_absent_until_it_is_configured(): void {
		$xpath = self::xpath( $this->render() );

		self::assertSame( 0, self::count_class( $xpath, 'wtb-value-band' ) );
	}

	public function test_the_value_band_renders_one_item_per_configured_line(): void {
		set_theme_mod(
			'front_value_items',
			"Fast delivery | Courier to the door | truck\nTwo-year warranty | Repair or replace | shield-check"
		);

		$html  = $this->render();
		$xpath = self::xpath( $html );

		self::assertSame( 1, self::count_class( $xpath, 'wtb-value-band' ) );
		self::assertSame( 2, self::count_class( $xpath, 'wtb-value' ) );
		self::assertSame( 2, self::count_class( $xpath, 'wtb-value__title' ) );
		self::assertStringContainsString( 'Courier to the door', $html );

		// The band has no heading of its own, so an <h4> here would jump from
		// the hero's <h1> to level four. See value-band.php.
		self::assertSame(
			0,
			$xpath->query( "//*[contains(concat(' ', normalize-space(@class), ' '), ' wtb-value ')]//h4" )->length,
			'the value items must not be headings — the band has no heading above them.'
		);
	}

	public function test_the_promo_is_absent_until_it_has_a_heading(): void {
		// Everything BUT the heading: the section still must not render, since
		// a promo with art and a button and nothing to say is not a promo.
		set_theme_mod( 'front_promo_text', 'Body copy with no heading above it' );
		set_theme_mod( 'front_promo_cta_label', 'Shop the edit' );
		set_theme_mod( 'front_promo_cta_url', 'https://example.com/edit' );

		$html  = $this->render();
		$xpath = self::xpath( $html );

		self::assertSame( 0, self::count_class( $xpath, 'wtb-promo' ) );
		self::assertStringNotContainsString( 'Body copy with no heading above it', $html );
	}

	public function test_the_promo_renders_its_copy_button_and_plate(): void {
		set_theme_mod( 'front_promo_title', 'The kitchen edit' );
		set_theme_mod( 'front_promo_text', 'Twelve things that make a kitchen work' );
		set_theme_mod( 'front_promo_cta_label', 'Shop the edit' );
		set_theme_mod( 'front_promo_cta_url', 'https://example.com/edit' );

		$html  = $this->render();
		$xpath = self::xpath( $html );

		self::assertSame( 1, self::count_class( $xpath, 'wtb-promo' ) );
		self::assertStringContainsString( 'The kitchen edit', $html );
		self::assertStringContainsString( 'Twelve things that make a kitchen work', $html );
		self::assertSame(
			1,
			$xpath->query( "//a[@href='https://example.com/edit']" )->length,
			'the promo button is missing.'
		);
		self::assertSame(
			1,
			self::count_class( $xpath, 'wtb-plate--promo' ),
			'with no promo image set, the art column must render the plate rather than an empty box.'
		);
	}

	public function test_the_promo_button_needs_both_a_label_and_a_url(): void {
		set_theme_mod( 'front_promo_title', 'The kitchen edit' );
		set_theme_mod( 'front_promo_cta_label', 'Shop the edit' );

		$html  = $this->render();
		$xpath = self::xpath( $html );

		self::assertSame( 1, self::count_class( $xpath, 'wtb-promo' ) );
		self::assertSame(
			0,
			self::count_class( $xpath, 'wtb-promo__cta' ),
			'a button with a label and nowhere to go must not render.'
		);
	}

	public function test_product_picks_are_absent_without_woocommerce(): void {
		$xpath = self::xpath( $this->render() );

		self::assertSame( 0, self::count_class( $xpath, 'wtb-front-products' ) );
	}

	public function test_the_journal_renders_the_three_newest_posts(): void {
		self::factory()->post->create_many(
			4,
			[
				'post_title'   => 'Journal item',
				'post_excerpt' => 'A real excerpt.',
			]
		);

		$html  = $this->render();
		$xpath = self::xpath( $html );

		self::assertSame( 1, self::count_class( $xpath, 'wtb-front-journal' ) );
		self::assertSame( 3, self::count_class( $xpath, 'wtb-front-editorial__card' ) );
		self::assertSame( 3, self::count_class( $xpath, 'wtb-plate--post-a' ) + self::count_class( $xpath, 'wtb-plate--post-b' ) + self::count_class( $xpath, 'wtb-plate--post-c' ) );
	}

	public function test_newsletter_is_absent_when_the_shortcode_is_unregistered(): void {
		set_theme_mod( 'front_newsletter_shortcode', '[not_registered_form]' );

		$html = $this->render();

		self::assertStringNotContainsString( 'not_registered_form', $html );
		self::assertSame( 0, self::count_class( self::xpath( $html ), 'wtb-front-newsletter' ) );
	}

	public function test_newsletter_rejects_a_registered_shortcode_followed_by_an_unregistered_one(): void {
		add_shortcode( 'test_newsletter_form', static fn (): string => '<form></form>' );
		set_theme_mod( 'front_newsletter_shortcode', '[test_newsletter_form][not_registered_form]' );

		$html = $this->render();

		remove_shortcode( 'test_newsletter_form' );

		self::assertSame( 0, self::count_class( self::xpath( $html ), 'wtb-front-newsletter' ) );
		self::assertStringNotContainsString( 'not_registered_form', $html );
	}

	public function test_newsletter_wraps_registered_shortcode_output(): void {
		add_shortcode( 'test_newsletter_form', static fn (): string => '<form><input type="email" /></form>' );
		set_theme_mod( 'front_newsletter_shortcode', '[test_newsletter_form]' );

		$html = $this->render();

		remove_shortcode( 'test_newsletter_form' );

		self::assertSame( 1, self::count_class( self::xpath( $html ), 'wtb-front-newsletter' ) );
		self::assertStringContainsString( '<form>', $html );
	}

	/**
	 * The tile plate, without WooCommerce.
	 *
	 * The integration harness has no WooCommerce (FrontPageTest asserts that
	 * outright), so `product_cat` is registered here for the length of the
	 * test. That is not a fake of WooCommerce: category-tiles.php's own
	 * contract is `taxonomy_exists( 'product_cat' )` plus `get_terms()`, and
	 * this exercises exactly that contract. The real WooCommerce path — the
	 * tiles' count, links and product counts — is covered on :8891 by the
	 * e2e:woo suite, where a real store exists.
	 */
	public function test_a_category_without_a_thumbnail_gets_a_plate(): void {
		register_taxonomy( 'product_cat', 'post', [ 'public' => true ] );

		$term_id = self::factory()->term->create(
			[
				'taxonomy' => 'product_cat',
				'name'     => 'Kitchen',
			]
		);
		$post_id = self::factory()->post->create();
		wp_set_object_terms( $post_id, [ $term_id ], 'product_cat' );

		$xpath = self::xpath( $this->render() );

		unregister_taxonomy( 'product_cat' );

		self::assertSame( 1, self::count_class( $xpath, 'wtb-cat-tile' ) );
		self::assertSame(
			1,
			self::count_class( $xpath, 'bg--plate' ),
			'a category with no thumbnail_id must still have art — the plate.'
		);
		// Scoped INSIDE the tile on purpose: the hero above renders a plate of
		// its own on this same page, so an unscoped count of `.wtb-plate`
		// answers a different question than the one this test is asking.
		self::assertSame(
			1,
			$xpath->query(
				"//*[contains(concat(' ', normalize-space(@class), ' '), ' bg--plate ')]"
				. "//*[contains(concat(' ', normalize-space(@class), ' '), ' wtb-plate ')]"
			)->length
		);
	}
}
