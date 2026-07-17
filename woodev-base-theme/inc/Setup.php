<?php
/**
 * Theme setup: supports, i18n, menus.
 *
 * @package Woodev\Theme\Base
 */

declare(strict_types=1);

namespace Woodev\Theme\Base;

final class Setup {

	public function register(): void {
		add_action( 'after_setup_theme', [ $this, 'setup' ] );
	}

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
			[ 'primary' => __( 'Primary Menu', 'woodev-base-theme' ) ]
		);
	}
}
