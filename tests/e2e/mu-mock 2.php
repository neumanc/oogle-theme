<?php
/**
 * Oogle Theme — end-to-end upgrade lab: GitHub mock (dev only; never shipped).
 *
 * Copied into wp-content/mu-plugins/ of the e2e lab by tests/e2e/run.sh. While
 * the option "oogle_e2e" names a scenario directory, every request the updater
 * makes to GitHub is answered from that directory's files, so the REAL core
 * upgrader runs against REAL archives with no network:
 *   https://api.github.com/repos/<o>/<r>/releases?per_page=30 and /releases/latest
 *       → one published release, tag v<version>, assets = the files present
 *         (oogle-theme.zip, .sha256, .sig) at GitHub's canonical asset URLs
 *   https://raw.githubusercontent.com/<o>/<r>/<tag>/style.css
 *       → style.css read from inside the zip (so it always matches the package)
 *   https://github.com/<o>/<r>/releases/download/<tag>/<asset>
 *       → the file; streamed to $args['filename'] when core downloads
 *   https://api.wordpress.org/themes/update-check/…
 *       → an empty answer (neither theme is a wordpress.org theme)
 * Anything else is refused (WP_Error), so a test can never reach the network.
 *
 * @package Oogle
 */

add_filter(
	'pre_http_request',
	static function ( $pre, $args, $url ) {
		$scenario = get_option( 'oogle_e2e' );
		if ( ! is_array( $scenario ) || empty( $scenario['dir'] ) ) {
			return $pre;
		}
		$dir     = rtrim( (string) $scenario['dir'], '/' ) . '/';
		$version = (string) $scenario['version'];
		$tag     = 'v' . $version;
		$ok      = static fn( string $body, array $headers = array() ) => array(
			'response' => array( 'code' => 200, 'message' => 'OK' ),
			'body'     => $body,
			'headers'  => $headers,
			'cookies'  => array(),
			'filename' => null,
		);
		$host    = (string) wp_parse_url( $url, PHP_URL_HOST );
		$path    = (string) wp_parse_url( $url, PHP_URL_PATH );

		if ( 'api.wordpress.org' === $host ) {
			// Core's own update checks run around an upgrade (and fatal on an
			// answer of the wrong shape); answer each endpoint with its empty form.
			if ( str_contains( $path, '/plugins/update-check/' ) ) {
				return $ok( wp_json_encode( array( 'plugins' => array(), 'no_update' => array(), 'translations' => array() ) ) );
			}
			if ( str_contains( $path, '/themes/update-check/' ) ) {
				return $ok( wp_json_encode( array( 'themes' => array(), 'no_update' => array(), 'translations' => array() ) ) );
			}
			if ( str_contains( $path, '/core/version-check/' ) ) {
				return $ok( wp_json_encode( array( 'offers' => array(), 'translations' => array() ) ) );
			}
			if ( str_contains( $path, '/themes/info/' ) ) {
				// What wordpress.org answers for a theme it does not host (WP-CLI asks before updating).
				return $ok( wp_json_encode( array( 'error' => 'Theme not found' ) ) );
			}
			return $ok( '{}' );
		}
		if ( 'api.github.com' === $host && preg_match( '#^/repos/([^/]+)/([^/]+)/releases#', $path, $m ) ) {
			$base   = sprintf( 'https://github.com/%s/%s/releases/download/%s/', $m[1], $m[2], $tag );
			$assets = array();
			foreach ( array( 'oogle-theme.zip', 'oogle-theme.zip.sha256', 'oogle-theme.zip.sig' ) as $name ) {
				if ( is_file( $dir . $name ) ) {
					$assets[] = array( 'name' => $name, 'browser_download_url' => $base . $name );
				}
			}
			$release = array(
				'tag_name'   => $tag,
				'draft'      => false,
				'prerelease' => false,
				'html_url'   => sprintf( 'https://github.com/%s/%s/releases/tag/%s', $m[1], $m[2], $tag ),
				'assets'     => $assets,
			);
			return $ok( wp_json_encode( str_ends_with( $path, '/latest' ) ? $release : array( $release ) ) );
		}
		if ( 'raw.githubusercontent.com' === $host && str_ends_with( $path, '/style.css' ) ) {
			$zip = new ZipArchive();
			if ( true === $zip->open( $dir . 'oogle-theme.zip' ) ) {
				$css = $zip->getFromName( 'oogle-theme/style.css' );
				$zip->close();
				if ( is_string( $css ) ) {
					return $ok( $css );
				}
			}
			return array( 'response' => array( 'code' => 404, 'message' => 'Not Found' ), 'body' => '', 'headers' => array(), 'cookies' => array(), 'filename' => null );
		}
		if ( 'github.com' === $host && preg_match( '#/releases/download/[^/]+/(oogle-theme\.zip(?:\.sha256|\.sig)?)$#', $path, $m ) && is_file( $dir . $m[1] ) ) {
			if ( ! empty( $args['stream'] ) && ! empty( $args['filename'] ) ) {
				copy( $dir . $m[1], $args['filename'] );
				return $ok( '', array( 'content-length' => (string) filesize( $dir . $m[1] ) ) ) + array( 'filename' => $args['filename'] );
			}
			return $ok( (string) file_get_contents( $dir . $m[1] ) );
		}
		return new WP_Error( 'oogle_e2e_offline', 'e2e lab: unexpected request to ' . $url );
	},
	1,
	3
);
