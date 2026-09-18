<?php
/**
 * Title: Query loop as cards (template use)
 * Slug: oogle/query-cards
 * Categories: oogle-components
 * Inserter: false
 * Description: Inherited-query card grid used by the index, archive and search templates. Not shown in the inserter.
 *
 * @package Oogle
 */
?>
<!-- wp:query {"queryId":2,"query":{"perPage":12,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","inherit":true},"align":"wide"} -->
<div class="wp-block-query alignwide">
	<!-- wp:post-template {"layout":{"type":"grid","minimumColumnWidth":"18rem"}} -->
		<!-- wp:group {"className":"is-style-card-flush","style":{"spacing":{"blockGap":"0"}},"layout":{"type":"constrained"}} -->
		<div class="wp-block-group is-style-card-flush">
			<!-- wp:post-featured-image {"aspectRatio":"3/2"} /-->
			<!-- wp:group {"style":{"spacing":{"padding":{"top":"var:preset|spacing|40","right":"var:preset|spacing|40","bottom":"var:preset|spacing|40","left":"var:preset|spacing|40"},"blockGap":"var:preset|spacing|20"}},"layout":{"type":"constrained"}} -->
			<div class="wp-block-group" style="padding-top:var(--wp--preset--spacing--40);padding-right:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--40);padding-left:var(--wp--preset--spacing--40)">
				<!-- wp:post-title {"level":2,"isLink":true,"fontSize":"x-large"} /-->
				<!-- wp:post-date {"fontSize":"small"} /-->
				<!-- wp:post-excerpt {"excerptLength":24} /-->
			</div>
			<!-- /wp:group -->
		</div>
		<!-- /wp:group -->
	<!-- /wp:post-template -->

	<!-- wp:query-pagination {"layout":{"type":"flex","justifyContent":"center"}} -->
		<!-- wp:query-pagination-previous /-->
		<!-- wp:query-pagination-numbers /-->
		<!-- wp:query-pagination-next /-->
	<!-- /wp:query-pagination -->

	<!-- wp:query-no-results -->
		<!-- wp:pattern {"slug":"oogle/hidden-no-results"} /-->
	<!-- /wp:query-no-results -->
</div>
<!-- /wp:query -->
