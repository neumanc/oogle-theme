<?php
/**
 * Oogle — parent block theme bootstrap.
 *
 * This file only loads modules from inc/. It contains no logic of its own so
 * that each concern stays small, named and independently removable.
 *
 * A child theme's functions.php runs BEFORE this file (WordPress loads the
 * child first), so a child may define its own hooks freely; everything here
 * is filterable rather than hard-coded.
 *
 * @package Oogle
 * @since   0.1.0
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

/**
 * Theme version, read from style.css so it is declared in exactly one place.
 * get_template() is the parent's directory name whatever it was installed as.
 */
define( 'OOGLE_VERSION', (string) wp_get_theme( get_template() )->get( 'Version' ) );
define( 'OOGLE_DIR', get_template_directory() );
define( 'OOGLE_URI', get_template_directory_uri() );

foreach ( array( 'setup', 'assets', 'block-styles', 'editor', 'images', 'cleanup', 'a11y', 'updates' ) as $oogle_module ) {
	require_once OOGLE_DIR . '/inc/' . $oogle_module . '.php';
}
unset( $oogle_module );
