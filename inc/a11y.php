<?php
/**
 * Accessibility corrections for core block output.
 *
 * Each entry here is a specific, verified defect in core markup, fixed at
 * render time with the HTML API, and each should disappear once core fixes
 * it upstream. Keep this file short; it is not a place for general ARIA.
 *
 * @package Oogle
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

/**
 * Accordion panel: core (7.1) renders <div aria-labelledby> with no role.
 * aria-labelledby is prohibited on the generic role (axe: aria-prohibited-attr).
 * The APG accordion pattern uses role="region" on the panel, labelled by
 * its heading button — which is exactly what core already points to.
 *
 * $content is untyped on purpose (see oogle_maybe_enqueue_script_modules()
 * in inc/assets.php): a non-string from an earlier render_block filter is
 * passed through rather than raised as a TypeError.
 *
 * @param mixed $content Rendered block HTML (string from core).
 * @return mixed
 */
function oogle_accordion_panel_role( $content ) {
	if ( ! is_string( $content ) ) {
		return $content;
	}
	$p = new WP_HTML_Tag_Processor( $content );
	if ( $p->next_tag( array( 'class_name' => 'wp-block-accordion-panel' ) ) && null === $p->get_attribute( 'role' ) ) {
		$p->set_attribute( 'role', 'region' );
		return $p->get_updated_html();
	}
	return $content;
}
add_filter( 'render_block_core/accordion-panel', 'oogle_accordion_panel_role' );
