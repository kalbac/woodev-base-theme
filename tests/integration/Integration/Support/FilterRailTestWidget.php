<?php
/**
 * A minimal, self-contained widget for filling `sidebar-shop` in tests.
 *
 * @package Woodev\Theme\Base\Tests\Integration\Support
 */

declare(strict_types=1);

namespace Woodev\Theme\Base\Tests\Integration\Support;

use WP_Widget;

/**
 * WooCommerce's own filter widgets would work just as well for filling
 * `sidebar-shop`, but their output depends on real product/attribute/price
 * data existing in the store (F1 in the parent plan, not yet seeded — see
 * docs/plans/2026-07-28-catalogue-and-product.md). Woo\FilterRailTest is only
 * about the rail's OWN chrome — the `.wtb-filter-group` wrapper
 * Setup::register_widget_areas() gives every widget dropped into
 * `sidebar-shop`, regardless of which one it is — so a disposable widget with
 * fixed output is the more direct proof.
 */
final class FilterRailTestWidget extends WP_Widget {

	public function __construct() {
		parent::__construct( 'wtb_test_filter_widget', 'Test Filter Widget' );
	}

	/**
	 * @param array<string, string> $args     Sidebar-provided before/after strings.
	 * @param array<string, mixed>  $instance Saved widget instance data (unused).
	 */
	public function widget( $args, $instance ): void {
		echo $args['before_widget'];
		echo $args['before_title'] . 'Test filter' . $args['after_title'];
		echo '<p>Kettles</p>';
		echo $args['after_widget'];
	}
}
