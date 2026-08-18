<?php
/**
 * Small server-rendered breadcrumb helper for content templates.
 *
 * @package Woodev\Theme\Base
 */

declare(strict_types=1);

namespace Woodev\Theme\Base\Templates;

defined( 'ABSPATH' ) || exit;

/**
 * Builds accessible breadcrumbs from WordPress's current query context.
 */
final class Breadcrumbs {

	/**
	 * Echo the current view's breadcrumb trail when it has a meaningful parent.
	 */
	public static function render(): void {
		$items = self::items();

		if ( count( $items ) < 2 ) {
			return;
		}

		echo '<nav class="wtb-breadcrumbs" aria-label="' . esc_attr__( 'Breadcrumbs', 'woodev-base-theme' ) . '"><ol>';

		foreach ( $items as $item ) {
			echo '<li>';

			if ( null === $item['url'] ) {
				echo '<span aria-current="page">' . esc_html( $item['label'] ) . '</span>';
			} else {
				echo '<a href="' . esc_url( $item['url'] ) . '">' . esc_html( $item['label'] ) . '</a>';
			}

			echo '</li>';
		}

		echo '</ol></nav>';
	}

	/**
	 * Build the current view's breadcrumb items.
	 *
	 * @return list<array{label: string, url: string|null}>
	 */
	private static function items(): array {
		$items = [
			[
				'label' => __( 'Home', 'woodev-base-theme' ),
				'url'   => home_url( '/' ),
			],
		];

		if ( is_singular( 'post' ) ) {
			$categories = get_the_category();
			$category   = $categories[0] ?? null;

			if ( $category instanceof \WP_Term ) {
				$url = get_category_link( $category->term_id );

				if ( ! is_wp_error( $url ) ) {
					$items[] = [
						'label' => $category->name,
						'url'   => $url,
					];
				}
			}

			$items[] = [
				'label' => get_the_title(),
				'url'   => null,
			];

			return $items;
		}//end if

		if ( is_page() ) {
			$post_id = get_the_ID();

			if ( false === $post_id ) {
				return $items;
			}

			foreach ( array_reverse( get_post_ancestors( $post_id ) ) as $ancestor_id ) {
				$url = get_permalink( $ancestor_id );

				if ( false === $url ) {
					continue;
				}

				$items[] = [
					'label' => get_the_title( $ancestor_id ),
					'url'   => $url,
				];
			}

			$items[] = [
				'label' => get_the_title(),
				'url'   => null,
			];

			return $items;
		}//end if

		if ( is_category() ) {
			$category = get_queried_object();

			if ( $category instanceof \WP_Term ) {
				$items[] = [
					'label' => $category->name,
					'url'   => null,
				];
			}
		}

		return $items;
	}
}
