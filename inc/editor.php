<?php
/**
 * Editor policy.
 *
 * Keeps the inserter focused on blocks that produce good, styleable markup.
 * Everything here is filterable; a child theme or site plugin may widen or
 * narrow the list. Nothing here affects capability/role behaviour — Editors
 * cannot reach the Site Editor by default in any block theme.
 *
 * @package Oogle
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

/**
 * Blocks allowed in the inserter.
 *
 * Deliberately excluded (restorable via the filter): archives, calendar,
 * tag-cloud, rss, latest-comments, verse, preformatted, text-columns,
 * nextpage, more, footnotes, legacy widgets, comments-related blocks.
 *
 * @param bool|string[]           $allowed Current value.
 * @param WP_Block_Editor_Context $context Editor context.
 * @return bool|string[]
 */
function oogle_allowed_blocks( $allowed, WP_Block_Editor_Context $context ) {
	// Never restrict the Site Editor: administrators editing templates need
	// the full set (post-title, query blocks, template parts, etc.).
	if ( ! isset( $context->post ) ) {
		return $allowed;
	}

	$blocks = array(
		// Text.
		'core/paragraph',
		'core/heading',
		'core/list',
		'core/list-item',
		'core/quote',
		'core/pullquote',
		'core/code',
		'core/table',
		'core/details',
		'core/accordion',
		'core/accordion-item',
		'core/accordion-heading',
		'core/accordion-panel',
		'core/tabs',
		'core/tab-list',
		'core/tab-panels',
		'core/tab-panel',
		// Media.
		'core/image',
		'core/gallery',
		'core/cover',
		'core/media-text',
		'core/video',
		'core/audio',
		'core/file',
		'core/embed',
		'core/icon',
		// Design.
		'core/group',
		'core/columns',
		'core/column',
		'core/buttons',
		'core/button',
		'core/separator',
		'core/spacer',
		// Theme / dynamic.
		'core/pattern',
		'core/block',
		'core/shortcode',
		'core/html',
		'core/breadcrumbs',
		'core/social-links',
		'core/social-link',
		'core/query',
		'core/post-template',
		'core/query-pagination',
		'core/query-pagination-next',
		'core/query-pagination-previous',
		'core/query-pagination-numbers',
		'core/query-no-results',
		'core/post-title',
		'core/post-excerpt',
		'core/post-featured-image',
		'core/post-date',
		'core/post-terms',
		'core/read-more',
		'core/latest-posts',
		'core/search',
		'core/site-logo',
		'core/site-title',
		'core/site-tagline',
		'core/navigation',
		'core/navigation-link',
		'core/navigation-submenu',
		'core/page-list',
		'core/terms-query',
		'core/term-template',
		'core/term-name',
		'core/term-description',
		'core/term-count',
	);

	/**
	 * Filter the allowed blocks for post/page editing.
	 *
	 * Return true to allow everything. Third-party blocks (e.g. a form plugin's
	 * block) must be added here by the site that uses them.
	 *
	 * @param string[]                $blocks  Allowed block names.
	 * @param WP_Block_Editor_Context $context Editor context.
	 */
	return apply_filters( 'oogle/editor/allowed_blocks', $blocks, $context );
}
add_filter( 'allowed_block_types_all', 'oogle_allowed_blocks', 10, 2 );
