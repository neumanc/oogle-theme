<?php
/**
 * Title: Testimonials
 * Slug: oogle/testimonials
 * Categories: oogle-sections
 * Viewport Width: 1400
 * Description: Heading plus a responsive grid of quotes in cards. Each quote is a core Quote block with the "Testimonial" style; the citation carries the name and context. For quotes reused on several pages, convert one to a synced pattern.
 *
 * @package Oogle
 */
$oogle_quotes = array(
	array( __( 'A specific, believable sentence or two about the result. Vague praise convinces nobody; a detail does.', 'oogle' ), __( 'First Last, City', 'oogle' ) ),
	array( __( 'Another short quote. Keep the cards roughly the same length so the grid stays tidy.', 'oogle' ), __( 'First Last, City', 'oogle' ) ),
	array( __( 'A third quote. Three is a good number; six starts to feel like a wall.', 'oogle' ), __( 'First Last, City', 'oogle' ) ),
);
?>
<!-- wp:group {"align":"full","className":"oogle-section","style":{"spacing":{"padding":{"top":"var:preset|spacing|70","bottom":"var:preset|spacing|70"},"blockGap":"var:preset|spacing|60"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull oogle-section" style="padding-top:var(--wp--preset--spacing--70);padding-bottom:var(--wp--preset--spacing--70)">
	<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|30"}},"layout":{"type":"constrained","contentSize":"40rem"}} -->
	<div class="wp-block-group">
		<!-- wp:paragraph {"align":"center","className":"is-style-eyebrow"} -->
		<p class="has-text-align-center is-style-eyebrow"><?php esc_html_e( 'Testimonials', 'oogle' ); ?></p>
		<!-- /wp:paragraph -->
		<!-- wp:heading {"textAlign":"center"} -->
		<h2 class="wp-block-heading has-text-align-center"><?php esc_html_e( 'What clients say', 'oogle' ); ?></h2>
		<!-- /wp:heading -->
	</div>
	<!-- /wp:group -->

	<!-- wp:group {"align":"wide","layout":{"type":"grid","minimumColumnWidth":"20rem"},"style":{"spacing":{"blockGap":"var:preset|spacing|50"}}} -->
	<div class="wp-block-group alignwide">
		<?php foreach ( $oogle_quotes as $oogle_q ) : ?>
		<!-- wp:group {"className":"is-style-card","layout":{"type":"constrained"}} -->
		<div class="wp-block-group is-style-card">
			<!-- wp:quote {"className":"is-style-testimonial"} -->
			<blockquote class="wp-block-quote is-style-testimonial">
				<!-- wp:paragraph -->
				<p><?php echo esc_html( $oogle_q[0] ); ?></p>
				<!-- /wp:paragraph -->
				<cite><?php echo esc_html( $oogle_q[1] ); ?></cite>
			</blockquote>
			<!-- /wp:quote -->
		</div>
		<!-- /wp:group -->
		<?php endforeach; ?>
	</div>
	<!-- /wp:group -->
</div>
<!-- /wp:group -->
