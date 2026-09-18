<?php
/**
 * Title: Blog heading
 * Slug: oogle/hidden-blog-heading
 * Inserter: false
 * Description: The H1 of the posts index (index template). Core's Query Title block renders nothing on the posts page, so the heading is a pattern for translation.
 *
 * @package Oogle
 */

?>
<!-- wp:heading {"level":1} -->
<h1 class="wp-block-heading"><?php esc_html_e( 'Blog', 'oogle' ); ?></h1>
<!-- /wp:heading -->
