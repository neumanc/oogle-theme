<?php
/**
 * Editor policy.
 *
 * Keeps the inserter focused on blocks that produce good, styleable markup.
 * Everything here is filterable; a child theme or site plugin may widen or
 * narrow the list. This is an editorial preference, not a capability or
 * security control: nothing here affects what a role may do, and a plugin
 * that restricts blocks before the theme keeps its restriction (the theme
 * only ever intersects, never broadens).
 *
 * @package Oogle
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

/**
 * Post types that are internal to WordPress (templates, parts, patterns,
 * navigation, styles, fonts) — never restricted, even when they are edited
 * through a post-editor context.
 *
 * @return string[]
 */
function oogle_editor_internal_post_types(): array {
	return array( 'wp_template', 'wp_template_part', 'wp_block', 'wp_navigation', 'wp_global_styles', 'wp_font_family', 'wp_font_face' );
}

/**
 * Whether an editor context is the post editor for an ordinary post type.
 *
 * Only `core/edit-post` with a real post of a public-facing or REST-visible,
 * non-internal type is restricted. The Site Editor (`core/edit-site`), the
 * widgets editors and the Customizer are never restricted: administrators
 * editing templates need the full set (post-title, query blocks, template
 * parts, …).
 *
 * @param WP_Block_Editor_Context $context Editor context.
 * @return bool
 */
function oogle_editor_is_restricted_context( WP_Block_Editor_Context $context ): bool {
	if ( 'core/edit-post' !== $context->name || ! $context->post instanceof WP_Post ) {
		return false;
	}
	$type = $context->post->post_type;
	if ( in_array( $type, oogle_editor_internal_post_types(), true ) ) {
		return false;
	}
	$object = get_post_type_object( $type );
	if ( ! $object instanceof WP_Post_Type ) {
		return false;
	}

	/**
	 * Filter whether the inserter is restricted for a post type.
	 *
	 * @param bool         $restricted Default: the type is public or REST-visible.
	 * @param WP_Post_Type $object     Post type object.
	 * @param WP_Block_Editor_Context $context Editor context.
	 */
	return (bool) apply_filters( 'oogle/editor/restrict_post_type', (bool) $object->public || (bool) $object->show_in_rest, $object, $context );
}

/**
 * Blocks allowed in the inserter, composed with whatever WordPress or an
 * earlier filter already decided:
 *
 *  - `false` (nothing allowed) is never broadened;
 *  - an array from an earlier filter is intersected with the theme's list, so
 *    the result is never wider than either;
 *  - `true` / null (everything allowed) becomes the theme's list.
 *
 * The `oogle/editor/allowed_blocks` filter can widen or narrow the theme's
 * list (returning `true` means "no theme restriction"); it still never
 * widens an upstream array.
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
	if ( false === $allowed || ! oogle_editor_is_restricted_context( $context ) ) {
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
	 * Filter the theme's allowed blocks for post/page editing.
	 *
	 * Return true to lift the theme's restriction. Third-party blocks (e.g. a
	 * form plugin's block) must be added here by the site that uses them. An
	 * upstream restriction (a plugin returning an array earlier in
	 * `allowed_block_types_all`) is still applied afterwards.
	 *
	 * @param string[]                $blocks  Allowed block names.
	 * @param WP_Block_Editor_Context $context Editor context.
	 */
	$blocks = apply_filters( 'oogle/editor/allowed_blocks', $blocks, $context );

	if ( true === $blocks ) {
		return $allowed;
	}
	if ( ! is_array( $blocks ) ) {
		return $allowed;
	}
	$blocks = array_values( array_unique( array_filter( $blocks, 'is_string' ) ) );

	if ( is_array( $allowed ) ) {
		return array_values( array_intersect( $blocks, array_filter( $allowed, 'is_string' ) ) );
	}
	return $blocks;
}
add_filter( 'allowed_block_types_all', 'oogle_allowed_blocks', 10, 2 );
