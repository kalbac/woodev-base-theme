<?php
/**
 * FilterRail integration tests: the rail's real markup and conditions,
 * against a real WordPress + WooCommerce install with a real widget in
 * sidebar-shop.
 *
 * The base test env has NO WooCommerce active (same as Woo\BootstrapTest in
 * this directory), so every test here skips there — that is correct and
 * intended. This suite only proves something when it runs against an
 * environment with WooCommerce installed and active.
 *
 * @package Woodev\Theme\Base\Tests\Integration
 */

declare(strict_types=1);

namespace Woodev\Theme\Base\Tests\Integration\Woo;

use DOMDocument;
use DOMXPath;
use WP_UnitTestCase;
use Woodev\Theme\Base\Tests\Integration\Support\FilterRailTestWidget;
use Woodev\Theme\Base\Woo\FilterRail;
use Woodev\Theme\Base\Woo\Support;

final class FilterRailTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();

		if ( ! class_exists( 'WooCommerce' ) ) {
			self::markTestSkipped( 'WooCommerce not active in this environment.' );
		}

		register_widget( FilterRailTestWidget::class );
	}

	public function tear_down(): void {
		wp_set_sidebars_widgets( [] );
		delete_option( 'widget_wtb_test_filter_widget' );

		parent::tear_down();
	}

	/**
	 * Puts a real widget instance into sidebar-shop so is_active_sidebar()
	 * and dynamic_sidebar() both have something real to report on and
	 * render — a bare wp_set_sidebars_widgets() call naming a widget id that
	 * does not actually exist would make is_active_sidebar() lie.
	 */
	private function fill_shop_sidebar(): void {
		update_option(
			'widget_wtb_test_filter_widget',
			[
				2              => [],
				'_multiwidget' => 1,
			]
		);

		$sidebars_widgets                 = wp_get_sidebars_widgets();
		$sidebars_widgets['sidebar-shop'] = [ 'wtb_test_filter_widget-2' ];
		wp_set_sidebars_widgets( $sidebars_widgets );
	}

	public function test_is_active_is_false_on_the_shop_page_with_an_empty_sidebar(): void {
		$this->go_to( wc_get_page_permalink( 'shop' ) );

		self::assertFalse( FilterRail::is_active() );
	}

	public function test_is_active_is_true_on_the_shop_page_with_a_filled_sidebar(): void {
		$this->fill_shop_sidebar();
		$this->go_to( wc_get_page_permalink( 'shop' ) );

		self::assertTrue( FilterRail::is_active() );
	}

	/**
	 * A filled sidebar-shop must NOT turn the rail on everywhere — only on
	 * the product archive views it is meant for.
	 */
	public function test_is_active_is_false_on_a_non_archive_page_even_with_a_filled_sidebar(): void {
		$this->fill_shop_sidebar();
		$page_id = self::factory()->post->create( [ 'post_type' => 'page' ] );
		$this->go_to( (string) get_permalink( $page_id ) );

		self::assertFalse( FilterRail::is_active() );
	}

	/**
	 * Pins the exact markup shape Support's docblock and FilterRail's own
	 * docblock both describe: an <aside>, wrapping a <details>/<summary>
	 * disclosure, wrapping the widget rendered in the `.wtb-filter-group`
	 * shape Setup::register_widget_areas() declares.
	 */
	public function test_render_emits_the_disclosure_and_the_widget_as_a_filter_group(): void {
		$this->fill_shop_sidebar();
		$this->go_to( wc_get_page_permalink( 'shop' ) );

		ob_start();
		( new FilterRail() )->render();
		$html = (string) ob_get_clean();

		$xpath = self::xpath( $html );

		self::assertSame( 1, $xpath->query( '//aside[contains(@class,"wtb-filter-rail")]' )->length );
		self::assertSame( 1, $xpath->query( '//details[contains(@class,"wtb-filter-rail__disclosure")]' )->length );
		self::assertSame( 1, $xpath->query( '//summary[contains(@class,"wtb-filter-rail__head")]' )->length );
		self::assertSame( 1, $xpath->query( '//div[contains(@class,"wtb-filter-group")]' )->length );
		// The reset link must not be nested inside <summary> — a <summary> is
		// itself an interactive disclosure control, and nesting another
		// interactive element inside it is invalid, inaccessible markup.
		self::assertSame( 0, $xpath->query( '//summary//a' )->length );
		self::assertStringContainsString( 'Kettles', $html );
	}

	/**
	 * No filter query var is active in this request, so no Reset link
	 * should print at all.
	 */
	public function test_render_omits_the_reset_link_when_no_filter_is_active(): void {
		$this->fill_shop_sidebar();
		$this->go_to( wc_get_page_permalink( 'shop' ) );

		ob_start();
		( new FilterRail() )->render();
		$html = (string) ob_get_clean();

		self::assertStringNotContainsString( 'wtb-filter-rail__reset', $html );
	}

	public function test_render_prints_the_reset_link_when_a_filter_query_var_is_present(): void {
		$this->fill_shop_sidebar();
		$_GET['min_price'] = '500';
		$this->go_to( wc_get_page_permalink( 'shop' ) . '?min_price=500' );

		ob_start();
		( new FilterRail() )->render();
		$html = (string) ob_get_clean();

		unset( $_GET['min_price'] );

		self::assertStringContainsString( 'wtb-filter-rail__reset', $html );
	}

	public function test_columns_filter_yields_three_when_the_rail_is_active(): void {
		$this->fill_shop_sidebar();
		$this->go_to( wc_get_page_permalink( 'shop' ) );

		( new FilterRail() )->register();

		self::assertSame( 3, apply_filters( 'loop_shop_columns', 4 ) );
	}

	public function test_columns_filter_leaves_woos_default_alone_when_the_rail_is_inactive(): void {
		$this->go_to( wc_get_page_permalink( 'shop' ) );

		( new FilterRail() )->register();

		self::assertSame( 4, apply_filters( 'loop_shop_columns', 4 ) );
	}

	/**
	 * Support::open_wrapper() is the other half of the rail's placement:
	 * with the rail active, it must switch to the two-column grid and fire
	 * the rail between the grid's own opening tag and the results column.
	 */
	public function test_open_wrapper_switches_to_the_two_column_shop_layout_when_the_rail_is_active(): void {
		$this->fill_shop_sidebar();
		$this->go_to( wc_get_page_permalink( 'shop' ) );

		( new FilterRail() )->register();

		ob_start();
		( new Support() )->open_wrapper();
		$html = (string) ob_get_clean();

		self::assertStringStartsWith( '<div class="wtb-shop-layout"><aside class="wtb-filter-rail"', $html );
		self::assertStringEndsWith( '<div class="wtb-shop-layout__content">', $html );
	}

	/**
	 * Same shape as FrontPageSectionsTest::xpath(): loadHTML() warns loudly
	 * about HTML5 elements (details, summary, aside) it does not recognise in
	 * its default parser even though it handles them correctly, so warnings
	 * are suppressed via the libxml flags rather than left to fail the test
	 * on noise instead of the actual markup.
	 */
	private static function xpath( string $html ): DOMXPath {
		self::assertNotSame( '', $html, 'The captured render is empty.' );

		$dom      = new DOMDocument();
		$previous = libxml_use_internal_errors( true );

		try {
			$dom->loadHTML( '<body>' . $html . '</body>', LIBXML_NOWARNING | LIBXML_NOERROR );
		} finally {
			libxml_clear_errors();
			libxml_use_internal_errors( $previous );
		}

		return new DOMXPath( $dom );
	}
}
