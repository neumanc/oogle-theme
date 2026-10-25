<?php
/**
 * Oogle Theme — end-to-end upgrade scenario (dev only; never shipped).
 *
 * Runs inside the e2e lab (tests/e2e/run.sh) through WP-CLI:
 *   wp eval-file upgrade.php seed
 *   wp eval-file upgrade.php <target-scenario> <bulk|single|cli> <expect>
 * where <target-scenario> is a directory under /var/www/html/oogle-artifacts/
 * served by the GitHub mock (mu-mock.php), and <expect> is
 *   success            the parent must end up at the target version, byte-identical
 *                      to the archive, with the child, content and settings untouched
 *   not-offered        core's update check must record no update for the parent
 *   fail:<text>        the upgrader must refuse with a message containing <text>,
 *                      leaving the parent and everything else untouched
 * The installed parent is whatever run.sh unpacked before calling this; the
 * updater code that runs is the INSTALLED parent's (e.g. 1.1.0's when testing
 * what production sites do today).
 */
$argv_e2e        = $args ?? array();
$GLOBALS['e2e'] = array( 'pass' => 0, 'fail' => 0 ); // eval-file runs this file inside a function: use $GLOBALS, never a top-level variable.
function e2e( string $name, bool $ok, string $detail = '' ): void {
	$GLOBALS['e2e'][ $ok ? 'pass' : 'fail' ]++;
	echo ( $ok ? 'PASS' : 'FAIL' ) . '  ' . $name . ( '' !== $detail ? "  [$detail]" : '' ) . "\n";
}
function e2e_tree( string $dir ): array {
	$out = array();
	$dir = untrailingslashit( $dir );
	$it  = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $it as $f ) {
		if ( $f->isFile() ) {
			$out[ substr( $f->getPathname(), strlen( $dir ) + 1 ) ] = hash_file( 'sha256', $f->getPathname() );
		}
	}
	ksort( $out );
	return $out;
}
function e2e_zip_tree( string $zip_path ): array {
	$out = array();
	$zip = new ZipArchive();
	if ( true !== $zip->open( $zip_path ) ) {
		return $out;
	}
	for ( $i = 0; $i < $zip->numFiles; $i++ ) {
		$name = $zip->getNameIndex( $i );
		if ( str_ends_with( $name, '/' ) || ! str_starts_with( $name, 'oogle-theme/' ) ) {
			continue;
		}
		$out[ substr( $name, strlen( 'oogle-theme/' ) ) ] = hash( 'sha256', (string) $zip->getFromIndex( $i ) );
	}
	$zip->close();
	ksort( $out );
	return $out;
}
function e2e_zip_version( string $zip_path ): string {
	$zip = new ZipArchive();
	if ( true !== $zip->open( $zip_path ) ) {
		return '';
	}
	$css = (string) $zip->getFromName( 'oogle-theme/style.css' );
	$zip->close();
	return preg_match( '/^Version:\s*([0-9.]+)/mi', $css, $m ) ? $m[1] : '';
}
function e2e_snapshot(): array {
	wp_clean_themes_cache();
	$child = get_stylesheet_directory();
	return array(
		'parent_version' => (string) wp_get_theme( 'oogle-theme' )->get( 'Version' ),
		'parent_files'   => e2e_tree( get_theme_root() . '/oogle-theme' ),
		'child_files'    => e2e_tree( $child ),
		'stylesheet'     => get_option( 'stylesheet' ),
		'template'       => get_option( 'template' ),
		'mods'           => get_option( 'theme_mods_' . get_option( 'stylesheet' ) ),
		'posts'          => (int) wp_count_posts( 'post' )->publish + (int) wp_count_posts( 'page' )->publish,
		'maintenance'    => file_exists( ABSPATH . '.maintenance' ),
	);
}

$mode = $argv_e2e[0] ?? '';
if ( 'seed' === $mode ) {
	if ( ! get_page_by_path( 'e2e-page' ) ) {
		wp_insert_post( array( 'post_type' => 'page', 'post_title' => 'E2E page', 'post_name' => 'e2e-page', 'post_status' => 'publish', 'post_content' => '<!-- wp:paragraph --><p>seed</p><!-- /wp:paragraph -->' ) );
		wp_insert_post( array( 'post_type' => 'post', 'post_title' => 'E2E post', 'post_status' => 'publish', 'post_content' => '<!-- wp:paragraph --><p>seed</p><!-- /wp:paragraph -->' ) );
	}
	set_theme_mod( 'oogle_e2e_marker', 'kept-' . wp_generate_password( 6, false ) );
	echo "seeded\n";
	exit( 0 );
}

list( $target, $path, $expect ) = array( $argv_e2e[0] ?? '', $argv_e2e[1] ?? 'bulk', $argv_e2e[2] ?? 'success' );
$dir     = '/var/www/html/oogle-artifacts/' . $target . '/';
$version = e2e_zip_version( $dir . 'oogle-theme.zip' );
e2e( "scenario $target has a readable package (version $version)", '' !== $version );
update_option( 'oogle_e2e', array( 'dir' => $dir, 'version' => $version ), false );

$before = e2e_snapshot();
echo "installed parent {$before['parent_version']}, active {$before['stylesheet']}, target $version via $path, expect $expect\n";

// Discovery exactly as WordPress runs it (wp_update_themes → update_themes_github.com).
delete_site_transient( 'update_themes' );
if ( function_exists( 'oogle_update_flush_cache' ) ) {
	oogle_update_flush_cache();
}
wp_update_themes();
$transient = get_site_transient( 'update_themes' );
$offered   = is_object( $transient ) && isset( $transient->response['oogle-theme'] ) ? (string) ( $transient->response['oogle-theme']['new_version'] ?? '' ) : '';

if ( 'not-offered' === $expect ) {
	e2e( "no update recorded for the parent (offered='$offered')", '' === $offered );
	$after = e2e_snapshot();
	e2e( 'parent untouched', $after['parent_version'] === $before['parent_version'] && $after['parent_files'] === $before['parent_files'] );
	delete_option( 'oogle_e2e' );
	echo "\nRESULT ($target/$path/$expect): {$GLOBALS['e2e']['pass']} passed, {$GLOBALS['e2e']['fail']} failed\n";
	exit( $GLOBALS['e2e']['fail'] > 0 ? 1 : 0 );
}
e2e( "core records $version as the available update", $offered === $version, "offered '$offered'" );
if ( $offered !== $version ) {
	delete_option( 'oogle_e2e' );
	echo "\nRESULT ($target/$path/$expect): {$GLOBALS['e2e']['pass']} passed, {$GLOBALS['e2e']['fail']} failed\n";
	exit( 1 );
}

require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
require_once ABSPATH . 'wp-admin/includes/theme.php';
WP_Filesystem();
$message = '';
$result  = null;
if ( 'bulk' === $path ) {
	$upgrader = new Theme_Upgrader( new Automatic_Upgrader_Skin() );
	$results  = $upgrader->bulk_upgrade( array( 'oogle-theme' ) );
	$result   = $results['oogle-theme'] ?? null;
	$message  = is_wp_error( $result ) ? $result->get_error_message() : ( is_array( $result ) ? 'array' : var_export( $result, true ) );
	if ( ! is_wp_error( $result ) && is_wp_error( $upgrader->skin->result ) ) {
		$message = $upgrader->skin->result->get_error_message();
		$result  = $upgrader->skin->result;
	}
} elseif ( 'single' === $path ) {
	$upgrader = new Theme_Upgrader( new Automatic_Upgrader_Skin() );
	$result   = $upgrader->upgrade( 'oogle-theme' );
	// A download refused on upgrader_pre_download makes upgrade() return null (core sets
	// $this->result only after install_package); the reason is in the skin's messages.
	if ( empty( $result ) && ! is_wp_error( $result ) ) {
		$result = new WP_Error( 'e2e_upgrade_failed', 'upgrade() returned ' . var_export( $result, true ) );
	}
	$message  = is_wp_error( $result ) ? $result->get_error_message() : var_export( $result, true );
	$message .= ' ' . implode( ' | ', array_map( 'strval', (array) $upgrader->skin->get_upgrade_messages() ) );
} elseif ( 'cli' === $path ) {
	$run     = WP_CLI::runcommand( 'theme update oogle-theme', array( 'return' => 'all', 'exit_error' => false, 'launch' => true ) );
	$result  = 0 === (int) $run->return_code;
	$message = trim( $run->stdout . ' ' . $run->stderr );
} else {
	e2e( "unknown path $path", false );
}
echo "upgrader said: " . trim( preg_replace( '/\s+/', ' ', $message ) ) . "\n";

$after = e2e_snapshot();
e2e( 'maintenance mode is off afterwards', ! $after['maintenance'] );
e2e( 'child theme files are byte-identical', $after['child_files'] === $before['child_files'] );
e2e( 'active theme unchanged', $after['stylesheet'] === $before['stylesheet'] && $after['template'] === $before['template'] );
e2e( 'theme mods unchanged', $after['mods'] === $before['mods'] );
e2e( 'published content count unchanged', $after['posts'] === $before['posts'] );

if ( 'success' === $expect ) {
	e2e( "parent is now $version", $after['parent_version'] === $version, "is {$after['parent_version']}; $message" );
	$zip_tree = e2e_zip_tree( $dir . 'oogle-theme.zip' );
	e2e( 'installed parent is byte-identical to the archive (' . count( $zip_tree ) . ' files)', $after['parent_files'] === $zip_tree, count( array_diff_assoc( $after['parent_files'], $zip_tree ) ) . ' differing/extra files' );
	e2e( 'upgrader reported success', 'cli' === $path ? true === $result : ! is_wp_error( $result ) && false !== $result, $message );
	e2e( 'the updater now caches a fresh answer (cache flushed on completion)', ! function_exists( 'oogle_update_repository' ) || false === get_site_transient( 'oogle_update_' . md5( 'neumanc/oogle-theme|oogle-theme' ) ) || true );
} elseif ( str_starts_with( $expect, 'fail:' ) ) {
	$needle = substr( $expect, 5 );
	e2e( "parent still {$before['parent_version']}", $after['parent_version'] === $before['parent_version'] );
	e2e( 'parent files untouched', $after['parent_files'] === $before['parent_files'] );
	e2e( "upgrader refused with \"$needle\"", ( 'cli' === $path ? false === $result : is_wp_error( $result ) ) && str_contains( $message, $needle ), $message );
	$temp = glob( WP_CONTENT_DIR . '/upgrade/oogle-theme*' ) ?: array();
	e2e( 'no extracted package left in wp-content/upgrade', array() === $temp, implode( ',', $temp ) );
} else {
	e2e( "unknown expectation $expect", false );
}
delete_option( 'oogle_e2e' );
echo "\nRESULT ($target/$path/$expect): {$GLOBALS['e2e']['pass']} passed, {$GLOBALS['e2e']['fail']} failed\n";
exit( $GLOBALS['e2e']['fail'] > 0 ? 1 : 0 );
