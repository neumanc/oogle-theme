<?php
/**
 * Title: Hero (split)
 * Slug: oogle/hero-split
 * Categories: oogle-sections
 * Viewport Width: 1400
 * Description: Text beside a photo: eyebrow, H1, lead and two calls to action on the left, an image on the right. Use on pages that use the "Page (no title)" template — this pattern carries the page's only H1. Preferred over the cover hero when the photo is under ~1600px wide. The Image carries the class "oogle-lcp" so the theme marks it as the LCP element.
 *
 * @package Oogle
 */

?>
<!-- wp:group {"align":"full","className":"oogle-section","style":{"spacing":{"padding":{"top":"var:preset|spacing|70","bottom":"var:preset|spacing|70"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull oogle-section" style="padding-top:var(--wp--preset--spacing--70);padding-bottom:var(--wp--preset--spacing--70)">
	<!-- wp:columns {"verticalAlignment":"center","align":"wide","style":{"spacing":{"blockGap":{"left":"var:preset|spacing|70"}}}} -->
	<div class="wp-block-columns alignwide are-vertically-aligned-center">
		<!-- wp:column {"verticalAlignment":"center","width":"55%"} -->
		<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:55%">
			<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|40"}},"layout":{"type":"constrained","justifyContent":"left"}} -->
			<div class="wp-block-group">
				<!-- wp:paragraph {"className":"is-style-eyebrow"} -->
				<p class="is-style-eyebrow"><?php esc_html_e( 'Eyebrow label', 'oogle' ); ?></p>
				<!-- /wp:paragraph -->

				<!-- wp:heading {"level":1,"fontSize":"xxxx-large"} -->
				<h1 class="wp-block-heading has-xxxx-large-font-size"><?php esc_html_e( 'A headline that says what you do and for whom', 'oogle' ); ?></h1>
				<!-- /wp:heading -->

				<!-- wp:paragraph {"className":"is-style-lead"} -->
				<p class="is-style-lead"><?php esc_html_e( 'One or two sentences of supporting copy. Keep it concrete: what happens next, and why it is worth it.', 'oogle' ); ?></p>
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
		</div>
		<!-- /wp:column -->

		<!-- wp:column {"verticalAlignment":"center","width":"45%"} -->
		<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:45%">
			<!-- wp:image {"aspectRatio":"4/5","scale":"cover","sizeSlug":"large","className":"oogle-lcp"} -->
			<figure class="wp-block-image size-large oogle-lcp"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/img/placeholder.svg' ) ); ?>" alt="<?php esc_attr_e( 'Describe this image', 'oogle' ); ?>" style="aspect-ratio:4/5;object-fit:cover"/></figure>
			<!-- /wp:image -->
		</div>
		<!-- /wp:column -->
	</div>
	<!-- /wp:columns -->
</div>
<!-- /wp:group -->
