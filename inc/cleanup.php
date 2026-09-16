<?php
/**
 * Front-end head/asset cleanup.
 *
 * Only assets we understand and can justify removing. Each item is filterable
 * off. Nothing here is removed in the admin or the editor.
 *
 *  - Emoji detection script + inline styles: ~10 KB of JS and a DNS lookup on
 *    every page; every supported browser renders emoji natively.
 *  - wp-embed.js is NOT removed: it only loads when an embed is present.
 *  - Dashicons: core already skips them for logged-out users in block themes.
 *  - oEmbed discovery <link>s, RSD, shortlink: harmless and occasionally
 *    useful (e.g. Slack unfurls); left in place.
 *
 * @package Oogle
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

/**
 * Remove the emoji loader on the front end.
 *
 * @return void
 */
function oogle_disable_emoji(): void {
	if ( is_admin() || ! apply_filters( 'oogle/cleanup/emoji', true ) ) {
		return;
	}
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
	remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
	remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
	add_filter( 'emoji_svg_url', '__return_false' ); // Drops the s.w.org dns-prefetch.
}
add_action( 'init', 'oogle_disable_emoji' );
