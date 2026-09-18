<?php
/**
 * Title: 404 content
 * Slug: oogle/hidden-404
 * Inserter: false
 * Description: Body of the 404 template (heading, lead, search, home link). A pattern rather than template markup so the copy is translatable.
 *
 * @package Oogle
 */

?>
<!-- wp:heading {"level":1} -->
<h1 class="wp-block-heading"><?php esc_html_e( 'Page not found', 'oogle' ); ?></h1>
<!-- /wp:heading -->
<!-- wp:paragraph {"className":"is-style-lead"} -->
<p class="is-style-lead"><?php esc_html_e( 'The page you were looking for has moved or never existed. Try a search, or start from the homepage.', 'oogle' ); ?></p>
<!-- /wp:paragraph -->
<!-- wp:search {"showLabel":false} /-->
<!-- wp:buttons -->
<div class="wp-block-buttons">
	<!-- wp:button {"className":"is-style-outline"} -->
	<div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Go to the homepage', 'oogle' ); ?></a></div>
	<!-- /wp:button -->
</div>
<!-- /wp:buttons -->
