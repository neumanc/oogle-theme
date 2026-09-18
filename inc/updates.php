<?php
/**
 * Updates from GitHub Releases.
 *
 * WordPress already owns the update pipeline: the "Update URI" header in
 * style.css makes core call the `update_themes_{hostname}` filter instead of
 * WordPress.org, and whatever that filter returns flows into the normal
 * Appearance → Themes update UI, auto-updates, WP-CLI and the upgrader's
 * temp-backup/rollback. This file only answers that one question — "what is
 * the latest release?" — by reading the repository's latest GitHub Release.
 *
 * Contract with the release (docs/RELEASES.md, .github/workflows/release.yml):
 *  - a published, non-draft, non-prerelease Release tagged vX.Y.Z;
 *  - one asset named "<theme-dir>.zip" (e.g. oogle-theme.zip) whose single
 *    top-level folder is the theme directory (git archive --prefix);
 *  - style.css at that tag carries the release's "Requires at least" and
 *    "Requires PHP" so core can refuse an update the host cannot run.
 *
 * Safety: read-only public API calls over HTTPS with certificate verification
 * (core default), 10 s timeout, redirects never followed (a 3xx is a failure),
 * every field validated before use, results cached in a site transient (6 h;
 * 1 h after a failure so a rate-limited or offline host is not hammered). The
 * package URL must be the release-asset URL on github.com for this very
 * owner/repo/asset — anything else is rejected and no update is offered. No
 * secret is ever stored in the theme; an optional OOGLE_GITHUB_TOKEN constant
 * in wp-config.php only raises the API rate limit and is sent to api.github.com
 * alone, never to any other host, filtered URL or redirect target. Private
 * repositories are not supported: release assets of a private repository
 * cannot be downloaded by core's downloader. Use a public repository (the
 * theme is GPL) or a dedicated updater plugin.
 *
 * Nothing a site owns lives inside the parent directory (child theme,
 * mu-plugins, uploads, database), so replacing the directory is safe; the
 * upgrade test in docs/UPGRADE.md proves it.
 *
 * @package Oogle
 * @since   1.0.0
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

/**
 * Owner and repository parsed from the Update URI header, or null when the
 * header is missing or does not point at github.com.
 *
 * @return array{owner:string, repo:string}|null
 */
function oogle_update_repository(): ?array {
	$uri  = (string) wp_get_theme( get_template() )->get( 'UpdateURI' );
	$host = wp_parse_url( $uri, PHP_URL_HOST );
	$path = (string) wp_parse_url( $uri, PHP_URL_PATH );
	if ( 'github.com' !== $host ) {
		return null;
	}
	$parts = array_values( array_filter( explode( '/', $path ) ) );
	if ( count( $parts ) < 2 ) {
		return null;
	}
	$owner = $parts[0];
	$repo  = preg_replace( '/\.git$/', '', $parts[1] );
	if ( ! preg_match( '/^[A-Za-z0-9_.-]+$/', $owner ) || ! preg_match( '/^[A-Za-z0-9_.-]+$/', (string) $repo ) ) {
		return null;
	}
	return array(
		'owner' => $owner,
		'repo'  => (string) $repo,
	);
}

/**
 * Whether the updater is active. Sites deployed from git may switch it off.
 *
 * @return bool
 */
function oogle_updates_enabled(): bool {
	/**
	 * Filter whether the theme checks GitHub Releases for updates.
	 *
	 * @param bool $enabled Default true when Update URI points at github.com.
	 */
	return (bool) apply_filters( 'oogle/updates/enabled', null !== oogle_update_repository() );
}

/**
 * Hosts that may receive the OOGLE_GITHUB_TOKEN. Only the REST API is
 * rate-limited in a way the token improves; style.css and the release asset
 * are public files fetched anonymously. Deliberately not filterable.
 *
 * @return string[]
 */
function oogle_update_token_hosts(): array {
	return array( 'api.github.com' );
}

/**
 * Strictly parse an https URL and return its lower-cased host, or null when
 * the URL is anything other than a plain https://host/path URL: no scheme or
 * a non-https scheme, no host, userinfo ("user@"), an explicit port, or a
 * host that is not a DNS name (IP literals are refused). The host is returned
 * for EXACT comparison by the caller; nothing here treats subdomains or
 * substrings as equivalent.
 *
 * @param string $url URL to parse.
 * @return string|null
 */
function oogle_update_url_host( string $url ): ?string {
	$parts = wp_parse_url( $url );
	if ( ! is_array( $parts ) || empty( $parts['host'] ) || ! is_string( $parts['host'] ) ) {
		return null;
	}
	if ( 'https' !== strtolower( (string) ( $parts['scheme'] ?? '' ) ) ) {
		return null;
	}
	if ( isset( $parts['user'] ) || isset( $parts['pass'] ) || isset( $parts['port'] ) ) {
		return null;
	}
	$host = strtolower( $parts['host'] );
	if ( ! preg_match( '/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)*\.[a-z][a-z0-9-]*$/', $host ) ) {
		return null; // Not a dotted DNS name with an alphabetic TLD: rejects IPv4/IPv6 literals and single labels.
	}
	return $host;
}

/**
 * Whether a release-asset URL is the one GitHub serves for this repository's
 * asset: https://github.com/{owner}/{repo}/releases/download/{tag}/{asset},
 * with no query string or fragment. Host comparison is exact; owner and
 * repository are compared case-insensitively (GitHub treats them so); the
 * asset file name must match exactly.
 *
 * @param string                           $url   browser_download_url from the API.
 * @param array{owner:string, repo:string} $repo  Repository from the Update URI.
 * @param string                           $asset Expected asset file name.
 * @return bool
 */
function oogle_update_is_release_asset_url( string $url, array $repo, string $asset ): bool {
	if ( 'github.com' !== oogle_update_url_host( $url ) ) {
		return false;
	}
	$parts = wp_parse_url( $url );
	if ( ! is_array( $parts ) || isset( $parts['query'] ) || isset( $parts['fragment'] ) || empty( $parts['path'] ) ) {
		return false;
	}
	$segments = explode( '/', ltrim( (string) $parts['path'], '/' ) );
	if ( 6 !== count( $segments ) ) {
		return false;
	}
	list( $owner, $name, $releases, $download, $tag, $file ) = $segments;
	return 0 === strcasecmp( rawurldecode( $owner ), $repo['owner'] )
		&& 0 === strcasecmp( rawurldecode( $name ), $repo['repo'] )
		&& 'releases' === $releases
		&& 'download' === $download
		&& '' !== $tag
		&& rawurldecode( $file ) === $asset;
}

/**
 * Perform one GET against GitHub with sane defaults.
 *
 * Redirects are never followed: neither endpoint the updater uses redirects
 * in normal operation, and core's HTTP layer re-sends the original headers —
 * Authorization included — to whatever host a Location header names. A 3xx
 * therefore fails closed (no update offered) instead of being chased.
 *
 * The token is attached only when the FINAL url (after filters) is https on
 * a host in oogle_update_token_hosts(). Both the token and the no-redirect
 * rule are applied after the request-args filter, so a filter can adjust
 * timeouts or proxy settings but cannot re-enable redirects or redirect the
 * token elsewhere.
 *
 * @param string $url    Absolute URL.
 * @param string $accept Accept header.
 * @return string|WP_Error Body on HTTP 200, WP_Error otherwise.
 */
function oogle_update_http_get( string $url, string $accept ) {
	$args = array(
		'timeout'     => 10,
		'redirection' => 0,
		'headers'     => array(
			'Accept'     => $accept,
			'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url( '/' ),
		),
	);

	/**
	 * Filter the request arguments for update checks (e.g. a proxy).
	 *
	 * The Authorization header is added after this filter runs and only for
	 * trusted hosts, and 'redirection' is forced back to 0 afterwards; a
	 * filter cannot cause the token to be sent elsewhere or a 3xx to be chased.
	 *
	 * @param array<string, mixed> $args wp_remote_get() arguments.
	 * @param string               $url  Request URL.
	 */
	$args                = (array) apply_filters( 'oogle/updates/request_args', $args, $url );
	$args['redirection'] = 0;

	if (
		defined( 'OOGLE_GITHUB_TOKEN' ) && is_string( OOGLE_GITHUB_TOKEN ) && '' !== OOGLE_GITHUB_TOKEN
		&& in_array( oogle_update_url_host( $url ), oogle_update_token_hosts(), true )
	) {
		if ( ! isset( $args['headers'] ) || ! is_array( $args['headers'] ) ) {
			$args['headers'] = array();
		}
		$args['headers']['Authorization'] = 'Bearer ' . OOGLE_GITHUB_TOKEN;
	}

	$response = wp_remote_get( $url, $args );
	if ( is_wp_error( $response ) ) {
		return $response;
	}
	$code = (int) wp_remote_retrieve_response_code( $response );
	if ( 200 !== $code ) {
		return new WP_Error( 'oogle_update_http', sprintf( 'GitHub returned HTTP %d for %s', $code, $url ) );
	}
	return (string) wp_remote_retrieve_body( $response );
}

/**
 * Read "Requires at least" and "Requires PHP" from a style.css body.
 *
 * @param string $css style.css contents.
 * @return array{requires:string, requires_php:string}
 */
function oogle_update_parse_requirements( string $css ): array {
	$out = array(
		'requires'     => '',
		'requires_php' => '',
	);
	if ( preg_match( '/^[ \t\/*#@]*Requires at least:\s*(.+)$/mi', $css, $m ) ) {
		$out['requires'] = trim( $m[1] );
	}
	if ( preg_match( '/^[ \t\/*#@]*Requires PHP:\s*(.+)$/mi', $css, $m ) ) {
		$out['requires_php'] = trim( $m[1] );
	}
	return $out;
}

/**
 * Latest release, validated and cached.
 *
 * @param bool $force Ignore the cache.
 * @return array{version:string, url:string, package:string, requires:string, requires_php:string}|null
 */
function oogle_update_latest_release( bool $force = false ): ?array {
	$repo = oogle_update_repository();
	if ( null === $repo ) {
		return null;
	}
	$key = 'oogle_update_' . md5( $repo['owner'] . '/' . $repo['repo'] . '|' . get_template() );
	if ( ! $force ) {
		$cached = get_site_transient( $key );
		if ( is_array( $cached ) ) {
			return isset( $cached['version'] ) ? $cached : null; // A cached failure is array( 'error' => … ).
		}
	}

	$release = oogle_update_fetch_release( $repo );
	if ( is_wp_error( $release ) ) {
		set_site_transient( $key, array( 'error' => $release->get_error_message() ), HOUR_IN_SECONDS );
		return null;
	}
	set_site_transient( $key, $release, 6 * HOUR_IN_SECONDS );
	return $release;
}

/**
 * Fetch and validate the latest release from the GitHub API.
 *
 * @param array{owner:string, repo:string} $repo Repository.
 * @return array{version:string, url:string, package:string, requires:string, requires_php:string}|WP_Error
 */
function oogle_update_fetch_release( array $repo ) {
	$api = sprintf( 'https://api.github.com/repos/%s/%s/releases/latest', rawurlencode( $repo['owner'] ), rawurlencode( $repo['repo'] ) );

	/**
	 * Filter the releases API URL (used by the upgrade test to point at a fixture).
	 *
	 * @param string $api URL.
	 */
	$api = (string) apply_filters( 'oogle/updates/api_url', $api );

	$body = oogle_update_http_get( $api, 'application/vnd.github+json' );
	if ( is_wp_error( $body ) ) {
		return $body;
	}
	$data = json_decode( $body, true );
	if ( ! is_array( $data ) || empty( $data['tag_name'] ) || ! is_string( $data['tag_name'] ) ) {
		return new WP_Error( 'oogle_update_malformed', 'Release JSON has no tag_name.' );
	}
	if ( ! empty( $data['draft'] ) || ! empty( $data['prerelease'] ) ) {
		return new WP_Error( 'oogle_update_prerelease', 'Latest release is a draft or prerelease.' );
	}
	$version = ltrim( $data['tag_name'], 'vV' );
	if ( ! preg_match( '/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?$/', $version ) ) {
		return new WP_Error( 'oogle_update_version', 'Release tag is not a semantic version: ' . $data['tag_name'] );
	}

	// The package must be a release asset named after the theme directory
	// (oogle-theme.zip or oogle-theme-1.2.3.zip), never GitHub's auto zipball,
	// and its download URL must be GitHub's own release-asset URL for this
	// owner/repo/asset: exact host github.com, https, no userinfo/port/query.
	$slug    = get_template();
	$package = '';
	foreach ( (array) ( $data['assets'] ?? array() ) as $asset ) {
		if ( ! is_array( $asset ) || empty( $asset['name'] ) || ! is_string( $asset['name'] ) || empty( $asset['browser_download_url'] ) || ! is_string( $asset['browser_download_url'] ) ) {
			continue;
		}
		if ( ! preg_match( '/^' . preg_quote( $slug, '/' ) . '(?:-v?[0-9][0-9A-Za-z.-]*)?\.zip$/', $asset['name'] ) ) {
			continue;
		}
		if ( ! oogle_update_is_release_asset_url( $asset['browser_download_url'], $repo, $asset['name'] ) ) {
			return new WP_Error( 'oogle_update_asset_url', sprintf( 'Release %s asset %s has an unexpected download URL.', $data['tag_name'], $asset['name'] ) );
		}
		$package = $asset['browser_download_url'];
		break;
	}
	if ( '' === $package ) {
		return new WP_Error( 'oogle_update_asset', sprintf( 'Release %s has no %s.zip asset.', $data['tag_name'], $slug ) );
	}

	// Requirements of the NEW version, from its own style.css. Fall back to the
	// installed headers if that fetch fails; core still enforces those.
	$installed = wp_get_theme( $slug );
	$req       = array(
		'requires'     => (string) $installed->get( 'RequiresWP' ),
		'requires_php' => (string) $installed->get( 'RequiresPHP' ),
	);
	$raw       = sprintf( 'https://raw.githubusercontent.com/%s/%s/%s/style.css', rawurlencode( $repo['owner'] ), rawurlencode( $repo['repo'] ), rawurlencode( $data['tag_name'] ) );
	$raw       = (string) apply_filters( 'oogle/updates/style_url', $raw, $data['tag_name'] );
	$css       = oogle_update_http_get( $raw, 'text/plain' );
	if ( ! is_wp_error( $css ) ) {
		$parsed = oogle_update_parse_requirements( $css );
		foreach ( $parsed as $k => $v ) {
			if ( '' !== $v ) {
				$req[ $k ] = $v;
			}
		}
	}

	return array(
		'version'      => $version,
		'url'          => is_string( $data['html_url'] ?? null ) && 'github.com' === oogle_update_url_host( $data['html_url'] ) ? esc_url_raw( $data['html_url'] ) : '',
		'package'      => esc_url_raw( $package ),
		'requires'     => $req['requires'],
		'requires_php' => $req['requires_php'],
	);
}

/**
 * Answer core's update check for this theme.
 *
 * Core compares `version` with the installed version itself and files the
 * result under response/no_update, so the latest release is returned whenever
 * it is known.
 *
 * @param array<string, mixed>|false $update           Existing value (false).
 * @param array<string, mixed>       $theme_data       Theme headers.
 * @param string                     $theme_stylesheet Theme directory being checked.
 * @return array<string, mixed>|false
 */
function oogle_update_check( $update, array $theme_data, string $theme_stylesheet ) {
	if ( get_template() !== $theme_stylesheet || ! oogle_updates_enabled() ) {
		return $update;
	}
	$release = oogle_update_latest_release();
	if ( null === $release ) {
		return $update;
	}
	return array(
		'theme'        => $theme_stylesheet,
		'version'      => $release['version'],
		'url'          => $release['url'],
		'package'      => $release['package'],
		'requires'     => $release['requires'],
		'requires_php' => $release['requires_php'],
	);
}
add_filter( 'update_themes_github.com', 'oogle_update_check', 10, 3 );

/**
 * Install into the theme's real directory whatever the zip's top folder is
 * called (a hand-built zip may say oogle-theme-1.2.3/).
 *
 * @param string|WP_Error      $source        Extracted source path.
 * @param string               $remote_source Working directory.
 * @param WP_Upgrader          $upgrader      Upgrader.
 * @param array<string, mixed> $hook_extra    Context; 'theme' is the directory being updated.
 * @return string|WP_Error
 */
function oogle_update_source_selection( $source, string $remote_source, WP_Upgrader $upgrader, array $hook_extra = array() ) {
	global $wp_filesystem;
	if ( is_wp_error( $source ) || empty( $hook_extra['theme'] ) || get_template() !== $hook_extra['theme'] ) {
		return $source;
	}
	$desired = trailingslashit( $remote_source ) . get_template() . '/';
	if ( untrailingslashit( (string) $source ) === untrailingslashit( $desired ) ) {
		return $source;
	}
	if ( $wp_filesystem && $wp_filesystem->move( (string) $source, $desired ) ) {
		return $desired;
	}
	return new WP_Error( 'oogle_update_rename', __( 'Could not rename the extracted theme folder.', 'oogle' ) );
}
add_filter( 'upgrader_source_selection', 'oogle_update_source_selection', 10, 4 );

/**
 * Forget the cached release when the site asks for a fresh check ("Check
 * again" on the Updates screen deletes the update_themes transient) and after
 * this theme has been updated.
 *
 * @return void
 */
function oogle_update_flush_cache(): void {
	$repo = oogle_update_repository();
	if ( null !== $repo ) {
		delete_site_transient( 'oogle_update_' . md5( $repo['owner'] . '/' . $repo['repo'] . '|' . get_template() ) );
	}
}
add_action( 'delete_site_transient_update_themes', 'oogle_update_flush_cache' );
add_action(
	'upgrader_process_complete',
	static function ( WP_Upgrader $upgrader, array $hook_extra ): void {
		if ( 'theme' === ( $hook_extra['type'] ?? '' ) && in_array( get_template(), (array) ( $hook_extra['themes'] ?? array() ), true ) ) {
			oogle_update_flush_cache();
		}
	},
	10,
	2
);
