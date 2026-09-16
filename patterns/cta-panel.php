<?php
/**
 * Title: Call to action
 * Slug: oogle/cta-panel
 * Categories: oogle-sections
 * Viewport Width: 1400
 * Description: Closing call-to-action section: heading, one sentence, a primary button and a secondary text link. Every page should end with one.
 *
 * @package Oogle
 */
?>
<!-- wp:group {"align":"full","className":"is-style-section-reversed","style":{"spacing":{"padding":{"top":"var:preset|spacing|70","bottom":"var:preset|spacing|70"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull is-style-section-reversed" style="padding-top:var(--wp--preset--spacing--70);padding-bottom:var(--wp--preset--spacing--70)">
	<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|40"}},"layout":{"type":"constrained","contentSize":"40rem"}} -->
	<div class="wp-block-group">
		<!-- wp:heading {"textAlign":"center"} -->
		<h2 class="wp-block-heading has-text-align-center"><?php esc_html_e( 'Ready to talk about your project?', 'oogle' ); ?></h2>
		<!-- /wp:heading -->
		<!-- wp:paragraph {"align":"center","className":"is-style-lead"} -->
		<p class="has-text-align-center is-style-lead"><?php esc_html_e( 'One sentence on what happens when they get in touch — and how quickly.', 'oogle' ); ?></p>
		<!-- /wp:paragraph -->
		<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"},"style":{"spacing":{"blockGap":"var:preset|spacing|30"}}} -->
		<div class="wp-block-buttons">
			<!-- wp:button -->
			<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="#"><?php esc_html_e( 'Primary action', 'oogle' ); ?></a></div>
			<!-- /wp:button -->
			<!-- wp:button {"className":"is-style-text"} -->
			<div class="wp-block-button is-style-text"><a class="wp-block-button__link wp-element-button" href="tel:+10000000000"><?php esc_html_e( 'Or call 000-000-0000', 'oogle' ); ?></a></div>
			<!-- /wp:button -->
		</div>
		<!-- /wp:buttons -->
	</div>
	<!-- /wp:group -->
</div>
<!-- /wp:group -->
