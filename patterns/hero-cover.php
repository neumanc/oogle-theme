<?php
/**
 * Title: Hero (cover)
 * Slug: oogle/hero-cover
 * Categories: oogle-sections
 * Block Types: core/cover
 * Viewport Width: 1400
 * Description: Full-width photographic hero with an H1, a lead paragraph and two calls to action. Use on pages that use the "Page (no title)" template — this pattern carries the page's only H1. The Cover carries the class "oogle-lcp" so the theme marks its image as the LCP element, and uses the "scrim" gradient preset as its overlay (a preset, not CSS, because preset color classes are !important in core). The outline button carries no text-color preset: the block style owns its colours in every state (a preset class would pin the text colour with !important and defeat the hover state).
 *
 * @package Oogle
 */
?>
<!-- wp:cover {"url":"<?php echo esc_url( get_theme_file_uri( 'assets/img/placeholder.svg' ) ); ?>","isUserOverlayColor":true,"minHeight":70,"minHeightUnit":"vh","gradient":"scrim","align":"full","className":"oogle-lcp","style":{"spacing":{"padding":{"top":"var:preset|spacing|80","bottom":"var:preset|spacing|80"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-cover alignfull oogle-lcp" style="padding-top:var(--wp--preset--spacing--80);padding-bottom:var(--wp--preset--spacing--80);min-height:70vh"><img class="wp-block-cover__image-background" alt="" src="<?php echo esc_url( get_theme_file_uri( 'assets/img/placeholder.svg' ) ); ?>" data-object-fit="cover"/><span aria-hidden="true" class="wp-block-cover__background has-background-dim-100 has-background-dim wp-block-cover__gradient-background has-background-gradient has-scrim-gradient-background"></span><div class="wp-block-cover__inner-container">
	<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|40"}},"layout":{"type":"constrained","contentSize":"40rem","justifyContent":"left"}} -->
	<div class="wp-block-group">
		<!-- wp:paragraph {"className":"is-style-eyebrow","textColor":"base"} -->
		<p class="is-style-eyebrow has-base-color has-text-color"><?php esc_html_e( 'Eyebrow label', 'oogle' ); ?></p>
		<!-- /wp:paragraph -->

		<!-- wp:heading {"level":1,"textColor":"base","fontSize":"display"} -->
		<h1 class="wp-block-heading has-base-color has-text-color has-display-font-size"><?php esc_html_e( 'A headline that says what you do and for whom', 'oogle' ); ?></h1>
		<!-- /wp:heading -->

		<!-- wp:paragraph {"className":"is-style-lead","textColor":"base"} -->
		<p class="is-style-lead has-base-color has-text-color"><?php esc_html_e( 'One or two sentences of supporting copy. Keep it concrete: what happens next, and why it is worth it.', 'oogle' ); ?></p>
		<!-- /wp:paragraph -->

		<!-- wp:buttons {"style":{"spacing":{"blockGap":"var:preset|spacing|30"}}} -->
		<div class="wp-block-buttons">
			<!-- wp:button -->
			<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="#"><?php esc_html_e( 'Primary action', 'oogle' ); ?></a></div>
			<!-- /wp:button -->
			<!-- wp:button {"className":"is-style-outline"} -->
			<div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="#"><?php esc_html_e( 'Secondary action', 'oogle' ); ?></a></div>
			<!-- /wp:button -->
		</div>
		<!-- /wp:buttons -->
	</div>
	<!-- /wp:group -->
</div></div>
<!-- /wp:cover -->
