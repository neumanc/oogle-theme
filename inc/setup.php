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
	// Translations. The parent ships none, but a site may drop oogle-{locale}.mo
	// into languages/ (or wp-content/languages/themes/) and they will load.
	load_theme_textdomain( 'oogle', OOGLE_DIR . '/languages' );

	// Editor styles: block themes load theme.json automatically. base.css is
	// added so the canvas matches the front end (button targets, section
	// adjacency, text wrapping…); editor.css follows it and undoes the few
	// document-level rules that make no sense inside the canvas. Per-block CSS
	// is attached in assets.php and loaded in the editor by core as well.
	add_editor_style( array( 'assets/css/base.css', 'assets/css/editor.css' ) );

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
