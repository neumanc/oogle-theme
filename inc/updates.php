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
 *    top-level folder is the theme directory (git archive --prefix), plus its
 *    "<asset>.sha256" sidecar (mandatory: a release without one is not offered);
 *  - style.css at that tag carries the same Version and both "Requires at
 *    least" and "Requires PHP", read from the tag — never inherited from the
 *    installed copy; a release whose requirements cannot be read is not offered.
 *
 * Major-version boundary: only releases of the installed major are offered
 * (a 1.x site follows 1.x; 2.0.0 needs the oogle/updates/allowed_major
 * opt-in or a one-time manual upload). Discovery reads the newest 30
 * releases in one request so a later 1.x maintenance release is still found
 * after 2.x exists.
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
 * Before core replaces the installed parent, two more gates run on every
 * upgrader path (single, bulk, automatic, WP-CLI, other plugins driving
 * Theme_Upgrader): the downloaded zip must match the release's published
 * SHA-256 (integrity of the transfer — not publisher authentication, since
 * the sidecar lives in the same Release), and the extracted package must be
 * this theme at exactly the version being installed with compatible,
 * declared requirements, a valid theme.json and a block-theme index.
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
 * Read the headers the updater needs from a style.css body: the version and
 * the two requirements. Values that are not plain dotted version numbers are
 * returned as '' so the caller treats them as missing.
 *
 * @param string $css style.css contents.
 * @return array{version:string, requires:string, requires_php:string}
 */
function oogle_update_parse_requirements( string $css ): array {
	$out = array(
		'version'      => '',
		'requires'     => '',
		'requires_php' => '',
	);
	foreach ( array(
		'version'      => 'Version',
		'requires'     => 'Requires at least',
		'requires_php' => 'Requires PHP',
	) as $key => $header ) {
		if ( preg_match( '/^[ \t\/*#@]*' . preg_quote( $header, '/' ) . ':\s*([^\r\n]+)$/mi', $css, $m ) ) {
			$value = trim( $m[1] );
			if ( preg_match( '/^\d+(?:\.\d+){1,3}$/', $value ) ) {
				$out[ $key ] = $value;
			}
		}
	}
	return $out;
}

/**
 * The major version this installation follows automatically.
 *
 * Only releases of the same major as the installed parent are ever offered:
 * a 1.x fleet picks up 1.x patch and minor releases and is never moved to
 * 2.x by WordPress's routine check. A site that has prepared its child theme
 * for a new major opts in through the filter below (or updates by uploading
 * the new major's zip once; from then on it follows that major).
 *
 * @param string $installed Installed version.
 * @return int|null Major to follow, or null to allow any major.
 */
function oogle_update_allowed_major( string $installed ): ?int {
	$major = (int) explode( '.', $installed )[0];

	/**
	 * Filter the major version the updater is allowed to offer.
	 *
	 * Return the integer major to follow (default: the installed major), or
	 * null to follow the newest release of any major. Example, in a site
	 * mu-plugin, once the child theme has been migrated to 2.x:
	 * add_filter( 'oogle/updates/allowed_major', fn() => 2 );
	 *
	 * @param int|null $major     Major to follow.
	 * @param string   $installed Installed parent version.
	 */
	$allowed = apply_filters( 'oogle/updates/allowed_major', $major, $installed );
	if ( null === $allowed || false === $allowed ) {
		return null;
	}
	return is_numeric( $allowed ) ? (int) $allowed : $major;
}

/**
 * Latest release, validated and cached.
 *
 * @param bool $force Ignore the cache.
 * @return array{version:string, url:string, package:string, checksum:string, requires:string, requires_php:string}|null
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
 * Fetch the newest release this installation may move to and validate it.
 *
 * Reads the repository's recent releases (one bounded request, newest first)
 * rather than /releases/latest, so a 1.x site still finds a later 1.x
 * maintenance release after 2.0.0 has been published. A candidate must be
 * published (not draft, not prerelease), tagged vX.Y.Z, in the allowed
 * major, and carry both the theme zip and its .sha256 sidecar as
 * github.com release assets. Its own style.css at the tag must state the
 * same version and both requirements; if any of that cannot be established
 * the release is not offered ("fail closed"). Nothing is ever inherited from
 * the installed copy.
 *
 * @param array{owner:string, repo:string} $repo      Repository.
 * @param string|null                      $installed Installed version (defaults to the parent's style.css; tests pass one).
 * @return array{version:string, url:string, package:string, checksum:string, requires:string, requires_php:string}|WP_Error
 */
function oogle_update_fetch_release( array $repo, ?string $installed = null ) {
	$installed = $installed ?? (string) wp_get_theme( get_template() )->get( 'Version' );
	$api       = sprintf( 'https://api.github.com/repos/%s/%s/releases?per_page=30', rawurlencode( $repo['owner'] ), rawurlencode( $repo['repo'] ) );

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
	if ( ! is_array( $data ) ) {
		return new WP_Error( 'oogle_update_malformed', 'Release list is not valid JSON.' );
	}
	// A single release object (e.g. a fixture answering the old /latest shape) is treated as a list of one.
	if ( isset( $data['tag_name'] ) ) {
		$data = array( $data );
	}

	$major     = oogle_update_allowed_major( $installed );
	$candidate = null;
	foreach ( $data as $entry ) {
		if ( ! is_array( $entry ) || empty( $entry['tag_name'] ) || ! is_string( $entry['tag_name'] ) ) {
			continue;
		}
		if ( ! empty( $entry['draft'] ) || ! empty( $entry['prerelease'] ) ) {
			continue;
		}
		$version = ltrim( $entry['tag_name'], 'vV' );
		if ( ! preg_match( '/^\d+\.\d+\.\d+$/', $version ) ) {
			continue; // Only final releases are ever offered; -rc/-beta tags are skipped even if published.
		}
		if ( null !== $major && (int) explode( '.', $version )[0] !== $major ) {
			continue;
		}
		if ( null === $candidate || version_compare( $version, $candidate['version'], '>' ) ) {
			$candidate = array(
				'version' => $version,
				'entry'   => $entry,
			);
		}
	}
	if ( null === $candidate ) {
		return new WP_Error( 'oogle_update_no_release', sprintf( 'No published %s release found.', null === $major ? '' : $major . '.x' ) );
	}

	$version = $candidate['version'];
	$data    = $candidate['entry'];

	// The package must be a release asset named after the theme directory
	// (oogle-theme.zip or oogle-theme-1.2.3.zip), never GitHub's auto zipball,
	// and its download URL must be GitHub's own release-asset URL for this
	// owner/repo/asset: exact host github.com, https, no userinfo/port/query.
	// The same rule applies to the .sha256 sidecar, which is mandatory.
	$slug   = get_template();
	$assets = array();
	foreach ( (array) ( $data['assets'] ?? array() ) as $asset ) {
		if ( ! is_array( $asset ) || empty( $asset['name'] ) || ! is_string( $asset['name'] ) || empty( $asset['browser_download_url'] ) || ! is_string( $asset['browser_download_url'] ) ) {
			continue;
		}
		$assets[ $asset['name'] ] = $asset['browser_download_url'];
	}
	$package  = '';
	$checksum = '';
	foreach ( $assets as $name => $url ) {
		if ( ! preg_match( '/^' . preg_quote( $slug, '/' ) . '(?:-v?[0-9][0-9A-Za-z.-]*)?\.zip$/', $name ) ) {
			continue;
		}
		if ( ! oogle_update_is_release_asset_url( $url, $repo, $name ) ) {
			return new WP_Error( 'oogle_update_asset_url', sprintf( 'Release %s asset %s has an unexpected download URL.', $data['tag_name'], $name ) );
		}
		$package = $url;
		if ( isset( $assets[ $name . '.sha256' ] ) ) {
			if ( ! oogle_update_is_release_asset_url( $assets[ $name . '.sha256' ], $repo, $name . '.sha256' ) ) {
				return new WP_Error( 'oogle_update_asset_url', sprintf( 'Release %s asset %s.sha256 has an unexpected download URL.', $data['tag_name'], $name ) );
			}
			$checksum = $assets[ $name . '.sha256' ];
		}
		break;
	}
	if ( '' === $package ) {
		return new WP_Error( 'oogle_update_asset', sprintf( 'Release %s has no %s.zip asset.', $data['tag_name'], $slug ) );
	}
	if ( '' === $checksum ) {
		return new WP_Error( 'oogle_update_checksum_missing', sprintf( 'Release %s publishes no .sha256 for its package; not offered.', $data['tag_name'] ) );
	}

	// Requirements of the NEW version come from its own style.css at the tag,
	// never from the installed copy. Anything short of a 200 with both
	// headers and a matching Version means the release cannot be assessed and
	// is not offered.
	$raw = sprintf( 'https://raw.githubusercontent.com/%s/%s/%s/style.css', rawurlencode( $repo['owner'] ), rawurlencode( $repo['repo'] ), rawurlencode( $data['tag_name'] ) );
	$raw = (string) apply_filters( 'oogle/updates/style_url', $raw, $data['tag_name'] );
	$css = oogle_update_http_get( $raw, 'text/plain' );
	if ( is_wp_error( $css ) ) {
		return new WP_Error( 'oogle_update_requirements', sprintf( 'Requirements of release %s could not be read: %s', $data['tag_name'], $css->get_error_message() ) );
	}
	$req = oogle_update_parse_requirements( $css );
	if ( '' === $req['requires'] || '' === $req['requires_php'] ) {
		return new WP_Error( 'oogle_update_requirements', sprintf( 'Release %s does not declare both "Requires at least" and "Requires PHP"; not offered.', $data['tag_name'] ) );
	}
	if ( $req['version'] !== $version ) {
		return new WP_Error( 'oogle_update_version_mismatch', sprintf( 'Release %s style.css declares version "%s"; not offered.', $data['tag_name'], $req['version'] ) );
	}

	return array(
		'version'      => $version,
		'url'          => is_string( $data['html_url'] ?? null ) && 'github.com' === oogle_update_url_host( $data['html_url'] ) ? esc_url_raw( $data['html_url'] ) : '',
		'package'      => esc_url_raw( $package ),
		'checksum'     => esc_url_raw( $checksum ),
		'requires'     => $req['requires'],
		'requires_php' => $req['requires_php'],
	);
}

/**
 * Answer core's update check for this theme.
 *
 * Core compares `version` with the installed version itself and files the
 * result under response/no_update, so the latest release is returned whenever
 * it is known. Core also refuses to apply a release whose requirements this
 * host does not meet; oogle_update_validate_package() repeats that check on
 * the extracted package for the upgrader paths that skip it.
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
 * Whether an upgrader run is replacing this parent theme, from the context
 * core passes to its hooks.
 *
 * @param array<string, mixed> $hook_extra Upgrader context.
 * @return bool
 */
function oogle_update_is_own_upgrade( array $hook_extra ): bool {
	return isset( $hook_extra['theme'] ) && get_template() === $hook_extra['theme'];
}

/**
 * Version the update pipeline is about to install, from the same transient
 * every upgrader path reads its package from, or '' when unknown.
 *
 * @return string
 */
function oogle_update_expected_version(): string {
	$current = get_site_transient( 'update_themes' );
	$entry   = is_object( $current ) && isset( $current->response[ get_template() ] ) ? $current->response[ get_template() ] : null;
	$version = is_array( $entry ) ? ( $entry['new_version'] ?? '' ) : ( is_object( $entry ) ? ( $entry->new_version ?? '' ) : '' );
	return is_string( $version ) && preg_match( '/^\d+\.\d+\.\d+$/', $version ) ? $version : '';
}

/**
 * Fetch the published SHA-256 of the package (the .sha256 sidecar asset).
 *
 * The sidecar is a public file behind GitHub's asset redirect, so this uses
 * core's safe client with redirects (no token is ever attached: this never
 * goes through oogle_update_http_get()). Returns the 64-hex digest, or a
 * WP_Error when it cannot be read or does not look like one.
 *
 * @param string $url Sidecar URL from the validated release.
 * @return string|WP_Error
 */
function oogle_update_published_checksum( string $url ) {
	if ( 'github.com' !== oogle_update_url_host( $url ) ) {
		return new WP_Error( 'oogle_update_checksum_url', 'Checksum must be a github.com release asset.' );
	}
	$response = wp_safe_remote_get(
		$url,
		array(
			'timeout'             => 15,
			'redirection'         => 3,
			'limit_response_size' => 4096,
			'headers'             => array( 'Accept' => 'text/plain' ),
		)
	);
	if ( is_wp_error( $response ) ) {
		return $response;
	}
	if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
		return new WP_Error( 'oogle_update_checksum_http', sprintf( 'Checksum download returned HTTP %d.', (int) wp_remote_retrieve_response_code( $response ) ) );
	}
	if ( preg_match( '/^\s*([a-f0-9]{64})\b/i', (string) wp_remote_retrieve_body( $response ), $m ) ) {
		return strtolower( $m[1] );
	}
	return new WP_Error( 'oogle_update_checksum_malformed', 'Checksum file does not contain a SHA-256 digest.' );
}

/**
 * Download the package for an update of this theme and verify it against
 * the release's published SHA-256 before core unpacks it.
 *
 * Hooked to upgrader_pre_download, which every Theme_Upgrader path runs
 * (single, bulk, automatic, WP-CLI, and other plugins driving the upgrader).
 * Returning a file path makes core treat it as its own download. If another
 * hook already supplied a file, that file is verified instead of trusted.
 * Anything short of a match fails the update before any file is replaced.
 *
 * @param bool|string|WP_Error $reply      Earlier answer.
 * @param string               $package    Package URL.
 * @param WP_Upgrader          $upgrader   Upgrader.
 * @param array<string, mixed> $hook_extra Context.
 * @return bool|string|WP_Error
 */
function oogle_update_pre_download( $reply, $package, $upgrader, $hook_extra = array() ) {
	if ( is_wp_error( $reply ) || ! is_array( $hook_extra ) || ! oogle_update_is_own_upgrade( $hook_extra ) ) {
		return $reply;
	}
	$release = oogle_update_latest_release();
	if ( null === $release || ! is_string( $package ) || $package !== $release['package'] ) {
		return new WP_Error( 'oogle_update_package_unknown', __( 'The package is not the release the theme updater validated; the update was not applied.', 'oogle' ) );
	}
	$expected = oogle_update_published_checksum( $release['checksum'] );
	if ( is_wp_error( $expected ) ) {
		return $expected;
	}

	$file = $reply;
	if ( false === $file ) {
		if ( ! function_exists( 'download_url' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		$file = download_url( $package, 300 );
		if ( is_wp_error( $file ) ) {
			return $file;
		}
	}
	if ( ! is_string( $file ) || ! is_readable( $file ) ) {
		return new WP_Error( 'oogle_update_package_unreadable', __( 'The downloaded package could not be read.', 'oogle' ) );
	}
	$actual = hash_file( 'sha256', $file );
	if ( ! is_string( $actual ) || ! hash_equals( $expected, strtolower( $actual ) ) ) {
		if ( false === $reply ) {
			wp_delete_file( $file );
		}
		return new WP_Error( 'oogle_update_checksum_mismatch', __( 'The downloaded package does not match the SHA-256 published with the release; the update was not applied.', 'oogle' ) );
	}
	return $file;
}
add_filter( 'upgrader_pre_download', 'oogle_update_pre_download', 10, 4 );

/**
 * Prove that an extracted package really is this theme at the expected
 * version before core replaces the installed parent with it.
 *
 * Core's own Theme_Upgrader::check_package() runs for installs and bulk
 * updates but not for Theme_Upgrader::upgrade() (the no-JavaScript update
 * screen, WordPress automatic updates, and any plugin that drives the
 * upgrader directly), so this repeats those checks for every path and adds
 * the identity checks core has no opinion on.
 *
 * @param string $directory Extracted package directory (local path, trailing slash).
 * @param string $expected  Version the update pipeline is installing.
 * @return true|WP_Error
 */
function oogle_update_validate_package( string $directory, string $expected ) {
	$directory = trailingslashit( $directory );
	$fail      = static function ( string $code, string $message ): WP_Error {
		return new WP_Error( $code, sprintf( '%s %s', __( 'The update package was rejected before anything was replaced:', 'oogle' ), $message ) );
	};

	if ( '' === $expected ) {
		return $fail( 'oogle_update_package_no_expected', __( 'the version being installed is unknown.', 'oogle' ) );
	}
	if ( ! is_dir( $directory ) || ! is_file( $directory . 'style.css' ) ) {
		return $fail( 'oogle_update_package_no_style', __( 'style.css is missing.', 'oogle' ) );
	}

	$installed = wp_get_theme( get_template() );
	$headers   = get_file_data(
		$directory . 'style.css',
		array(
			'Name'        => 'Theme Name',
			'Version'     => 'Version',
			'Template'    => 'Template',
			'RequiresWP'  => 'Requires at least',
			'RequiresPHP' => 'Requires PHP',
			'UpdateURI'   => 'Update URI',
		)
	);

	if ( '' === $headers['Name'] || $headers['Name'] !== (string) $installed->get( 'Name' ) ) {
		return $fail( 'oogle_update_package_identity', sprintf( 'it is "%s", not %s.', $headers['Name'], (string) $installed->get( 'Name' ) ) );
	}
	if ( '' !== $headers['Template'] ) {
		return $fail( 'oogle_update_package_child', __( 'it is a child theme, not the parent.', 'oogle' ) );
	}
	if ( $headers['UpdateURI'] !== (string) $installed->get( 'UpdateURI' ) ) {
		return $fail( 'oogle_update_package_uri', __( 'its Update URI is not this theme\'s repository.', 'oogle' ) );
	}
	if ( $headers['Version'] !== $expected ) {
		return $fail( 'oogle_update_package_version', sprintf( 'it is version "%s", not %s.', $headers['Version'], $expected ) );
	}
	if ( version_compare( $headers['Version'], (string) $installed->get( 'Version' ), '<' ) ) {
		return $fail( 'oogle_update_package_downgrade', __( 'it is older than the installed version.', 'oogle' ) );
	}
	if ( ! preg_match( '/^\d+(?:\.\d+){1,3}$/', $headers['RequiresPHP'] ) || ! preg_match( '/^\d+(?:\.\d+){1,3}$/', $headers['RequiresWP'] ) ) {
		return $fail( 'oogle_update_package_requirements', __( 'it does not declare both "Requires PHP" and "Requires at least".', 'oogle' ) );
	}
	if ( ! is_php_version_compatible( $headers['RequiresPHP'] ) ) {
		return $fail( 'oogle_update_package_php', sprintf( 'it requires PHP %s; this server runs %s.', $headers['RequiresPHP'], PHP_VERSION ) );
	}
	if ( ! is_wp_version_compatible( $headers['RequiresWP'] ) ) {
		return $fail( 'oogle_update_package_wp', sprintf( 'it requires WordPress %s; this site runs %s.', $headers['RequiresWP'], get_bloginfo( 'version' ) ) );
	}
	if ( ! is_file( $directory . 'templates/index.html' ) ) {
		return $fail( 'oogle_update_package_no_index', __( 'templates/index.html is missing.', 'oogle' ) );
	}
	if ( ! is_file( $directory . 'theme.json' ) ) {
		return $fail( 'oogle_update_package_no_theme_json', __( 'theme.json is missing.', 'oogle' ) );
	}
	$theme_json = json_decode( (string) file_get_contents( $directory . 'theme.json' ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local extracted file.
	if ( ! is_array( $theme_json ) || empty( $theme_json['version'] ) || ! is_int( $theme_json['version'] ) || ! isset( $theme_json['settings'] ) || ! is_array( $theme_json['settings'] ) ) {
		return $fail( 'oogle_update_package_theme_json', __( 'theme.json is not a valid theme.json document.', 'oogle' ) );
	}

	return true;
}

/**
 * Validate the extracted package, then install it into the theme's real
 * directory whatever the zip's top folder is called (a hand-built zip may say
 * oogle-theme-1.2.3/).
 *
 * @param string|WP_Error      $source        Extracted source path.
 * @param string               $remote_source Working directory.
 * @param WP_Upgrader          $upgrader      Upgrader.
 * @param array<string, mixed> $hook_extra    Context; 'theme' is the directory being updated.
 * @return string|WP_Error
 */
function oogle_update_source_selection( $source, string $remote_source, WP_Upgrader $upgrader, array $hook_extra = array() ) {
	global $wp_filesystem;
	if ( is_wp_error( $source ) || ! oogle_update_is_own_upgrade( $hook_extra ) ) {
		return $source;
	}

	// $source is a filesystem-abstraction path; validation reads the local copy of the same directory.
	$local = $wp_filesystem ? str_replace( $wp_filesystem->wp_content_dir(), trailingslashit( WP_CONTENT_DIR ), (string) $source ) : (string) $source;
	$valid = oogle_update_validate_package( $local, oogle_update_expected_version() );
	if ( is_wp_error( $valid ) ) {
		// When this theme is the active stylesheet (no child), Theme_Upgrader::current_before()
		// has already switched maintenance mode on for this run, and core only switches it
		// off from upgrader_post_install — which a rejected source never reaches. Do not
		// leave the site answering 503 for the 10-minute expiry because we refused a package.
		$upgrader->maintenance_mode( false );
		return $valid;
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
