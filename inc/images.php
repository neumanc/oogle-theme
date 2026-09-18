<?php
/**
 * Image handling.
 *
 * Core already provides srcset/sizes, lazy loading, automatic fetchpriority
 * on the first large image, sizes="auto" for lazy images, and width/height
 * attributes. This file adds only two things core leaves to the theme:
 *
 *  1. Modern output format on upload (WebP by default; AVIF opt-in).
 *  2. A hard guarantee that the hero pattern's image is treated as the LCP
 *     candidate (eager + high priority) even if core's heuristic picks
 *     something else first — core's heuristic is good, but a logo above the
 *     hero can occasionally win.
 *
 * @package Oogle
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

/**
 * Generate WebP sub-sizes for JPEG uploads (originals are kept).
 *
 * AVIF is smaller still but needs Imagick with AVIF support and encodes
 * slowly on shared hosts; opt in with: add_filter( 'oogle/images/avif', '__return_true' ).
 *
 * @param array<string, string> $formats Existing mime map.
 * @return array<string, string>
 */
function oogle_image_output_format( array $formats ): array {
	$target = apply_filters( 'oogle/images/avif', false ) && wp_image_editor_supports( array( 'mime_type' => 'image/avif' ) )
		? 'image/avif'
		: 'image/webp';

	if ( ! wp_image_editor_supports( array( 'mime_type' => $target ) ) ) {
		return $formats;
	}

	// JPEG only. PNG is left alone on purpose: palette/indexed PNGs and
	// transparency convert unreliably across GD/Imagick builds (observed in
	// testing), and PNG uploads are usually logos where fidelity matters.
	$formats['image/jpeg'] = $target;
	return $formats;
}
add_filter( 'image_editor_output_format', 'oogle_image_output_format' );

/**
 * Guarantee LCP treatment for the hero image.
 *
 * A hero pattern gives its Cover or Image block the class "oogle-lcp". Core's
 * heuristic (wp_filter_content_tags) already avoids lazy-loading the first
 * content images and adds fetchpriority="high" to the first large one; this
 * makes the outcome deterministic for the one element we know is the LCP.
 *
 * $content is untyped on purpose (see oogle_maybe_enqueue_script_modules()):
 * a non-string from an earlier render_block filter is passed through.
 *
 * @param mixed                $content Rendered block HTML (string from core).
 * @param array<string, mixed> $block   Parsed block.
 * @return mixed
 */
function oogle_cover_lcp_attributes( $content, array $block ) {
	if ( ! is_string( $content ) ) {
		return $content;
	}
	$class = $block['attrs']['className'] ?? '';
	if ( ! is_string( $class ) || ! str_contains( $class, 'oogle-lcp' ) ) {
		return $content;
	}

	$processor = new WP_HTML_Tag_Processor( $content );
	if ( $processor->next_tag( 'img' ) ) {
		$processor->remove_attribute( 'loading' );
		$processor->set_attribute( 'fetchpriority', 'high' );
		$processor->set_attribute( 'decoding', 'sync' );
		$processor->add_class( 'oogle-lcp__img' );
		return $processor->get_updated_html();
	}
	return $content;
}
add_filter( 'render_block_core/cover', 'oogle_cover_lcp_attributes', 10, 2 );
add_filter( 'render_block_core/image', 'oogle_cover_lcp_attributes', 10, 2 );
add_filter( 'render_block_core/post-featured-image', 'oogle_cover_lcp_attributes', 10, 2 );

/**
 * Force lazy, low-priority loading for an image marked "oogle-defer".
 *
 * Core skips lazy-loading for the first few content images; a rotator's
 * second and third slides would otherwise be fetched eagerly next to the LCP
 * image. With loading="lazy" a slide that is display:none is not requested
 * at all until a script shows it (verified in Chromium and Firefox).
 *
 * $content is untyped on purpose (see oogle_maybe_enqueue_script_modules()):
 * a non-string from an earlier render_block filter is passed through.
 *
 * @param mixed                $content Rendered block HTML (string from core).
 * @param array<string, mixed> $block   Parsed block.
 * @return mixed
 */
function oogle_defer_image_attributes( $content, array $block ) {
	if ( ! is_string( $content ) ) {
		return $content;
	}
	$class = $block['attrs']['className'] ?? '';
	if ( ! is_string( $class ) || ! str_contains( $class, 'oogle-defer' ) ) {
		return $content;
	}

	$processor = new WP_HTML_Tag_Processor( $content );
	if ( $processor->next_tag( 'img' ) ) {
		$processor->set_attribute( 'loading', 'lazy' );
		$processor->set_attribute( 'fetchpriority', 'low' );
		$processor->set_attribute( 'decoding', 'async' );
		return $processor->get_updated_html();
	}
	return $content;
}
add_filter( 'render_block_core/image', 'oogle_defer_image_attributes', 10, 2 );
