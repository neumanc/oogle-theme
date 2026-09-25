<?php
/**
 * Oogle Fixture Child — functions.
 *
 * Uses only the documented child-theme contract (docs/EXTENSION-API.md), the
 * way a client child would. WordPress loads this file BEFORE the parent's
 * functions.php, so parent constants and functions are only used inside hooks.
 *
 * @package OogleFixtureChild
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

/**
 * Parent version seen by the child once both themes have loaded (tests read it).
 *
 * @return void
 */
function oogle_fixture_child_setup(): void {
	$GLOBALS['oogle_fixture_child'] = array(
		'parent_version' => defined( 'OOGLE_VERSION' ) ? OOGLE_VERSION : '',
	);

	// The child's own CSS in the editor canvas too. Its own file name: a child
	// file at assets/css/base.css or assets/css/editor.css would replace the
	// parent's editor styles (docs/CHILD-THEMES.md).
	add_editor_style( 'assets/css/fixture.css' );
}
add_action( 'after_setup_theme', 'oogle_fixture_child_setup' );

/**
 * Child stylesheet, after the parent's base stylesheet.
 *
 * @return void
 */
function oogle_fixture_child_enqueue(): void {
	$rel = 'assets/css/fixture.css';
	wp_enqueue_style(
		'oogle-fixture-child',
		get_theme_file_uri( $rel ),
		array( 'oogle-base' ),
		(string) wp_get_theme()->get( 'Version' )
	);
}
add_action( 'wp_enqueue_scripts', 'oogle_fixture_child_enqueue' );

/**
 * A per-block stylesheet of the child's own, the native way.
 *
 * @return void
 */
function oogle_fixture_child_block_styles(): void {
	$rel = 'assets/css/core-quote.css';
	wp_enqueue_block_style(
		'core/quote',
		array(
			'handle' => 'oogle-fixture-child-core-quote',
			'src'    => get_theme_file_uri( $rel ),
			'path'   => get_theme_file_path( $rel ),
			'ver'    => (string) wp_get_theme()->get( 'Version' ),
		)
	);
}
add_action( 'init', 'oogle_fixture_child_block_styles' );

/**
 * One extra block style through the parent's filter.
 *
 * @param array<int, array{0:string,1:string,2:string}> $styles Block, name, label triples.
 * @return array<int, array{0:string,1:string,2:string}>
 */
function oogle_fixture_child_register_styles( array $styles ): array {
	$styles[] = array( 'core/group', 'fixture-panel', __( 'Fixture panel', 'oogle-fixture-child' ) );
	return $styles;
}
add_filter( 'oogle/block_styles', 'oogle_fixture_child_register_styles' );

/**
 * One block the parent's curated inserter leaves out.
 *
 * @param string[]|bool $blocks Allowed blocks.
 * @return string[]|bool
 */
function oogle_fixture_child_allowed_blocks( $blocks ) {
	if ( is_array( $blocks ) ) {
		$blocks[] = 'core/verse';
	}
	return $blocks;
}
add_filter( 'oogle/editor/allowed_blocks', 'oogle_fixture_child_allowed_blocks' );

/**
 * The child's own pattern category.
 *
 * @return void
 */
function oogle_fixture_child_pattern_category(): void {
	register_block_pattern_category( 'oogle-fixture-child', array( 'label' => __( 'Fixture', 'oogle-fixture-child' ) ) );
}
add_action( 'init', 'oogle_fixture_child_pattern_category', 9 );
