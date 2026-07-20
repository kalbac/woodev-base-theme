<?php
/**
 * Shared primary navigation, used by both header variants.
 *
 * ONE menu in the DOM serves both desktop (inline, CSS-only submenu reveal on
 * hover/focus-within) and mobile (an Alpine disclosure drawer with a focus
 * trap). Progressive enhancement is the contract: with JS off the menu is fully
 * visible and the toggle stays hidden; Alpine only ENHANCES. The nav marks
 * itself `.wtb-nav--enhanced` on init, and the responsive CSS keys the
 * mobile-collapse behaviour on that class — so the no-JS path never depends on
 * Alpine having run. See docs/plans M1-02 Task 4.
 *
 * @package Woodev\Theme\Base
 */

declare(strict_types=1);

if ( ! has_nav_menu( 'primary' ) ) {
	return;
}
?>
<nav
	class="wtb-nav"
	aria-label="<?php esc_attr_e( 'Primary', 'woodev-base-theme' ); ?>"
	x-data="{ open: false }"
	x-init="$el.classList.add('wtb-nav--enhanced')"
	x-bind:class="{ 'wtb-nav--open': open }"
	x-on:keydown.escape="open = false; $nextTick(() => $refs.toggle.focus())"
>
	<button
		type="button"
		class="wtb-nav__toggle"
		x-ref="toggle"
		aria-label="<?php esc_attr_e( 'Menu', 'woodev-base-theme' ); ?>"
		aria-controls="wtb-primary-menu"
		x-bind:aria-expanded="open"
		x-on:click="open = ! open"
		x-bind:hidden="false"
		hidden
	>
		<span class="wtb-nav__toggle-icon" x-show="! open"><?php woodev_base_icon( 'menu' ); ?></span>
		<span class="wtb-nav__toggle-icon" x-show="open"><?php woodev_base_icon( 'x' ); ?></span>
	</button>

	<?php
	wp_nav_menu(
		[
			'theme_location' => 'primary',
			'container'      => false,
			'menu_id'        => 'wtb-primary-menu',
			'menu_class'     => 'wtb-nav__menu',
			'fallback_cb'    => false,
			// x-trap traps focus inside the drawer while `open` (mobile only — on
			// desktop `open` never becomes true, so it stays inert). `.noscroll`
			// locks body scroll; `.noreturn` disables x-trap's own focus
			// restoration so the nav can return focus to the toggle itself on
			// Escape — via $nextTick, so the focus() call lands AFTER the trap has
			// torn down (focusing an outside element while the trap is still active
			// gets redirected back into the drawer). Verified in navigation.spec.mjs.
			'items_wrap'     => '<ul id="%1$s" class="%2$s" x-trap.noscroll.noreturn="open">%3$s</ul>',
		]
	);
	?>
</nav>
