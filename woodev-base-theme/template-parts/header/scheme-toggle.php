<?php
/**
 * Colour-scheme switcher: a sun/moon button that flips `.light`/`.dark` on
 * `<html>` and remembers the visitor's choice in `localStorage['wtb-scheme']`
 * (spec §6). Both header variants reserve a slot for this in
 * `.wtb-header__actions`.
 *
 * Renders nothing when the admin has turned the front-end toggle off — a
 * control that cannot work must not be offered (Scheme::toggle_enabled()).
 *
 * Progressive enhancement mirrors template-parts/header/navigation.php: the
 * button starts `hidden` (a safety net that holds even if CSS fails to load)
 * and is only revealed once wtbSchemeToggle()'s init() marks it
 * `.wtb-scheme-toggle--enhanced` —
 * see the matching rule in src/css/adapter/index.css and
 * docs/gotchas/tailwind-v4-layer-precedence.md for why that `display` rule
 * has to live in the adapter layer rather than as a utility class.
 *
 * The two accessible-name strings are resolved here (translated, text domain
 * `woodev-base-theme`) and handed to the named Alpine component as data —
 * `wtbSchemeToggle()` in src/js/app.js swaps the active one via
 * `x-bind:aria-label` as the resolved scheme changes, so the name always
 * describes the action a click would perform, not the icon's current state.
 *
 * @package Woodev\Theme\Base
 */

declare(strict_types=1);

use Woodev\Theme\Base\Scheme;

if ( ! Scheme::toggle_enabled() ) {
	return;
}

$labels = [
	'toLight' => __( 'Switch to light theme', 'woodev-base-theme' ),
	'toDark'  => __( 'Switch to dark theme', 'woodev-base-theme' ),
];
?>
<button
	type="button"
	class="wtb-scheme-toggle"
	hidden
	x-data="wtbSchemeToggle( <?php echo esc_attr( wp_json_encode( $labels ) ); ?> )"
	x-bind:hidden="false"
	x-bind:aria-label="label"
	x-on:click="toggle()"
>
	<span class="wtb-scheme-toggle__icon" x-show="! dark"><?php woodev_base_icon( 'sun', [ 'size' => 20 ] ); ?></span>
	<span class="wtb-scheme-toggle__icon" x-show="dark"><?php woodev_base_icon( 'moon', [ 'size' => 20 ] ); ?></span>
</button>
