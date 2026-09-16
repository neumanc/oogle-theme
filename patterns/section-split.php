<?php
/**
 * Title: Content + image
 * Slug: oogle/section-split
 * Categories: oogle-sections
 * Viewport Width: 1400
 * Description: Two-column section: text (7/12) beside an image (5/12). Columns stack on narrow viewports. Apply the "Reverse when stacked" columns style to keep the image first on mobile when it is second on desktop.
 *
 * @package Oogle
 */
?>
<!-- wp:group {"align":"full","className":"oogle-section","style":{"spacing":{"padding":{"top":"var:preset|spacing|70","bottom":"var:preset|spacing|70"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull oogle-section" style="padding-top:var(--wp--preset--spacing--70);padding-bottom:var(--wp--preset--spacing--70)">
	<!-- wp:columns {"verticalAlignment":"center","align":"wide","style":{"spacing":{"blockGap":{"left":"var:preset|spacing|70"}}}} -->
	<div class="wp-block-columns alignwide are-vertically-aligned-center">
		<!-- wp:column {"verticalAlignment":"center","width":"58.333%"} -->
		<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:58.333%">
			<!-- wp:paragraph {"className":"is-style-eyebrow"} -->
			<p class="is-style-eyebrow"><?php esc_html_e( 'Eyebrow label', 'oogle' ); ?></p>
			<!-- /wp:paragraph -->

			<!-- wp:heading -->
			<h2 class="wp-block-heading"><?php esc_html_e( 'Section heading', 'oogle' ); ?></h2>
			<!-- /wp:heading -->

			<!-- wp:paragraph -->
			<p><?php esc_html_e( 'Two or three short paragraphs. Say what this is, who it is for and what the reader should do about it. Avoid filler — the image carries the mood, the text carries the facts.', 'oogle' ); ?></p>
			<!-- /wp:paragraph -->

			<!-- wp:list {"className":"is-style-checklist"} -->
			<ul class="wp-block-list is-style-checklist">
				<!-- wp:list-item --><li><?php esc_html_e( 'A concrete benefit', 'oogle' ); ?></li><!-- /wp:list-item -->
				<!-- wp:list-item --><li><?php esc_html_e( 'Another concrete benefit', 'oogle' ); ?></li><!-- /wp:list-item -->
				<!-- wp:list-item --><li><?php esc_html_e( 'A third, if it earns its place', 'oogle' ); ?></li><!-- /wp:list-item -->
			</ul>
			<!-- /wp:list -->

			<!-- wp:buttons -->
			<div class="wp-block-buttons">
				<!-- wp:button {"className":"is-style-outline"} -->
				<div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="#"><?php esc_html_e( 'Learn how it works', 'oogle' ); ?></a></div>
				<!-- /wp:button -->
			</div>
			<!-- /wp:buttons -->
		</div>
		<!-- /wp:column -->

		<!-- wp:column {"verticalAlignment":"center","width":"41.667%"} -->
		<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:41.667%">
			<!-- wp:image {"aspectRatio":"4/5","scale":"cover","sizeSlug":"large"} -->
			<figure class="wp-block-image size-large"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/img/placeholder.svg' ) ); ?>" alt="<?php esc_attr_e( 'Describe this image', 'oogle' ); ?>" style="aspect-ratio:4/5;object-fit:cover"/></figure>
			<!-- /wp:image -->
		</div>
		<!-- /wp:column -->
	</div>
	<!-- /wp:columns -->
</div>
<!-- /wp:group -->
