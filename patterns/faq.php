<?php
/**
 * Title: FAQ
 * Slug: oogle/faq
 * Categories: oogle-sections
 * Viewport Width: 1400
 * Description: Heading plus a core Accordion of questions and answers. One question open at a time is the block's default; each item is a heading (H3) with a button, so it is keyboard and screen-reader ready without extra markup.
 *
 * @package Oogle
 */

$oogle_faq = array(
	array( __( 'A question a visitor asks before getting in touch?', 'oogle' ), __( 'A short, direct answer. Two or three sentences; link to a page for the long version.', 'oogle' ) ),
	array( __( 'Another common question?', 'oogle' ), __( 'Answer it the way you would on the phone: what happens, when, and what it depends on.', 'oogle' ) ),
	array( __( 'A third question?', 'oogle' ), __( 'Keep the list to the questions people really ask; five to eight is plenty.', 'oogle' ) ),
);
?>
<!-- wp:group {"align":"full","className":"oogle-section","style":{"spacing":{"padding":{"top":"var:preset|spacing|70","bottom":"var:preset|spacing|70"},"blockGap":"var:preset|spacing|60"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull oogle-section" style="padding-top:var(--wp--preset--spacing--70);padding-bottom:var(--wp--preset--spacing--70)">
	<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|30"}},"layout":{"type":"constrained","contentSize":"40rem"}} -->
	<div class="wp-block-group">
		<!-- wp:paragraph {"align":"center","className":"is-style-eyebrow"} -->
		<p class="has-text-align-center is-style-eyebrow"><?php esc_html_e( 'FAQ', 'oogle' ); ?></p>
		<!-- /wp:paragraph -->
		<!-- wp:heading {"textAlign":"center"} -->
		<h2 class="wp-block-heading has-text-align-center"><?php esc_html_e( 'Questions people ask', 'oogle' ); ?></h2>
		<!-- /wp:heading -->
	</div>
	<!-- /wp:group -->

	<!-- wp:group {"layout":{"type":"constrained"}} -->
	<div class="wp-block-group">
		<!-- wp:accordion -->
		<div role="group" class="wp-block-accordion">
			<?php foreach ( $oogle_faq as $oogle_item ) : ?>
			<!-- wp:accordion-item -->
			<div class="wp-block-accordion-item">
				<!-- wp:accordion-heading {"title":<?php echo wp_json_encode( $oogle_item[0], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); // JSON inside a block comment: JSON-escaped, never HTML-escaped. ?>,"showIcon":false} -->
				<h3 class="wp-block-accordion-heading"><button type="button" class="wp-block-accordion-heading__toggle"><span class="wp-block-accordion-heading__toggle-title"><?php echo esc_html( $oogle_item[0] ); ?></span></button></h3>
				<!-- /wp:accordion-heading -->
				<!-- wp:accordion-panel -->
				<div role="region" class="wp-block-accordion-panel">
					<!-- wp:paragraph -->
					<p><?php echo esc_html( $oogle_item[1] ); ?></p>
					<!-- /wp:paragraph -->
				</div>
				<!-- /wp:accordion-panel -->
			</div>
			<!-- /wp:accordion-item -->
			<?php endforeach; ?>
		</div>
		<!-- /wp:accordion -->
	</div>
	<!-- /wp:group -->
</div>
<!-- /wp:group -->
