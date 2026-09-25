<?php
/**
 * Title: Fixture marker
 * Slug: oogle-fixture-child/marker
 * Categories: oogle-fixture-child
 * Description: A child-owned pattern in the child's own namespace, using a parent block style.
 *
 * @package OogleFixtureChild
 */

?>
<!-- wp:group {"className":"is-style-card oogle-fixture-marker","layout":{"type":"constrained"}} -->
<div class="wp-block-group is-style-card oogle-fixture-marker">
	<!-- wp:paragraph -->
	<p><?php esc_html_e( 'A card styled by the parent, valued by the child.', 'oogle-fixture-child' ); ?></p>
	<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
