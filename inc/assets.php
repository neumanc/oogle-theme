<?php
/**
 * Front-end and editor assets.
 *
 * Strategy (docs/CSS.md):
 *  - theme.json generates global styles (inline, both contexts).
 *  - base.css is the only global stylesheet: focus, skip link, motion, a few
 *    element rules that theme.json cannot express. Kept tiny.
 *  - Everything else is attached to a specific core block with
 *    wp_enqueue_block_style(), so it loads only on pages rendering that block
 *    and is inlined by core when small.
 *  - JavaScript is limited to small progressive-enhancement script modules
 *    (reveal, rotator) enqueued only when a block asks for them; core's
 *    Interactivity API modules (navigation overlay, accordion, lightbox) load
 *    themselves on demand.
 *
 * @package Oogle
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

/**
 * Version string for cache-busting theme assets.
 *
 * Uses file modification time in development (WP_DEBUG) so edits show
 * immediately; the theme version in production so caches are stable.
 *
 * @param string $relative_path Path relative to the theme root.
 * @return string
 */
function oogle_asset_version( string $relative_path ): string {
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		$file = OOGLE_DIR . '/' . ltrim( $relative_path, '/' );
		if ( is_readable( $file ) ) {
			return (string) filemtime( $file );
		}
	}
	return OOGLE_VERSION;
}

/**
 * Global base stylesheet (front end only). The editor canvas gets the
 * equivalent rules it needs from assets/css/editor.css.
 *
 * @return void
 */
function oogle_enqueue_base(): void {
	wp_enqueue_style(
		'oogle-base',
		OOGLE_URI . '/assets/css/base.css',
		array(),
		oogle_asset_version( 'assets/css/base.css' )
	);
}
add_action( 'wp_enqueue_scripts', 'oogle_enqueue_base' );

/**
 * Per-block stylesheets.
 *
 * Map of block name => file in assets/css/blocks/. Filterable so a child can
 * add its own per-block file or remove one of ours.
 *
 * @return array<string, string>
 */
function oogle_block_styles_map(): array {
	$map = array(
		'core/navigation'       => 'core-navigation.css',
		'core/group'            => 'core-group.css',
		'core/columns'          => 'core-columns.css',
		'core/button'           => 'core-button.css',
		'core/image'            => 'core-image.css',
		'core/list'             => 'core-list.css',
		'core/paragraph'        => 'core-paragraph.css',
		'core/quote'            => 'core-quote.css',
		'core/accordion'        => 'core-accordion.css',
		'core/post-template'    => 'core-post-template.css',
		'core/breadcrumbs'      => 'core-breadcrumbs.css',
	);

	/**
	 * Filter the block => stylesheet map.
	 *
	 * @param array<string, string> $map Block name => filename inside assets/css/blocks/.
	 */
	return (array) apply_filters( 'oogle/assets/block_styles', $map );
}

/**
 * Attach each per-block stylesheet. Core loads it front-end and editor,
 * only when the block renders, and inlines it if under the size limit.
 *
 * @return void
 */
function oogle_enqueue_block_styles(): void {
	foreach ( oogle_block_styles_map() as $block => $file ) {
		$rel = 'assets/css/blocks/' . $file;
		if ( ! is_readable( OOGLE_DIR . '/' . $rel ) ) {
			continue;
		}
		wp_enqueue_block_style(
			$block,
			array(
				'handle' => 'oogle-' . sanitize_key( str_replace( '/', '-', $block ) ),
				'src'    => OOGLE_URI . '/' . $rel,
				'path'   => OOGLE_DIR . '/' . $rel, // Enables inlining.
				'ver'    => oogle_asset_version( $rel ),
			)
		);
	}
}
add_action( 'init', 'oogle_enqueue_block_styles' );

/**
 * Progressive-enhancement script modules.
 *
 * Each module is registered always and enqueued only when a rendered block
 * carries its trigger class (checked at render time, before wp_footer prints
 * script modules). All are small, deferred, dependency-free ES modules.
 *
 * @return array<string, array{file:string, class:string}>
 */
function oogle_script_modules(): array {
	$modules = array(
		'oogle-reveal'  => array( 'file' => 'assets/js/reveal.js',  'class' => 'oogle-reveal' ),  // Scroll entrances (~1.5 KB).
		'oogle-rotator' => array( 'file' => 'assets/js/rotator.js', 'class' => 'oogle-rotator' ), // Crossfading photo stack (~2.9 KB).
	);

	/**
	 * Filter the theme's script modules.
	 *
	 * @param array<string, array{file:string, class:string}> $modules Handle => file + trigger class.
	 */
	return (array) apply_filters( 'oogle/assets/script_modules', $modules );
}

/**
 * Register the modules.
 *
 * @return void
 */
function oogle_register_script_modules(): void {
	foreach ( oogle_script_modules() as $handle => $module ) {
		wp_register_script_module( $handle, OOGLE_URI . '/' . $module['file'], array(), oogle_asset_version( $module['file'] ) );
	}
}
add_action( 'init', 'oogle_register_script_modules' );

/**
 * Enqueue a module the first time a block with its trigger class renders.
 *
 * @param string               $content Block HTML.
 * @param array<string, mixed> $block   Parsed block.
 * @return string
 */
function oogle_maybe_enqueue_script_modules( string $content, array $block ): string {
	static $done = array();
	if ( is_admin() ) {
		return $content;
	}
	$class = $block['attrs']['className'] ?? '';
	if ( ! is_string( $class ) || '' === $class ) {
		return $content;
	}
	foreach ( oogle_script_modules() as $handle => $module ) {
		if ( ! isset( $done[ $handle ] ) && str_contains( $class, $module['class'] ) ) {
			wp_enqueue_script_module( $handle );
			$done[ $handle ] = true;
		}
	}
	return $content;
}
add_filter( 'render_block', 'oogle_maybe_enqueue_script_modules', 10, 2 );

/**
 * Gravity Forms integration stylesheet.
 *
 * Loaded only when Gravity Forms is active AND a form is actually being
 * rendered (gform_enqueue_scripts fires per rendered form). The theme has no
 * other knowledge of Gravity Forms; sites using another form plugin simply
 * never trigger this.
 *
 * @return void
 */
function oogle_enqueue_gravity_forms_styles(): void {
	$rel = 'assets/css/integrations/gravity-forms.css';
	wp_enqueue_style(
		'oogle-gravity-forms',
		OOGLE_URI . '/' . $rel,
		array( 'oogle-base' ),
		oogle_asset_version( $rel )
	);
}
add_action( 'gform_enqueue_scripts', 'oogle_enqueue_gravity_forms_styles' );
