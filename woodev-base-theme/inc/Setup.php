<?php
/**
 * Theme setup: supports, i18n, menus.
 *
 * @package Woodev\Theme\Base
 */

declare(strict_types=1);

namespace Woodev\Theme\Base;

/**
 * Registers core theme support, i18n loading, and navigation menus.
 */
final class Setup {

	/**
	 * Hook theme setup into WordPress.
	 */
	public function register(): void {
		add_action( 'after_setup_theme', [ $this, 'setup' ] );
		add_action( 'widgets_init', [ $this, 'register_widget_areas' ] );
	}

	/**
	 * Declare theme supports, load translations, register nav menus.
	 */
	public function setup(): void {
		add_theme_support( 'title-tag' );
		add_theme_support( 'post-thumbnails' );
		add_theme_support( 'automatic-feed-links' );
		add_theme_support(
			'html5',
			[ 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ]
		);

		load_theme_textdomain( 'woodev-base-theme', get_template_directory() . '/languages' );

		register_nav_menus(
			[
				'primary' => __( 'Primary Menu', 'woodev-base-theme' ),
				'footer'  => __( 'Footer Menu', 'woodev-base-theme' ),
			]
		);
	}

	/**
	 * Register the sidebar and footer widget areas.
	 */
	public function register_widget_areas(): void {
		register_sidebar(
			[
				'id'            => 'sidebar-1',
				'name'          => __( 'Sidebar', 'woodev-base-theme' ),
				'description'   => __( 'Shown beside blog, archive and single-post content when the sidebar layout is active.', 'woodev-base-theme' ),
				'before_widget' => '<section id="%1$s" class="wtb-widget %2$s">',
				'after_widget'  => '</section>',
				'before_title'  => '<h2 class="wtb-widget__title">',
				'after_title'   => '</h2>',
			]
		);

		for ( $column = 1; $column <= 3; $column++ ) {
			register_sidebar(
				[
					'id'            => 'footer-' . $column,
					/* translators: %d: footer column number. */
					'name'          => \sprintf( __( 'Footer column %d', 'woodev-base-theme' ), $column ),
					'before_widget' => '<section id="%1$s" class="wtb-widget %2$s">',
					'after_widget'  => '</section>',
					'before_title'  => '<h2 class="wtb-widget__title">',
					'after_title'   => '</h2>',
				]
			);
		}
	}
}
