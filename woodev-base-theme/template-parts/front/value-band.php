<?php
/**
 * Front-page value band — mockup §05, CSS ported in T4 as part of
 * `src/css/adapter/blocks.css`.
 *
 * The mockup fills this band with one shop's promises ("Возврат 30 дней",
 * "Упаковка без пластика"). Unlike the category tiles above it, none of that
 * has a source in the site: WordPress does not know a store's delivery terms,
 * and a theme that ships them as defaults is asserting facts about somebody
 * else's business. So the items are a Customizer setting that defaults to
 * empty, and this part renders nothing at all until an admin fills it in.
 *
 * @package Woodev\Theme\Base
 */

declare(strict_types=1);

// Direct access to a theme file runs outside WordPress: the fatal that follows
// prints a path. Fail closed instead.
defined( 'ABSPATH' ) || exit;

use Woodev\Theme\Base\Customizer\Settings;

$wtb_value_items = Settings::front_value_items();

if ( [] === $wtb_value_items ) {
	return;
}
?>
<section class="wtb-front-section">
	<?php /* No .wtb-container: header.php already opens main.wtb-container. */ ?>
	<div class="wtb-value-band">
		<?php foreach ( $wtb_value_items as $wtb_item ) : ?>
			<div class="wtb-value">
				<span class="ico"><?php woodev_base_icon( $wtb_item['icon'], [ 'size' => 20 ] ); ?></span>
				<div>
					<?php
					/*
					 * A paragraph, not the mockup's <h4>. The band carries no
					 * heading of its own, so an <h4> here would jump straight
					 * from the hero's <h1> to level four — a heading order a
					 * screen reader reports as two missing levels, for items
					 * that are labels rather than document sections. The look
					 * is unchanged: blocks.css styles the class alongside the
					 * element it replaced.
					 */
					?>
					<p class="wtb-value__title"><?php echo esc_html( $wtb_item['title'] ); ?></p>
					<?php if ( '' !== $wtb_item['text'] ) : ?>
						<p><?php echo esc_html( $wtb_item['text'] ); ?></p>
					<?php endif; ?>
				</div>
			</div>
		<?php endforeach; ?>
	</div>
</section>
