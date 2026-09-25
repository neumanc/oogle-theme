<?php
/**
 * Oogle Theme — child-theme compatibility suite (dev only; excluded from the release zip).
 *
 * Proves the documented parent/child contract (docs/EXTENSION-API.md) in both supported
 * configurations:
 *   parent active (no child):      wp theme activate oogle-theme
 *   fixture child active:          wp theme activate oogle-fixture-child  (tests/fixtures/)
 * then:  wp eval-file wp-content/themes/oogle-theme/tests/child-theme.php
 * The mode follows the active theme. Every assertion runs PHP inside the real WordPress
 * (no mocks); nothing here makes a network request.
 */
$GLOBALS['oogle_t'] = array( 'pass' => 0, 'fail' => 0 );
function t( string $name, bool $ok, string $detail = '' ): void {
	$GLOBALS['oogle_t'][ $ok ? 'pass' : 'fail' ]++;
	echo ( $ok ? 'PASS' : 'FAIL' ) . '  ' . $name . ( $detail ? "  [$detail]" : '' ) . "\n";
}

$child  = is_child_theme();
$parent = get_template_directory();
$mine   = get_stylesheet_directory();
echo 'MODE: ' . ( $child ? 'child (' . get_stylesheet() . ')' : 'parent only' ) . "\n";

echo "\n== Recognition and bootstrap ==\n";
t( 'the parent directory is oogle-theme', 'oogle-theme' === get_template() );
if ( $child ) {
	$theme = wp_get_theme();
	t( 'WordPress recognises the fixture as a child of Oogle', 'oogle-fixture-child' === get_stylesheet() && $theme->parent() instanceof WP_Theme && 'Oogle' === $theme->parent()->get( 'Name' ) );
	t( 'the child declares Update URI: false (never offered a wordpress.org theme of the same slug)', 'false' === $theme->get( 'UpdateURI' ) );
	t( 'child initialised (after_setup_theme ran)', isset( $GLOBALS['oogle_fixture_child'] ) );
	t( 'child sees the parent version inside a hook (functions.php order)', ( $GLOBALS['oogle_fixture_child']['parent_version'] ?? '' ) === OOGLE_VERSION );
} else {
	t( 'no child: stylesheet and template are the same theme', get_stylesheet() === get_template() );
}
t( 'parent initialised (modules loaded)', function_exists( 'oogle_setup' ) && function_exists( 'oogle_update_check' ) && has_action( 'after_setup_theme', 'oogle_setup' ) );
t( 'OOGLE_VERSION is the PARENT\'s version, never the child\'s', OOGLE_VERSION === (string) wp_get_theme( 'oogle-theme' )->get( 'Version' ) );
t( 'OOGLE_DIR / OOGLE_URI point at the parent', OOGLE_DIR === $parent && OOGLE_URI === get_template_directory_uri() );

echo "\n== Stylesheets and modules ==\n";
do_action( 'wp_enqueue_scripts' );
$styles = wp_styles();
$src    = static fn( string $h ): string => isset( $styles->registered[ $h ] ) ? (string) $styles->registered[ $h ]->src : '';
t( 'parent base stylesheet (handle oogle-base) resolves from the parent', str_contains( $src( 'oogle-base' ), '/themes/oogle-theme/assets/css/base.css' ) && in_array( 'oogle-base', $styles->queue, true ) );
if ( $child ) {
	t( 'child stylesheet resolves from the child and loads after oogle-base', str_contains( $src( 'oogle-fixture-child' ), '/themes/oogle-fixture-child/assets/css/fixture.css' ) && in_array( 'oogle-base', $styles->registered['oogle-fixture-child']->deps, true ) );
}
do_blocks( '<!-- wp:group {"className":"is-style-card"} --><div class="wp-block-group is-style-card"><!-- wp:quote {"className":"is-style-testimonial"} --><blockquote class="wp-block-quote is-style-testimonial"><!-- wp:paragraph --><p>q</p><!-- /wp:paragraph --></blockquote><!-- /wp:quote --></div><!-- /wp:group -->' );
t( 'parent per-block stylesheet resolves from the parent', str_contains( $src( 'oogle-core-group' ), '/themes/oogle-theme/assets/css/blocks/core-group.css' ) );
if ( $child ) {
	t( 'child per-block stylesheet (wp_enqueue_block_style) resolves from the child', str_contains( $src( 'oogle-fixture-child-core-quote' ), '/themes/oogle-fixture-child/assets/css/core-quote.css' ) );
}
$modules    = new ReflectionProperty( WP_Script_Modules::class, 'registered' );
$registered = $modules->getValue( wp_script_modules() );
t( 'script modules resolve from the parent', str_contains( (string) ( $registered['oogle-reveal']['src'] ?? '' ), '/themes/oogle-theme/assets/js/reveal.js' ) );
$bogus = static function ( array $m ): array {
	$m['oogle-test-missing'] = array( 'file' => 'assets/js/not-shipped.js', 'class' => 'oogle-test-missing' );
	return $m;
};
add_filter( 'oogle/assets/script_modules', $bogus );
oogle_register_script_modules();
remove_filter( 'oogle/assets/script_modules', $bogus );
$registered = $modules->getValue( wp_script_modules() );
t( 'a module entry naming a file the parent does not ship is not registered (no 404 URL)', ! isset( $registered['oogle-test-missing'] ) );

echo "\n== theme.json: settings, tokens, section styles ==\n";
WP_Theme_JSON_Resolver::clean_cached_data();
$palette = wp_get_global_settings( array( 'color', 'palette', 'theme' ) );
$custom  = wp_get_global_settings( array( 'custom' ) );
$layout  = wp_get_global_settings( array( 'layout' ) );
$vars    = wp_get_global_stylesheet( array( 'variables' ) );
$slugs   = array_column( (array) $palette, 'slug' );
t( 'every contract palette slug is present', array() === array_diff( array( 'base', 'surface', 'contrast', 'contrast-soft', 'muted', 'line', 'primary', 'primary-deep', 'primary-tint', 'accent', 'accent-deep', 'accent-tint' ), $slugs ), implode( ',', $slugs ) );
$has_var = static fn( string $name, string $value ): bool => (bool) preg_match( '/' . preg_quote( $name, '/' ) . ':\s*' . preg_quote( $value, '/' ) . '\s*;/', $vars );
if ( $child ) {
	t( 'child palette replaces the parent\'s (base = #fbf8f1)', $has_var( '--wp--preset--color--base', '#fbf8f1' ) );
	t( 'child layout value wins, parent value kept where the child is silent', '40rem' === ( $layout['contentSize'] ?? '' ) && '77.5rem' === ( $layout['wideSize'] ?? '' ) );
	t( 'settings.custom merges key by key: child card.radius, parent card.padding', '0' === ( $custom['card']['radius'] ?? null ) && 'var(--wp--preset--spacing--50)' === ( $custom['card']['padding'] ?? null ) );
	t( 'child control.radius and section.base; parent section.tight kept', 'var(--wp--custom--radius--full)' === ( $custom['control']['radius'] ?? null ) && str_starts_with( (string) ( $custom['section']['base'] ?? '' ), 'clamp(3rem' ) && isset( $custom['section']['tight'] ) );
	t( 'child tokens reach the generated CSS variables', $has_var( '--wp--custom--card--radius', '0' ) && $has_var( '--wp--custom--card--shadow', 'none' ) );
} else {
	t( 'parent palette (base = #ffffff)', $has_var( '--wp--preset--color--base', '#ffffff' ) );
	t( 'card tokens default to the 1.0 values (radius md, shadow sm, padding 50)', $has_var( '--wp--custom--card--radius', 'var(--wp--custom--radius--md)' ) && $has_var( '--wp--custom--card--shadow', 'var(--wp--preset--shadow--sm)' ) && $has_var( '--wp--custom--card--padding', 'var(--wp--preset--spacing--50)' ) );
	t( 'control radius defaults to radius sm', $has_var( '--wp--custom--control--radius', 'var(--wp--custom--radius--sm)' ) );
}
t( 'buttons take their radius from the control token', str_contains( wp_get_global_stylesheet( array( 'styles' ) ), 'border-radius: var(--wp--custom--control--radius)' ) );
$variations = array();
foreach ( WP_Theme_JSON_Resolver::get_style_variations( 'block' ) as $variation ) {
	$variations[ $variation['slug'] ?? '' ] = $variation;
}
t( 'parent section styles are available', isset( $variations['section-reversed'], $variations['section-primary'], $variations['section-tint'] ) );
$tint = (string) ( $variations['section-tint']['styles']['color']['background'] ?? '' );
t( $child ? 'child styles/section-tint.json replaces the parent\'s' : 'parent section-tint is the parent\'s', $child ? str_contains( $tint, 'accent-tint' ) : str_contains( $tint, 'surface' ), $tint );

echo "\n== Templates, parts, patterns ==\n";
$file = static fn( string $type, string $slug ): string => (string) ( _get_block_template_file( $type, $slug )['path'] ?? '' );
t( 'page template comes from the parent (child does not copy it)', str_starts_with( $file( 'wp_template', 'page' ), $parent . '/' ) );
t( 'header part comes from the parent', str_starts_with( $file( 'wp_template_part', 'header' ), $parent . '/' ) );
if ( $child ) {
	t( 'template override: search.html comes from the child', str_starts_with( $file( 'wp_template', 'search' ), $mine . '/' ) );
	t( 'part override: footer.html comes from the child', str_starts_with( $file( 'wp_template_part', 'footer' ), $mine . '/' ) );
} else {
	t( 'search and footer come from the parent', str_starts_with( $file( 'wp_template', 'search' ), $parent . '/' ) && str_starts_with( $file( 'wp_template_part', 'footer' ), $parent . '/' ) );
}
$footer = do_blocks( '<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->' );
t( $child ? 'rendered footer is the child\'s (oogle-dark surface)' : 'rendered footer is the parent\'s', $child ? str_contains( $footer, 'oogle-fixture-footer' ) && str_contains( $footer, 'oogle-dark' ) : str_contains( $footer, 'oogle-footer' ) && ! str_contains( $footer, 'oogle-fixture' ) );
$four = do_blocks( '<!-- wp:pattern {"slug":"oogle/hidden-404"} /-->' );
t( $child ? 'pattern override by slug: the parent\'s 404 template renders the child\'s oogle/hidden-404' : 'the parent\'s oogle/hidden-404 renders', $child ? str_contains( $four, 'oogle-fixture-404' ) : str_contains( $four, 'Page not found' ) );
$registry = WP_Block_Patterns_Registry::get_instance();
t( 'parent patterns stay registered', $registry->is_registered( 'oogle/cta-panel' ) && $registry->is_registered( 'oogle/query-cards' ) );
if ( $child ) {
	t( 'child pattern in its own namespace is registered', $registry->is_registered( 'oogle-fixture-child/marker' ) );
	$card = do_blocks( '<!-- wp:pattern {"slug":"oogle-fixture-child/marker"} /-->' );
	t( 'child pattern renders with the parent\'s card block style', str_contains( $card, 'is-style-card' ) && str_contains( $card, 'oogle-fixture-marker' ) );
}

echo "\n== Documented filters ==\n";
$styles_registry = WP_Block_Styles_Registry::get_instance();
t( 'parent block styles registered', $styles_registry->is_registered( 'core/group', 'card' ) && $styles_registry->is_registered( 'core/paragraph', 'eyebrow' ) );
if ( $child ) {
	t( 'oogle/block_styles: the child\'s addition is registered', $styles_registry->is_registered( 'core/group', 'fixture-panel' ) );
}
$post_id = wp_insert_post( array( 'post_type' => 'post', 'post_title' => 'child-theme suite', 'post_status' => 'draft' ) );
$allowed = apply_filters( 'allowed_block_types_all', true, new WP_Block_Editor_Context( array( 'post' => get_post( $post_id ) ) ) );
wp_delete_post( $post_id, true );
t( 'curated inserter active for posts', is_array( $allowed ) && in_array( 'core/paragraph', $allowed, true ) );
t( $child ? 'oogle/editor/allowed_blocks: the child\'s addition (core/verse) is allowed' : 'core/verse is outside the curated list', $child ? in_array( 'core/verse', (array) $allowed, true ) : ! in_array( 'core/verse', (array) $allowed, true ) );

echo "\n== Editor styles ==\n";
$css = implode( "\n", array_column( get_block_editor_theme_styles(), 'css' ) );
t( 'the parent\'s base.css is in the editor canvas', str_contains( $css, '.oogle-sticky' ) );
t( 'the parent\'s editor.css is in the editor canvas (not replaced by a child file of the same name)', str_contains( $css, '.editor-styles-wrapper .oogle-header' ) );
if ( $child ) {
	t( 'the child\'s own editor stylesheet is in the editor canvas', str_contains( $css, '.oogle-fixture-marker' ) );
	t( 'the fixture ships none of the parent\'s reserved editor-style paths', ! file_exists( $mine . '/assets/css/base.css' ) && ! file_exists( $mine . '/assets/css/editor.css' ) );
}

echo "\n== Updater with this configuration ==\n";
require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
$repo = oogle_update_repository();
t( 'the updater reads the PARENT\'s Update URI', is_array( $repo ) && 'neumanc' === $repo['owner'] && 'oogle-theme' === $repo['repo'] );
t( 'the updater is enabled by default', oogle_updates_enabled() );
t( 'an upgrade of the parent directory is recognised as the theme\'s own', oogle_update_is_own_upgrade( array( 'theme' => 'oogle-theme' ) ) );
$cache_key = 'oogle_update_' . md5( 'neumanc/oogle-theme|oogle-theme' );
set_site_transient(
	$cache_key,
	array(
		'version'      => '1.99.0',
		'url'          => 'https://github.com/neumanc/oogle-theme/releases/tag/v1.99.0',
		'package'      => 'https://github.com/neumanc/oogle-theme/releases/download/v1.99.0/oogle-theme.zip',
		'checksum'     => 'https://github.com/neumanc/oogle-theme/releases/download/v1.99.0/oogle-theme.zip.sha256',
		'requires'     => '7.0',
		'requires_php' => '8.4',
	),
	HOUR_IN_SECONDS
);
$offer = apply_filters( 'update_themes_github.com', false, array(), 'oogle-theme' );
t( 'the offer names the parent directory and the release asset', is_array( $offer ) && 'oogle-theme' === $offer['theme'] && str_ends_with( $offer['package'], '/oogle-theme.zip' ) );
delete_site_transient( $cache_key );
if ( $child ) {
	t( 'the child is never answered by the parent\'s updater', false === apply_filters( 'update_themes_github.com', false, array(), 'oogle-fixture-child' ) );
	t( 'an upgrade of the child directory is not treated as the parent\'s', ! oogle_update_is_own_upgrade( array( 'theme' => 'oogle-fixture-child' ) ) );
	$upgrader = new Theme_Upgrader( new Automatic_Upgrader_Skin() );
	$source   = trailingslashit( get_temp_dir() ) . 'oogle-child-suite/oogle-fixture-child/';
	t( 'the child\'s own package is passed through untouched by the parent\'s gates', $source === oogle_update_source_selection( $source, dirname( untrailingslashit( $source ) ) . '/', $upgrader, array( 'theme' => 'oogle-fixture-child' ) ) );
	t( 'a child theme is refused as a parent package', is_wp_error( oogle_update_validate_package( $mine, OOGLE_VERSION ) ) );
}
t( 'the installed parent is a valid package at its own version', true === oogle_update_validate_package( $parent, OOGLE_VERSION ) );

$pass = $GLOBALS['oogle_t']['pass'];
$fail = $GLOBALS['oogle_t']['fail'];
echo "\nRESULT (" . ( $child ? 'child' : 'parent only' ) . "): $pass passed, $fail failed\n";
exit( $fail > 0 ? 1 : 0 );
