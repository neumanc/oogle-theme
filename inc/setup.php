<?php
/**
 * Theme setup: supports, pattern categories, editor styles.
 *
 * Block themes get most supports automatically (title-tag, post-thumbnails,
 * responsive-embeds, editor-styles, html5, automatic-feed-links, block
 * templates). Only what core does NOT switch on by default is declared here.
 *
 * @package Oogle
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

/**
 * Declare supports and register pattern categories.
 *
 * @return void
 */
function oogle_setup(): void {
	// Editor styles: block themes load theme.json automatically; this adds the
	// small editor-only corrections file. Front-end block CSS is attached per
	// block in assets.php and is loaded in the editor by core as well.
	add_editor_style( 'assets/css/editor.css' );

	// Core's own patterns and the pattern directory are noise for client sites.
	// A child theme may re-enable them with add_theme_support( 'core-block-patterns' ).
	remove_theme_support( 'core-block-patterns' );

	// Wide/full alignments are enabled by theme.json layout settings; nothing to do.
}
add_action( 'after_setup_theme', 'oogle_setup' );

/**
 * Pattern categories. Patterns themselves are auto-registered from /patterns.
 *
 * @return void
 */
function oogle_register_pattern_categories(): void {
	register_block_pattern_category(
		'oogle-sections',
		array(
			'label'       => _x( 'Oogle: Sections', 'Block pattern category', 'oogle' ),
			'description' => __( 'Full-width page sections composed of core blocks.', 'oogle' ),
		)
	);
	register_block_pattern_category(
		'oogle-components',
		array(
			'label'       => _x( 'Oogle: Components', 'Block pattern category', 'oogle' ),
			'description' => __( 'Smaller reusable pieces: cards, quotes, call-outs.', 'oogle' ),
		)
	);
}
add_action( 'init', 'oogle_register_pattern_categories', 9 );

/**
 * Do not load patterns from the WordPress.org pattern directory.
 *
 * @return bool
 */
add_filter( 'should_load_remote_block_patterns', '__return_false' );
