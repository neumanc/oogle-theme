<?php
/**
 * Block styles — the theme's component vocabulary.
 *
 * A block style is a stable public name (is-style-{name}) selectable in the
 * editor. Styling lives in assets/css/blocks/*.css keyed to that class, so a
 * theme update can improve every existing instance without touching content.
 *
 * Add a style here only when a pattern genuinely needs a look that no block
 * setting provides. Names are part of the parent/child contract (docs/TOKENS.md).
 *
 * @package Oogle
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

/**
 * Register block styles.
 *
 * @return void
 */
function oogle_register_block_styles(): void {
	$styles = array(
		// Group.
		array( 'core/group', 'card',          __( 'Card', 'oogle' ) ),
		array( 'core/group', 'card-flush',    __( 'Card (flush media)', 'oogle' ) ),
		array( 'core/group', 'mosaic',        __( 'Mosaic (grid of images)', 'oogle' ) ),
		array( 'core/group', 'section-head', __( 'Section head (title left, intro right)', 'oogle' ) ),
		array( 'core/group', 'numbered',      __( 'Numbered (01, 02 … on each child)', 'oogle' ) ),
		// "section-tint" and "section-reversed" are section styles defined in
		// styles/*.json (theme.json partials) because they recolor nested
		// headings, links and buttons — which JSON does safely and CSS cannot.
		// Paragraph.
		array( 'core/paragraph', 'eyebrow',   __( 'Eyebrow', 'oogle' ) ),
		array( 'core/paragraph', 'lead',      __( 'Lead', 'oogle' ) ),
		// List.
		array( 'core/list', 'checklist',      __( 'Checklist', 'oogle' ) ),
		array( 'core/list', 'inline',         __( 'Inline', 'oogle' ) ),
		// Quote.
		array( 'core/quote', 'testimonial',   __( 'Testimonial', 'oogle' ) ),
		// Button (core ships "fill" and "outline"; we add a text-only style).
		array( 'core/button', 'text',         __( 'Text link', 'oogle' ) ),
		// Columns.
		array( 'core/columns', 'reverse-on-stack', __( 'Reverse when stacked', 'oogle' ) ),
		// Terms list (Categories block with any taxonomy).
		array( 'core/categories', 'inline',   __( 'Inline (filter row)', 'oogle' ) ),
		// Post template (Query Loop).
		array( 'core/post-template', 'editorial', __( 'Editorial grid (first item large)', 'oogle' ) ),
	);

	/**
	 * Filter the list of block styles the theme registers.
	 *
	 * @param array<int, array{0:string,1:string,2:string}> $styles Triples of block, name, label.
	 */
	$styles = (array) apply_filters( 'oogle/block_styles', $styles );

	foreach ( $styles as $style ) {
		register_block_style(
			$style[0],
			array(
				'name'  => $style[1],
				'label' => $style[2],
			)
		);
	}
}
add_action( 'init', 'oogle_register_block_styles' );
