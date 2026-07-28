<?php
/**
 * The catalogue filter rail — chrome around WooCommerce's own filter widgets.
 *
 * Rendered by FilterRail::render(), which is only ever called once
 * FilterRail::is_active() has confirmed the `sidebar-shop` widget area has at
 * least one widget and the current view is a product archive. This file does
 * not re-check that.
 *
 * THE MOBILE COLLAPSE (A14) IS A PLAIN PANEL, NOT A `<details>`, and the two
 * rewrites it took to get here are worth recording because both intermediate
 * versions looked correct.
 *
 * A `<details>`/`<summary>` disclosure is the obvious JavaScript-free answer,
 * and it was the first one. Closed by default it is unusable: the non-summary
 * children are not rendered, `display: contents` on the `<details>` does not
 * undo that (measured — the children still lay out with real geometry, a group
 * reported 248x349, while `innerText` came back empty and the screenshot was
 * blank), so a desktop visitor with no JavaScript would get a "Filters" label
 * above nothing at all.
 *
 * Serving it `open` and closing it from JavaScript on narrow viewports fixed
 * the no-JS case and introduced a worse one: the grid row is sized while the
 * panel is still open, and the closed `<details>` keeps that size — the rail
 * measured 790px tall with a 45px summary inside it, i.e. a screen-height gap
 * under the "Filters" button. It reproduced only on a cold load (the browser
 * that had already been resized never showed it), and the element's own height
 * read 68px on one run and 166px on the next, which is the signature of a
 * layout that depends on WHEN the script ran.
 *
 * So the disclosure is ours: a static head, a panel, and — only once
 * src/js/woo.js runs — a real `<button aria-expanded>` swapped in for the
 * title. Without JavaScript the panel is simply visible, which is the correct
 * degraded state and needs no markup of its own. The Reset link sits in the
 * head beside the title rather than inside the toggle, because it is a
 * separate control and nesting one interactive element inside another is
 * invalid regardless of which element does the toggling.
 *
 * @package Woodev\Theme\Base\Woo
 */

declare(strict_types=1);

// Direct access to a theme file runs outside WordPress: the fatal that follows
// prints a path. Fail closed instead.
defined( 'ABSPATH' ) || exit;

use Woodev\Theme\Base\Woo\FilterRail;

$wtb_reset_url = FilterRail::reset_url();
?>
<aside class="wtb-filter-rail" aria-label="<?php esc_attr_e( 'Filters', 'woodev-base-theme' ); ?>">
	<div class="wtb-filter-rail__head">
		<span class="wtb-filter-rail__title">
			<?php woodev_base_icon( 'sliders-horizontal' ); ?>
			<?php esc_html_e( 'Filters', 'woodev-base-theme' ); ?>
		</span>
		<?php if ( '' !== $wtb_reset_url ) : ?>
			<?php
			/*
			 * `data-variant` / `data-size`, NOT the mockup's `btn--ghost
			 * btn--sm`. Those BEM modifiers are the MOCKUP's vocabulary; this
			 * theme keys its button variants off attributes instead, because
			 * Basecoat does (src/css/adapter/buttons.css explains the choice).
			 * Written the mockup's way first, the class did nothing at all and
			 * the link fell through to `.btn:not([data-variant])` — the primary
			 * button — so a quiet "Reset" rendered as a solid brown block above
			 * the filters. Caught in a screenshot, invisible to every gate,
			 * third instance this session of
			 * docs/gotchas/porting-a-mockup-inherits-its-class-names-and-loses-
			 * its-use-site.md.
			 */
			?>
			<a class="btn wtb-filter-rail__reset" data-variant="ghost" data-size="sm" href="<?php echo esc_url( $wtb_reset_url ); ?>">
				<?php esc_html_e( 'Reset', 'woodev-base-theme' ); ?>
			</a>
		<?php endif; ?>
	</div>
	<div class="wtb-filter-rail__panel" id="wtb-filter-rail-panel">
		<?php dynamic_sidebar( FilterRail::SIDEBAR_ID ); ?>
	</div>
</aside>
