<?php
/**
 * Title: Card grid
 * Slug: oogle/cards-grid
 * Categories: oogle-sections
 * Viewport Width: 1400
 * Description: Intro text followed by a responsive grid of cards (image, heading, text, link). The grid uses a minimum column width, so it reflows from one to three-plus columns with no breakpoints. Duplicate a card to add one.
 *
 * @package Oogle
 */
$oogle_placeholder = esc_url( get_theme_file_uri( 'assets/img/placeholder.svg' ) );
?>
<!-- wp:group {"align":"full","className":"is-style-section-tint","style":{"spacing":{"padding":{"top":"var:preset|spacing|70","bottom":"var:preset|spacing|70"},"blockGap":"var:preset|spacing|60"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull is-style-section-tint" style="padding-top:var(--wp--preset--spacing--70);padding-bottom:var(--wp--preset--spacing--70)">
	<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|30"}},"layout":{"type":"constrained","contentSize":"40rem"}} -->
	<div class="wp-block-group">
		<!-- wp:paragraph {"className":"is-style-eyebrow"} -->
		<p class="is-style-eyebrow"><?php esc_html_e( 'Eyebrow label', 'oogle' ); ?></p>
		<!-- /wp:paragraph -->
		<!-- wp:heading -->
		<h2 class="wp-block-heading"><?php esc_html_e( 'What we can help with', 'oogle' ); ?></h2>
		<!-- /wp:heading -->
		<!-- wp:paragraph -->
		<p><?php esc_html_e( 'One sentence framing the set of cards below.', 'oogle' ); ?></p>
		<!-- /wp:paragraph -->
	</div>
	<!-- /wp:group -->

	<!-- wp:group {"align":"wide","layout":{"type":"grid","minimumColumnWidth":"20rem"},"style":{"spacing":{"blockGap":"var:preset|spacing|50"}}} -->
	<div class="wp-block-group alignwide">
		<?php for ( $oogle_i = 1; $oogle_i <= 3; $oogle_i++ ) : ?>
		<!-- wp:group {"className":"is-style-card-flush","style":{"spacing":{"blockGap":"0"}},"layout":{"type":"constrained"}} -->
		<div class="wp-block-group is-style-card-flush">
			<!-- wp:image {"aspectRatio":"3/2","scale":"cover","sizeSlug":"large"} -->
			<figure class="wp-block-image size-large"><img src="<?php echo $oogle_placeholder; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?>" alt="<?php esc_attr_e( 'Describe this image', 'oogle' ); ?>" style="aspect-ratio:3/2;object-fit:cover"/></figure>
			<!-- /wp:image -->
			<!-- wp:group {"style":{"spacing":{"padding":{"top":"var:preset|spacing|50","right":"var:preset|spacing|50","bottom":"var:preset|spacing|50","left":"var:preset|spacing|50"},"blockGap":"var:preset|spacing|30"}},"layout":{"type":"constrained"}} -->
			<div class="wp-block-group" style="padding-top:var(--wp--preset--spacing--50);padding-right:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--50);padding-left:var(--wp--preset--spacing--50)">
				<!-- wp:heading {"level":3,"fontSize":"x-large"} -->
				<h3 class="wp-block-heading has-x-large-font-size"><?php echo esc_html( sprintf( /* translators: %d: card number */ __( 'Card heading %d', 'oogle' ), $oogle_i ) ); ?></h3>
				<!-- /wp:heading -->
				<!-- wp:paragraph -->
				<p><?php esc_html_e( 'Two sentences at most. What it is and why it matters to the reader.', 'oogle' ); ?></p>
				<!-- /wp:paragraph -->
				<!-- wp:buttons -->
				<div class="wp-block-buttons">
					<!-- wp:button {"className":"is-style-text"} -->
					<div class="wp-block-button is-style-text"><a class="wp-block-button__link wp-element-button" href="#"><?php esc_html_e( 'Read more about this', 'oogle' ); ?></a></div>
					<!-- /wp:button -->
				</div>
				<!-- /wp:buttons -->
			</div>
			<!-- /wp:group -->
		</div>
		<!-- /wp:group -->
		<?php endfor; ?>
	</div>
	<!-- /wp:group -->
</div>
<!-- /wp:group -->
